<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductCustomAttributeValue;
use App\Models\ProductVariant;
use App\Services\EffectiveCategoryAttributesResolver;
use App\Services\CategoryTreeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminProductController extends Controller
{
    public function __construct(
        private readonly EffectiveCategoryAttributesResolver $effectiveAttributes,
        private readonly CategoryTreeService $categoryTree
    ) {
    }

    public function index(Request $request)
    {
        $query = Product::query()->with('categories');

        if ($search = $request->input('search')) {
            $search = trim($search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        $paginator = $query->latest()->paginate(20);

        return response()->json($paginator);
    }

    public function meta()
    {
        $allCategories = Category::query()
            ->where('is_active', true)
            ->with('attributes.values')
            ->orderBy('sort_order')
            ->get();

        $categories = $this->categoryTree->build($allCategories);

        $attributes = \App\Models\Attribute::query()
            ->with([
                'values' => function ($query) {
                    $query->orderBy('sort_order');
                },
            ])
            ->orderBy('sort_order')
            ->get();

        $categoryAttributes = $this->effectiveAttributes
            ->forMany($allCategories)
            ->map(fn ($attributes) => $attributes->values())
            ->all();

        return response()->json([
            'categories' => $categories,
            'attributes' => $attributes,
            'category_attributes' => $categoryAttributes,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateProduct($request);
        $this->validateProductAttributeValues(
            $data['attribute_value_ids'] ?? [],
            $data['category_ids'] ?? []
        );
        $this->validateCustomAttributeValues(
            $data['custom_attribute_values'] ?? [],
            $data['category_ids'] ?? []
        );
        $this->validateVariantAttributeValues(
            $data['variants'] ?? [],
            $data['category_ids'] ?? []
        );
        $this->validateVariantSkus(
            $data['variants'] ?? [],
            null
        );

        return DB::transaction(function () use ($data) {
            $categoryIds = $data['category_ids'] ?? [];
            $attributeValueIds = $data['attribute_value_ids'] ?? [];
            $customAttributeValues = $data['custom_attribute_values'] ?? [];
            $variants = $data['variants'] ?? [];

            unset(
                $data['category_ids'],
                $data['attribute_value_ids'],
                $data['custom_attribute_values'],
                $data['variants']
            );

            $product = Product::create($data);

            $this->syncProductCategories(
                $product,
                $categoryIds
            );

            $this->syncProductAttributes(
                $product,
                $attributeValueIds
            );

            $this->syncCustomAttributeValues(
                $product,
                $customAttributeValues
            );

            $this->syncVariants(
                $product,
                $variants
            );

            return response()->json(
                $product->load([
                    'categories',
                    'attributeValues.attribute',
                    'customAttributeValues.attribute',
                    'variants.attributeValues.attribute',
                ]),
                201
            );
        });
    }

    public function show(Product $product)
    {
        $product->load([
            'categories',
            'attributeValues.attribute',
            'customAttributeValues.attribute',
            'variants.attributeValues.attribute',
            'images',
            'vehicleEngines.trim.generation.model.brand',
        ]);

        return response()->json($product);
    }

    public function update(
        Request $request,
        Product $product
    ) {
        \Log::info('[PRODUCT SAVE] update called', ['product_id' => $product->id, 'data' => $request->all()]);
        
        $data = $this->validateProduct(
            $request,
            $product
        );
        $this->validateProductAttributeValues(
            $data['attribute_value_ids'] ?? [],
            $data['category_ids'] ?? []
        );
        $this->validateCustomAttributeValues(
            $data['custom_attribute_values'] ?? [],
            $data['category_ids'] ?? []
        );
        $this->validateVariantAttributeValues(
            $data['variants'] ?? [],
            $data['category_ids'] ?? []
        );
        $this->validateVariantSkus(
            $data['variants'] ?? [],
            $product
        );

        return DB::transaction(function () use (
            $data,
            $product
        ) {
            $categoryIds = $data['category_ids'] ?? [];
            $attributeValueIds = $data['attribute_value_ids'] ?? [];
            $customAttributeValues = $data['custom_attribute_values'] ?? [];
            $variants = $data['variants'] ?? [];

            unset(
                $data['category_ids'],
                $data['attribute_value_ids'],
                $data['custom_attribute_values'],
                $data['variants']
            );

            \Log::info('[PRODUCT SAVE] updating product', ['product_id' => $product->id, 'data' => $data]);
            $product->update($data);

            $this->syncProductCategories(
                $product,
                $categoryIds
            );

            $this->syncProductAttributes(
                $product,
                $attributeValueIds
            );

            $this->syncCustomAttributeValues(
                $product,
                $customAttributeValues
            );

            $this->syncVariants(
                $product,
                $variants
            );

            $product->refresh();
            \Log::info('[PRODUCT SAVE] product updated successfully', ['product_id' => $product->id, 'name' => $product->name]);

            return response()->json(
                $product->load([
                    'categories',
                    'attributeValues.attribute',
                    'customAttributeValues.attribute',
                    'variants.attributeValues.attribute',
                    'images',
                ])
            );
        });
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return response()->noContent();
    }

    protected function validateProduct(
        Request $request,
        ?Product $product = null
    ): array {
        $productId = $product?->id;

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique(
                    'products',
                    'slug'
                )->ignore($productId),
            ],

            'sku' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique(
                    'products',
                    'sku'
                )->ignore($productId),
            ],

            'short_description' => [
                'nullable',
                'string',
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'price' => [
                'required',
                'numeric',
                'min:0',
            ],

            'compare_at_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'stock' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'is_active' => [
                'boolean',
            ],

            'is_featured' => [
                'boolean',
            ],

            'category_ids' => [
                'nullable',
                'array',
            ],

            'category_ids.*' => [
                'integer',
                'exists:categories,id',
            ],

            'attribute_value_ids' => [
                'nullable',
                'array',
            ],

            'attribute_value_ids.*' => [
                'integer',
                'exists:attribute_values,id',
            ],

            'custom_attribute_values' => [
                'nullable',
                'array',
            ],

            'custom_attribute_values.*.attribute_id' => [
                'required',
                'integer',
                'distinct',
                'exists:attributes,id',
            ],

            'custom_attribute_values.*.value' => [
                'required',
            ],

            'variants' => [
                'nullable',
                'array',
            ],

            'variants.*.sku' => [
                'nullable',
                'string',
                'max:255',
            ],

            'variants.*.price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'variants.*.compare_at_price' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'variants.*.stock' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'variants.*.is_active' => [
                'boolean',
            ],

            'variants.*.attribute_value_ids' => [
                'nullable',
                'array',
            ],

            'variants.*.attribute_value_ids.*' => [
                'integer',
                'exists:attribute_values,id',
            ],
        ]);
    }

    protected function syncProductCategories(
        Product $product,
        array $categoryIds
    ): void {
        $product->categories()->sync(
            collect($categoryIds)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all()
        );
    }

    protected function syncProductAttributes(
        Product $product,
        array $attributeValueIds
    ): void {
        $attributeValues = AttributeValue::query()
            ->whereIn('id', $attributeValueIds)
            ->get([
                'id',
                'attribute_id',
            ]);

        $productAttributes = [];

        foreach ($attributeValues as $value) {
            $productAttributes[$value->id] = [
                'attribute_id' => $value->attribute_id,
            ];
        }

        $product->attributeValues()->sync(
            $productAttributes
        );
    }

    protected function syncCustomAttributeValues(
        Product $product,
        array $customAttributeValues
    ): void {
        $attributeIds = collect($customAttributeValues)
            ->pluck('attribute_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $product->customAttributeValues()
            ->whereNotIn('attribute_id', $attributeIds)
            ->delete();

        foreach ($customAttributeValues as $valueData) {
            $attribute = Attribute::findOrFail($valueData['attribute_id']);
            $value = $valueData['value'];

            $data = [
                'value_type' => $attribute->type,
                'value_number' => null,
                'value_boolean' => null,
                'value_text' => null,
            ];

            if ($attribute->type === 'number') {
                $data['value_number'] = $value;
            } elseif ($attribute->type === 'boolean') {
                $data['value_boolean'] = filter_var(
                    $value,
                    FILTER_VALIDATE_BOOLEAN
                );
            } else {
                $data['value_text'] = $value;
            }

            ProductCustomAttributeValue::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'attribute_id' => $attribute->id,
                ],
                $data
            );
        }
    }

    protected function validateProductAttributeValues(
        array $attributeValueIds,
        array $categoryIds
    ): void {
        if ($attributeValueIds === []) {
            return;
        }

        $allowedAttributeIds = $this->effectiveAttributeIdsForCategories(
            $categoryIds
        );

        $attributeValues = AttributeValue::query()
            ->whereIn('id', $attributeValueIds)
            ->get(['id', 'attribute_id']);

        if ($attributeValues->contains(
            fn (AttributeValue $value) => ! $allowedAttributeIds->contains(
                $value->attribute_id
            )
        )) {
            throw ValidationException::withMessages([
                'attribute_value_ids' => [
                    'Product values must belong to an effective category attribute.',
                ],
            ]);
        }
    }

    protected function validateCustomAttributeValues(
        array $customAttributeValues,
        array $categoryIds
    ): void
    {
        if ($customAttributeValues === []) {
            return;
        }

        $attributes = Attribute::query()
            ->whereIn(
                'id',
                collect($customAttributeValues)->pluck('attribute_id')
            )
            ->get()
            ->keyBy('id');

        $allowedAttributeIds = $this->effectiveAttributeIdsForCategories(
            $categoryIds
        );

        foreach ($customAttributeValues as $index => $valueData) {
            $attribute = $attributes->get($valueData['attribute_id']);
            $value = $valueData['value'];
            $key = "custom_attribute_values.{$index}.value";

            if ($attribute === null || ! in_array(
                $attribute->type,
                ['number', 'boolean', 'text'],
                true
            )) {
                throw ValidationException::withMessages([
                    $key => ['Only number, boolean, and text attributes accept custom values.'],
                ]);
            }

            if (! $allowedAttributeIds->contains($attribute->id)) {
                throw ValidationException::withMessages([
                    $key => [
                        'Custom values must belong to an effective category attribute.',
                    ],
                ]);
            }

            if ($attribute->type === 'number' && ! is_numeric($value)) {
                throw ValidationException::withMessages([
                    $key => ['The value must be numeric.'],
                ]);
            }

            if ($attribute->type === 'boolean' && ! in_array(
                $value,
                [true, false, 0, 1, '0', '1', 'true', 'false'],
                true
            )) {
                throw ValidationException::withMessages([
                    $key => ['The value must be boolean.'],
                ]);
            }

            if ($attribute->type === 'text' && (! is_string($value) || mb_strlen($value) > 65535)) {
                throw ValidationException::withMessages([
                    $key => ['The value must be text with at most 65535 characters.'],
                ]);
            }
        }
    }

    protected function effectiveAttributeIdsForCategories(array $categoryIds)
    {
        if ($categoryIds === []) {
            return collect();
        }

        $categories = Category::query()
            ->whereIn('id', $categoryIds)
            ->get();

        $allowedAttributeIds = null;

        foreach ($categories as $category) {
            $categoryAttributeIds = $this->effectiveAttributes
                ->for($category)
                ->pluck('id');

            $allowedAttributeIds = $allowedAttributeIds === null
                ? $categoryAttributeIds
                : $allowedAttributeIds
                    ->intersect($categoryAttributeIds)
                    ->values();
        }

        return $allowedAttributeIds ?? collect();
    }

    protected function validateVariantAttributeValues(
        array $variants,
        array $categoryIds
    ): void {
        $variantValueIds = collect($variants)
            ->flatMap(
                fn (array $variant) => $variant['attribute_value_ids'] ?? []
            )
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($variantValueIds->isEmpty()) {
            return;
        }

        $categories = Category::query()
            ->whereIn('id', $categoryIds)
            ->get();

        $allowedAttributeIds = null;

        foreach ($categories as $category) {
            $categoryAxisIds = $this->effectiveAttributes
                ->for($category)
                ->filter(function (Attribute $attribute) {
                    return $attribute->pivot->is_variant_axis
                        && in_array(
                            $attribute->type,
                            ['select', 'multiselect', 'color'],
                            true
                        );
                })
                ->pluck('id');

            $allowedAttributeIds = $allowedAttributeIds === null
                ? $categoryAxisIds
                : $allowedAttributeIds->intersect($categoryAxisIds)->values();
        }

        $attributeValues = AttributeValue::query()
            ->whereIn('id', $variantValueIds)
            ->get(['id', 'attribute_id'])
            ->keyBy('id');

        foreach ($variants as $index => $variant) {
            $valueIds = $variant['attribute_value_ids'] ?? [];

            foreach ($valueIds as $valueId) {
                $attributeValue = $attributeValues->get((int) $valueId);

                if (
                    $attributeValue === null
                    || $allowedAttributeIds === null
                    || ! $allowedAttributeIds->contains($attributeValue->attribute_id)
                ) {
                    throw ValidationException::withMessages([
                        "variants.{$index}.attribute_value_ids" => [
                            'Variant values must belong to a shared category variant axis.',
                        ],
                    ]);
                }
            }

            if ($allowedAttributeIds !== null && $allowedAttributeIds->isNotEmpty()) {
                $usedAxisIds = collect($valueIds)
                    ->map(fn ($id) => $attributeValues->get((int) $id)?->attribute_id)
                    ->filter()
                    ->values();

                $duplicateAxes = $usedAxisIds->duplicates();

                if ($duplicateAxes->isNotEmpty()) {
                    $duplicateAxisId = $duplicateAxes->first();

                    throw ValidationException::withMessages([
                        "variants.{$index}.attribute_value_ids" => [
                            "Attribute \"{$duplicateAxisId}\" has more than one value. Each variant axis must have exactly one value.",
                        ],
                    ]);
                }

                $missingAxes = $allowedAttributeIds->diff($usedAxisIds);

                if ($missingAxes->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        "variants.{$index}.attribute_value_ids" => [
                            "Missing required variant axis(es): " . $missingAxes->implode(', ') . ". Each variant must have a value for every variant axis.",
                        ],
                    ]);
                }
            }
        }
    }

    protected function validateVariantSkus(
        array $variants,
        ?Product $product
    ): void {
        $skuVariants = collect($variants)
            ->filter(fn (array $v) => ! empty($v['sku']))
            ->values();

        if ($skuVariants->isEmpty()) {
            return;
        }

        $requestSkus = $skuVariants
            ->pluck('sku')
            ->values();

        $duplicates = $requestSkus->duplicates();

        if ($duplicates->isNotEmpty()) {
            throw ValidationException::withMessages([
                'variants' => [
                    'Duplicate SKU "' . $duplicates->first() . '" found in variants.',
                ],
            ]);
        }

        $existingByKey = $product
            ? $product->variants()
                ->get()
                ->keyBy('combination_key')
            : collect();

        foreach ($skuVariants as $requestVariant) {
            $sku = $requestVariant['sku'];

            $valueIds = collect(
                $requestVariant['attribute_value_ids'] ?? []
            )
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();

            $comboKey = implode('-', $valueIds);

            $query = ProductVariant::where('sku', $sku);

            if ($product && $comboKey !== '') {
                $existing = $existingByKey->get($comboKey);

                if ($existing) {
                    $query->where('id', '!=', $existing->id);
                }
            }

            if ($query->exists()) {
                throw ValidationException::withMessages([
                    'variants' => [
                        'SKU "' . $sku
                            . '" is already in use by another variant.',
                    ],
                ]);
            }
        }
    }

    protected function syncVariants(
        Product $product,
        array $variants
    ): void {
        $existingByKey = $product->variants()
            ->get()
            ->keyBy('combination_key');

        $processedKeys = [];

        foreach ($variants as $variantData) {
            $variantValueIds =
                $variantData['attribute_value_ids'] ?? [];

            unset(
                $variantData['attribute_value_ids']
            );

            $uniqueValueIds = collect($variantValueIds)
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();

            $combinationKey =
                implode('-', $uniqueValueIds);

            if ($combinationKey === '') {
                $combinationKey =
                    !empty($variantData['sku'])
                        ? 'sku-' . $variantData['sku']
                        : uniqid(
                            'variant_',
                            true
                        );
            }

            $variantData['combination_key'] =
                $combinationKey;

            $existing = $existingByKey->get(
                $combinationKey
            );

            if ($existing) {
                $existing->update(
                    collect($variantData)
                        ->except('combination_key')
                        ->toArray()
                );

                $existing->attributeValues()->sync(
                    $uniqueValueIds
                );
            } else {
                $variant =
                    $product->variants()->create(
                        $variantData
                    );

                if (!empty($uniqueValueIds)) {
                    $variant->attributeValues()->sync(
                        $uniqueValueIds
                    );
                }
            }

            $processedKeys[] = $combinationKey;
        }

        $existingByKey
            ->filter(
                fn ($variant) => ! in_array(
                    $variant->combination_key,
                    $processedKeys,
                    true
                )
            )
            ->each->delete();
    }
}
