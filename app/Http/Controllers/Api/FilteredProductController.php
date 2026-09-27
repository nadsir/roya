<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Services\CategoryTreeService;
use App\Services\EffectiveCategoryAttributesResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class FilteredProductController extends Controller
{
    private const RESERVED_FILTER_KEYS = [
        'category',
        'categories',
        'page',
        'per_page',
        'sort',
        'search',
        'price_min',
        'price_max',
        'in_stock',
        'vehicle_brand_id',
        'vehicle_model_id',
        'vehicle_generation_id',
        'vehicle_trim_id',
        'vehicle_engine_id',
    ];

    private const SORT_MAP = [
        'newest' => ['column' => 'published_at', 'direction' => 'desc'],
        'oldest' => ['column' => 'published_at', 'direction' => 'asc'],
        'price_asc' => ['column' => 'price', 'direction' => 'asc'],
        'price_desc' => ['column' => 'price', 'direction' => 'desc'],
        'name_asc' => ['column' => 'name', 'direction' => 'asc'],
        'name_desc' => ['column' => 'name', 'direction' => 'desc'],
    ];

    private const MAX_PER_PAGE = 48;

    public function __construct(
        private readonly CategoryTreeService $categoryTree,
        private readonly EffectiveCategoryAttributesResolver $effectiveAttributes
    ) {
    }

    public function index(Request $request)
    {
        $categorySlugs = $this->categorySlugs($request);
        $attributeFilters = $this->attributeFilters($request);

        if ($attributeFilters !== [] && count($categorySlugs) !== 1) {
            throw ValidationException::withMessages([
                'category' => [
                    'Exactly one active category is required when filtering by attributes.',
                ],
            ]);
        }

        $categories = $this->categoriesFor($categorySlugs);

        $query = Product::query()
            ->with([
                'categories:id,name,slug',
                'images:id,product_id,variant_id,path,alt_text,is_primary,sort_order',
                'attributeValues:id,attribute_id,label,value,hex_color,sort_order',
                'attributeValues.attribute:id,name,slug,type',
                'customAttributeValues:id,product_id,attribute_id,value_type,value_number,value_boolean,value_text',
                'customAttributeValues.attribute:id,name,slug,type',
                'variants:id,product_id,sku,price,compare_at_price,stock,is_active,combination_key',
                'variants.attributeValues:id,attribute_id,label,value,hex_color',
                'variants.attributeValues.attribute:id,name,slug,type',
                'vehicleEngines:id,name,slug,vehicle_trim_id',
                'vehicleEngines.trim:id,name,vehicle_generation_id',
                'vehicleEngines.trim.generation:id,name,year_start,year_end,vehicle_model_id',
                'vehicleEngines.trim.generation.model:id,name,vehicle_brand_id',
                'vehicleEngines.trim.generation.model.brand:id,name,slug',
            ])
            ->where('is_active', true);

        // ── Category ──────────────────────────────────────────────
        if ($categories->isNotEmpty()) {
            $categoryIds = $this->categoryTree->descendantIdsFor($categories);

            $query->whereHas(
                'categories',
                fn ($categoryQuery) => $categoryQuery->whereIn('categories.id', $categoryIds)
            );
        }

        // ── Attribute filters ─────────────────────────────────────
        if ($attributeFilters !== []) {
            $this->applyAttributeFilters(
                $query,
                $categories->first(),
                $attributeFilters
            );
        }

        // ── Price ─────────────────────────────────────────────────
        $this->applyPriceFilter($query, $request);

        // ── Stock / in_stock ──────────────────────────────────────
        $this->applyStockFilter($query, $request);

        // ── Vehicle compatibility ─────────────────────────────────
        $this->applyVehicleFilter($query, $request);

        // ── Search ────────────────────────────────────────────────
        $this->applySearch($query, $request);

        // ── Sort ──────────────────────────────────────────────────
        $this->applySort($query, $request);

        // ── Pagination ────────────────────────────────────────────
        $perPage = $this->perPage($request);
        $products = $query->paginate($perPage);

        return ProductResource::collection($products);
    }

    /* ────────────────────────────────────────────────────────────
     | Category helpers
     | ──────────────────────────────────────────────────────────── */

    /**
     * @return list<string>
     */
    private function categorySlugs(Request $request): array
    {
        $categories = $request->input('category', $request->input('categories', []));
        $categories = is_array($categories)
            ? $categories
            : explode(',', (string) $categories);

        return collect($categories)
            ->filter(fn ($slug) => is_string($slug) && $slug !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param list<string> $categorySlugs
     * @return \Illuminate\Support\Collection<int, Category>
     */
    private function categoriesFor(array $categorySlugs)
    {
        if ($categorySlugs === []) {
            return collect();
        }

        $categories = Category::query()
            ->whereIn('slug', $categorySlugs)
            ->where('is_active', true)
            ->get();

        if ($categories->count() !== count($categorySlugs)) {
            throw ValidationException::withMessages([
                'category' => ['One or more categories are invalid or inactive.'],
            ]);
        }

        return $categories;
    }

    /* ────────────────────────────────────────────────────────────
     | Attribute filters
     | ──────────────────────────────────────────────────────────── */

    /**
     * @return array<string, list<string>>
     */
    private function attributeFilters(Request $request): array
    {
        return collect($request->except(self::RESERVED_FILTER_KEYS))
            ->map(function ($values) {
                $values = is_array($values)
                    ? $values
                    : explode(',', (string) $values);

                return collect($values)
                    ->filter(fn ($value) => is_string($value) && $value !== '')
                    ->unique()
                    ->values()
                    ->all();
            })
            ->filter(fn (array $values) => $values !== [])
            ->all();
    }

    /**
     * @param array<string, list<string>> $attributeFilters
     */
    private function applyAttributeFilters(
        $query,
        Category $category,
        array $attributeFilters
    ): void {
        $allowedAttributes = $this->effectiveAttributes
            ->for($category)
            ->filter(fn ($attribute) => $attribute->pivot->is_filterable)
            ->keyBy('slug');

        foreach ($attributeFilters as $attributeSlug => $values) {
            $attribute = $allowedAttributes->get($attributeSlug);

            if ($attribute === null) {
                throw ValidationException::withMessages([
                    $attributeSlug => ['This filter is not available for the selected category.'],
                ]);
            }

            if (in_array($attribute->type, ['number', 'boolean', 'text'], true)) {
                $this->applyCustomAttributeFilter(
                    $query,
                    $attribute,
                    $attributeSlug,
                    $values
                );

                continue;
            }

            $allowedValues = $attribute->values->pluck('value');

            if (collect($values)->diff($allowedValues)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    $attributeSlug => ['One or more filter values are invalid.'],
                ]);
            }

            $query->where(function ($productQuery) use ($attribute, $values) {
                $productQuery
                    ->whereHas('attributeValues', function ($valueQuery) use ($attribute, $values) {
                        $valueQuery
                            ->where('attribute_values.attribute_id', $attribute->id)
                            ->whereIn('attribute_values.value', $values);
                    })
                    ->orWhereHas('variants', function ($variantQuery) use ($attribute, $values) {
                        $variantQuery
                            ->where('is_active', true)
                            ->whereHas('attributeValues', function ($valueQuery) use ($attribute, $values) {
                                $valueQuery
                                    ->where('attribute_values.attribute_id', $attribute->id)
                                    ->whereIn('attribute_values.value', $values);
                            });
                    });
            });
        }
    }

    /**
     * @param list<string> $values
     */
    private function applyCustomAttributeFilter(
        $query,
        $attribute,
        string $attributeSlug,
        array $values
    ): void {
        if ($attribute->type === 'number') {
            if (collect($values)->contains(fn ($value) => ! is_numeric($value))) {
                throw ValidationException::withMessages([
                    $attributeSlug => ['All filter values must be numeric.'],
                ]);
            }

            $query->whereHas('customAttributeValues', function ($valueQuery) use ($attribute, $values) {
                $valueQuery
                    ->where('attribute_id', $attribute->id)
                    ->whereIn('value_number', $values);
            });

            return;
        }

        if ($attribute->type === 'boolean') {
            $booleanValues = collect($values)->map(function ($value) use ($attributeSlug) {
                if (! in_array($value, ['0', '1', 'true', 'false'], true)) {
                    throw ValidationException::withMessages([
                        $attributeSlug => ['All filter values must be boolean.'],
                    ]);
                }

                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            });

            $query->whereHas('customAttributeValues', function ($valueQuery) use ($attribute, $booleanValues) {
                $valueQuery
                    ->where('attribute_id', $attribute->id)
                    ->whereIn('value_boolean', $booleanValues->all());
            });

            return;
        }

        $query->whereHas('customAttributeValues', function ($valueQuery) use ($attribute, $values) {
            $valueQuery
                ->where('attribute_id', $attribute->id)
                ->whereIn('value_text', $values);
        });
    }

    /* ────────────────────────────────────────────────────────────
     | Price filter
     | ──────────────────────────────────────────────────────────── */

    private function applyPriceFilter($query, Request $request): void
    {
        $min = $request->input('price_min');
        $max = $request->input('price_max');

        if ($min !== null) {
            if (! is_numeric($min)) {
                throw ValidationException::withMessages([
                    'price_min' => ['Must be a number.'],
                ]);
            }

            $query->where(function ($q) use ($min) {
                $q->where('products.price', '>=', $min)
                    ->orWhereExists(function ($sub) use ($min) {
                        $sub->from('product_variants')
                            ->whereColumn('product_variants.product_id', 'products.id')
                            ->where('product_variants.is_active', true)
                            ->whereRaw('COALESCE(product_variants.price, products.price) >= ?', [$min]);
                    });
            });
        }

        if ($max !== null) {
            if (! is_numeric($max)) {
                throw ValidationException::withMessages([
                    'price_max' => ['Must be a number.'],
                ]);
            }

            $query->where(function ($q) use ($max) {
                $q->where('products.price', '<=', $max)
                    ->orWhereExists(function ($sub) use ($max) {
                        $sub->from('product_variants')
                            ->whereColumn('product_variants.product_id', 'products.id')
                            ->where('product_variants.is_active', true)
                            ->whereRaw('COALESCE(product_variants.price, products.price) <= ?', [$max]);
                    });
            });
        }
    }

    /* ────────────────────────────────────────────────────────────
     | Stock / in_stock filter
     | ──────────────────────────────────────────────────────────── */

    private function applyStockFilter($query, Request $request): void
    {
        $value = $request->input('in_stock');

        if ($value === null) {
            return;
        }

        $inStock = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        if ($inStock) {
            $query->where(function ($q) {
                $q->where(function ($noVariants) {
                    $noVariants->whereDoesntHave('variants')
                        ->where('products.stock', '>', 0);
                })->orWhere(function ($hasVariants) {
                    $hasVariants->whereHas('variants', function ($v) {
                        $v->where('is_active', true);
                    }, '>', 0)
                        ->whereRaw('(
                            SELECT COALESCE(SUM(stock), 0)
                            FROM product_variants
                            WHERE product_variants.product_id = products.id
                              AND product_variants.is_active = 1
                        ) > 0');
                });
            });
        } else {
            $query->where(function ($q) {
                $q->where(function ($noVariants) {
                    $noVariants->whereDoesntHave('variants')
                        ->where('products.stock', '<=', 0);
                })->orWhere(function ($hasVariants) {
                    $hasVariants->whereHas('variants', function ($v) {
                        $v->where('is_active', true);
                    }, '>', 0)
                        ->whereRaw('(
                            SELECT COALESCE(SUM(stock), 0)
                            FROM product_variants
                            WHERE product_variants.product_id = products.id
                              AND product_variants.is_active = 1
                        ) <= 0');
                });
            });
        }
    }

    /* ────────────────────────────────────────────────────────────
     | Vehicle compatibility filter
     | ──────────────────────────────────────────────────────────── */

    private function applyVehicleFilter($query, Request $request): void
    {
        $engineId = $request->input('vehicle_engine_id');
        $trimId = $request->input('vehicle_trim_id');
        $generationId = $request->input('vehicle_generation_id');
        $modelId = $request->input('vehicle_model_id');
        $brandId = $request->input('vehicle_brand_id');

        if ($engineId !== null) {
            $query->whereHas('vehicleEngines', fn ($q) => $q->where('vehicle_engines.id', $engineId));

            return;
        }

        if ($trimId !== null) {
            $query->whereHas('vehicleEngines', fn ($q) => $q->where('vehicle_engines.vehicle_trim_id', $trimId));

            return;
        }

        if ($generationId !== null) {
            $query->whereHas('vehicleEngines.trim', fn ($q) => $q->where('vehicle_trims.vehicle_generation_id', $generationId));

            return;
        }

        if ($modelId !== null) {
            $query->whereHas(
                'vehicleEngines.trim.generation',
                fn ($q) => $q->where('vehicle_generations.vehicle_model_id', $modelId)
            );

            return;
        }

        if ($brandId !== null) {
            $query->whereHas(
                'vehicleEngines.trim.generation.model',
                fn ($q) => $q->where('vehicle_models.vehicle_brand_id', $brandId)
            );
        }
    }

    /* ────────────────────────────────────────────────────────────
     | Search
     | ──────────────────────────────────────────────────────────── */

    private function applySearch($query, Request $request): void
    {
        $term = $request->input('search');

        if ($term === null || $term === '') {
            return;
        }

        $term = trim($term);

        $query->where(function ($q) use ($term) {
            $q->where('products.name', 'like', "%{$term}%")
                ->orWhere('products.sku', 'like', "%{$term}%")
                ->orWhere('products.short_description', 'like', "%{$term}%")
                ->orWhere('products.description', 'like', "%{$term}%");
        });
    }

    /* ────────────────────────────────────────────────────────────
     | Sort
     | ──────────────────────────────────────────────────────────── */

    private function applySort($query, Request $request): void
    {
        $sort = $request->input('sort');

        if ($sort === null) {
            $query->orderByDesc('products.published_at')
                ->orderByDesc('products.created_at');

            return;
        }

        if (! isset(self::SORT_MAP[$sort])) {
            throw ValidationException::withMessages([
                'sort' => ['Invalid sort value. Allowed: ' . implode(', ', array_keys(self::SORT_MAP)) . '.'],
            ]);
        }

        $sortConfig = self::SORT_MAP[$sort];

        $query->orderBy($sortConfig['column'], $sortConfig['direction'])
            ->orderByDesc('products.created_at');
    }

    /* ────────────────────────────────────────────────────────────
     | Pagination
     | ──────────────────────────────────────────────────────────── */

    private function perPage(Request $request): int
    {
        $value = $request->input('per_page', 12);

        if (! is_numeric($value) || (int) $value < 1) {
            return 12;
        }

        return min((int) $value, self::MAX_PER_PAGE);
    }
}
