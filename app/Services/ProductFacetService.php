<?php

namespace App\Services;

use App\Models\Attribute;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductFacetService
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

    private const CUSTOM_TYPES = ['number', 'boolean', 'text'];

    public function __construct(
        private readonly CategoryTreeService $categoryTree
    ) {
    }

    /**
     * Attach product-scoped facet values (with per-value counts) to the
     * effective filterable attributes of a category.
     *
     * @param Collection<int, Attribute> $attributes
     * @return Collection<int, Attribute>
     */
    public function apply(Request $request, Category $category, Collection $attributes): Collection
    {
        if ($attributes->isEmpty()) {
            return $attributes;
        }

        $activeFilters = $this->attributeFilters($request);
        $allowedBySlug = $attributes->keyBy('slug');

        $this->validateActiveFilters($activeFilters, $allowedBySlug);

        $descendantIds = $this->categoryTree->descendantIdsFor(collect([$category]));

        foreach ($attributes as $attribute) {
            $attribute->setRelation(
                'facetValues',
                $this->facetValues($request, $attribute, $activeFilters, $allowedBySlug, $descendantIds)
            );
        }

        return $attributes;
    }

    /**
     * Counts for every usable value of a single facet.
     * The facet's own filter is excluded from the baseline so that active
     * values remain selectable for removal; every other context filter
     * (category + descendants, other attributes, price, stock, vehicle,
     * search) is applied. Pagination and sort never participate.
     *
     * @param array<string, list<string>> $activeFilters
     * @param Collection<int, Attribute> $allowedBySlug
     * @param Collection<int, int> $descendantIds
     */
    private function facetValues(
        Request $request,
        Attribute $attribute,
        array $activeFilters,
        Collection $allowedBySlug,
        Collection $descendantIds
    ): Collection {
        $base = $this->baseQuery(
            $request,
            $activeFilters,
            $attribute->slug,
            $allowedBySlug,
            $descendantIds
        );

        $active = collect($activeFilters[$attribute->slug] ?? []);

        $counts = in_array($attribute->type, self::CUSTOM_TYPES, true)
            ? $this->customCounts($base, $attribute)
            : $this->selectCounts($base, $attribute);

        return in_array($attribute->type, self::CUSTOM_TYPES, true)
            ? $this->buildCustomValues($counts, $active)
            : $this->buildSelectValues($attribute, $counts, $active);
    }

    /**
     * Distinct value + product count per attribute value, considering both
     * product-level and active-variant-level assignments.
     *
     * @param Builder $base
     */
    private function selectCounts(Builder $base, Attribute $attribute): Collection
    {
        return (clone $base)
            ->toBase()
            ->leftJoin('product_attribute_values as pav', function ($join) use ($attribute) {
                $join->on('pav.product_id', '=', 'products.id')
                    ->where('pav.attribute_id', '=', $attribute->id);
            })
            ->leftJoin('product_variants as pv', function ($join) {
                $join->on('pv.product_id', '=', 'products.id')
                    ->where('pv.is_active', '=', 1);
            })
            ->leftJoin('variant_attribute_values as vav', 'vav.variant_id', '=', 'pv.id')
            ->leftJoin('attribute_values as av', function ($join) {
                $join->on('av.id', '=', 'pav.attribute_value_id')
                    ->orOn('av.id', '=', 'vav.attribute_value_id');
            })
            ->where('av.attribute_id', '=', $attribute->id)
            ->groupBy('av.id', 'av.label', 'av.value', 'av.hex_color')
            ->select([
                'av.id',
                'av.label',
                'av.value',
                'av.hex_color',
                DB::raw('COUNT(DISTINCT products.id) as cnt'),
            ])
            ->get()
            ->map(fn ($row) => (object) [
                'id' => $row->id,
                'label' => $row->label,
                'value' => $row->value,
                'hex_color' => $row->hex_color,
                'count' => (int) $row->cnt,
            ])
            ->keyBy('value');
    }

    /**
     * Distinct scalar values from product_custom_attribute_values.
     *
     * @param Builder $base
     */
    private function customCounts(Builder $base, Attribute $attribute): Collection
    {
        if ($attribute->type === 'boolean') {
            return (clone $base)
                ->toBase()
                ->join('product_custom_attribute_values as pcav', 'pcav.product_id', '=', 'products.id')
                ->where('pcav.attribute_id', '=', $attribute->id)
                ->select([
                    'pcav.value_boolean',
                    DB::raw('COUNT(DISTINCT products.id) as cnt'),
                ])
                ->groupBy('pcav.value_boolean')
                ->get()
                ->mapWithKeys(function ($row) {
                    $value = $row->value_boolean ? 'true' : 'false';

                    return [$value => (object) [
                        'value' => $value,
                        'label' => $value,
                        'count' => (int) $row->cnt,
                    ]];
                });
        }

        if ($attribute->type === 'number') {
            return (clone $base)
                ->toBase()
                ->join('product_custom_attribute_values as pcav', 'pcav.product_id', '=', 'products.id')
                ->where('pcav.attribute_id', '=', $attribute->id)
                ->select([
                    'pcav.value_number',
                    DB::raw('COUNT(DISTINCT products.id) as cnt'),
                ])
                ->groupBy('pcav.value_number')
                ->get()
                ->filter(fn ($row) => $row->value_number !== null)
                ->mapWithKeys(function ($row) {
                    $value = (string) $row->value_number;

                    return [$value => (object) [
                        'value' => $value,
                        'label' => $value,
                        'count' => (int) $row->cnt,
                    ]];
                });
        }

        return (clone $base)
            ->toBase()
            ->join('product_custom_attribute_values as pcav', 'pcav.product_id', '=', 'products.id')
            ->where('pcav.attribute_id', '=', $attribute->id)
            ->select([
                'pcav.value_text',
                DB::raw('COUNT(DISTINCT products.id) as cnt'),
            ])
            ->groupBy('pcav.value_text')
            ->get()
            ->filter(fn ($row) => $row->value_text !== null && $row->value_text !== '')
            ->mapWithKeys(function ($row) {
                return [$row->value_text => (object) [
                    'value' => $row->value_text,
                    'label' => $row->value_text,
                    'count' => (int) $row->cnt,
                ]];
            });
    }

    /**
     * Select-like facet: catalog order first, then any counted value that is
     * not part of the catalog, then active values (count 0) kept for removal.
     *
     * @param Collection<string, object> $counts
     * @param Collection<int, string> $active
     */
    private function buildSelectValues(Attribute $attribute, Collection $counts, Collection $active): Collection
    {
        $output = [];

        foreach ($attribute->values as $catalogValue) {
            $counted = $counts->get($catalogValue->value);

            if ($counted !== null) {
                $output[] = [
                    'id' => $catalogValue->id,
                    'label' => $catalogValue->label,
                    'value' => $catalogValue->value,
                    'hex_color' => $catalogValue->hex_color,
                    'count' => (int) $counted->count,
                ];

                continue;
            }

            if ($active->contains($catalogValue->value)) {
                $output[] = [
                    'id' => $catalogValue->id,
                    'label' => $catalogValue->label,
                    'value' => $catalogValue->value,
                    'hex_color' => $catalogValue->hex_color,
                    'count' => 0,
                ];
            }
        }

        $seen = collect($output)->pluck('value');

        foreach ($counts as $value => $counted) {
            if (! $seen->contains($value)) {
                $output[] = [
                    'id' => $counted->id ?? null,
                    'label' => $counted->label ?? $value,
                    'value' => $value,
                    'hex_color' => $counted->hex_color ?? null,
                    'count' => (int) $counted->count,
                ];

                $seen->push($value);
            }
        }

        return collect($output);
    }

    /**
     * Custom facet: counted distinct values plus active values kept for removal.
     *
     * @param Collection<string, object> $counts
     * @param Collection<int, string> $active
     */
    private function buildCustomValues(Collection $counts, Collection $active): Collection
    {
        $output = collect($counts)
            ->map(fn (object $counted) => [
                'id' => null,
                'label' => $counted->label,
                'value' => $counted->value,
                'hex_color' => null,
                'count' => (int) $counted->count,
            ])
            ->values();

        $seen = $output->pluck('value');

        foreach ($active as $value) {
            if (! $seen->contains($value)) {
                $output->push([
                    'id' => null,
                    'label' => $value,
                    'value' => $value,
                    'hex_color' => null,
                    'count' => 0,
                ]);

                $seen->push($value);
            }
        }

        return $output;
    }

    /**
     * Baseline product result set used for facet counts.
     *
     * @param array<string, list<string>> $activeFilters
     * @param Collection<int, Attribute> $allowedBySlug
     * @param Collection<int, int> $descendantIds
     */
    private function baseQuery(
        Request $request,
        array $activeFilters,
        ?string $excludeSlug,
        Collection $allowedBySlug,
        Collection $descendantIds
    ): Builder {
        $query = Product::query()->where('products.is_active', true);

        if ($descendantIds->isNotEmpty()) {
            $query->whereHas(
                'categories',
                fn ($categoryQuery) => $categoryQuery->whereIn('categories.id', $descendantIds->all())
            );
        }

        foreach ($activeFilters as $slug => $values) {
            if ($slug === $excludeSlug) {
                continue;
            }

            $attribute = $allowedBySlug->get($slug);

            if ($attribute === null) {
                continue;
            }

            if (in_array($attribute->type, self::CUSTOM_TYPES, true)) {
                $this->applyCustomFilter($query, $attribute, $values);
            } else {
                $this->applySelectFilter($query, $attribute, $values);
            }
        }

        $this->applyPriceFilter($query, $request);
        $this->applyStockFilter($query, $request);
        $this->applyVehicleFilter($query, $request);
        $this->applySearchFilter($query, $request);

        return $query;
    }

    /**
     * @param list<string> $values
     */
    private function applySelectFilter(Builder $query, Attribute $attribute, array $values): void
    {
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

    /**
     * @param list<string> $values
     */
    private function applyCustomFilter(Builder $query, Attribute $attribute, array $values): void
    {
        if ($attribute->type === 'number') {
            $query->whereHas('customAttributeValues', function ($valueQuery) use ($attribute, $values) {
                $valueQuery
                    ->where('attribute_id', $attribute->id)
                    ->whereIn('value_number', $values);
            });

            return;
        }

        if ($attribute->type === 'boolean') {
            $booleanValues = collect($values)->map(fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN));

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

    private function applyPriceFilter(Builder $query, Request $request): void
    {
        $min = $request->input('price_min');
        $max = $request->input('price_max');

        if ($min !== null) {
            if (! is_numeric($min)) {
                throw ValidationException::withMessages(['price_min' => ['Must be a number.']]);
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
                throw ValidationException::withMessages(['price_max' => ['Must be a number.']]);
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

    private function applyStockFilter(Builder $query, Request $request): void
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
                    $hasVariants->whereHas('variants', fn ($v) => $v->where('is_active', true), '>', 0)
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
                    $hasVariants->whereHas('variants', fn ($v) => $v->where('is_active', true), '>', 0)
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

    private function applyVehicleFilter(Builder $query, Request $request): void
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
            $query->whereHas('vehicleEngines.trim.generation', fn ($q) => $q->where('vehicle_generations.vehicle_model_id', $modelId));

            return;
        }

        if ($brandId !== null) {
            $query->whereHas('vehicleEngines.trim.generation.model', fn ($q) => $q->where('vehicle_models.vehicle_brand_id', $brandId));
        }
    }

    private function applySearchFilter(Builder $query, Request $request): void
    {
        $term = $request->input('search');

        if ($term === null || trim((string) $term) === '') {
            return;
        }

        $term = trim((string) $term);

        $query->where(function ($q) use ($term) {
            $q->where('products.name', 'like', "%{$term}%")
                ->orWhere('products.sku', 'like', "%{$term}%")
                ->orWhere('products.short_description', 'like', "%{$term}%")
                ->orWhere('products.description', 'like', "%{$term}%");
        });
    }

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
     * @param array<string, list<string>> $activeFilters
     * @param Collection<int, Attribute> $allowedBySlug
     */
    private function validateActiveFilters(array $activeFilters, Collection $allowedBySlug): void
    {
        foreach ($activeFilters as $slug => $values) {
            $attribute = $allowedBySlug->get($slug);

            if ($attribute === null) {
                throw ValidationException::withMessages([
                    $slug => ['This filter is not available for the selected category.'],
                ]);
            }

            if (in_array($attribute->type, self::CUSTOM_TYPES, true)) {
                if ($attribute->type === 'number' && collect($values)->contains(fn ($value) => ! is_numeric($value))) {
                    throw ValidationException::withMessages([
                        $slug => ['All filter values must be numeric.'],
                    ]);
                }

                if ($attribute->type === 'boolean' && collect($values)->contains(fn ($value) => ! in_array($value, ['0', '1', 'true', 'false'], true))) {
                    throw ValidationException::withMessages([
                        $slug => ['All filter values must be boolean.'],
                    ]);
                }

                continue;
            }

            $allowedValues = $attribute->values->pluck('value');

            if (collect($values)->diff($allowedValues)->isNotEmpty()) {
                throw ValidationException::withMessages([
                    $slug => ['One or more filter values are invalid.'],
                ]);
            }
        }
    }
}