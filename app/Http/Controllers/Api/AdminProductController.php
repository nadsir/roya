<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttributeValue;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminProductController extends Controller
{
    public function meta()
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->whereNull('parent_id')
            ->with([
                'attributes.values',
                'children' => function ($query) {
                    $query
                        ->where('is_active', true)
                        ->with('attributes.values')
                        ->orderBy('sort_order');
                },
            ])
            ->orderBy('sort_order')
            ->get();

        $attributes = \App\Models\Attribute::query()
            ->with([
                'values' => function ($query) {
                    $query->orderBy('sort_order');
                },
            ])
            ->orderBy('sort_order')
            ->get();

        $categoryAttributes = [];

        foreach ($categories as $category) {
            $categoryAttributes[$category->id] =
                $category->attributes->values();

            foreach ($category->children as $child) {
                $categoryAttributes[$child->id] =
                    $child->attributes->values();
            }
        }

        return response()->json([
            'categories' => $categories,
            'attributes' => $attributes,
            'category_attributes' => $categoryAttributes,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateProduct($request);

        return DB::transaction(function () use ($data) {
            $categoryIds = $data['category_ids'] ?? [];
            $attributeValueIds = $data['attribute_value_ids'] ?? [];
            $variants = $data['variants'] ?? [];

            unset(
                $data['category_ids'],
                $data['attribute_value_ids'],
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

            $this->syncVariants(
                $product,
                $variants
            );

            return response()->json(
                $product->load([
                    'categories',
                    'attributeValues.attribute',
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
            'variants.attributeValues.attribute',
            'images',
        ]);

        return response()->json($product);
    }

    public function update(
        Request $request,
        Product $product
    ) {
        $data = $this->validateProduct(
            $request,
            $product
        );

        return DB::transaction(function () use (
            $data,
            $product
        ) {
            $categoryIds = $data['category_ids'] ?? [];
            $attributeValueIds = $data['attribute_value_ids'] ?? [];
            $variants = $data['variants'] ?? [];

            unset(
                $data['category_ids'],
                $data['attribute_value_ids'],
                $data['variants']
            );

            $product->update($data);

            $this->syncProductCategories(
                $product,
                $categoryIds
            );

            $this->syncProductAttributes(
                $product,
                $attributeValueIds
            );

            /*
             * فعلاً Variantها از نو ساخته می‌شوند.
             * در مرحله بعدی که مدیریت Variant Image را اضافه کنیم،
             * این بخش را تغییر می‌دهیم تا ID و تصاویر Variantها حفظ شوند.
             */
            $product->variants()->delete();

            $this->syncVariants(
                $product,
                $variants
            );

            return response()->json(
                $product->fresh()->load([
                    'categories',
                    'attributeValues.attribute',
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

    protected function syncVariants(
        Product $product,
        array $variants
    ): void {
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

            $variantData['combination_key'] =
                implode('-', $uniqueValueIds);

            if (
                $variantData['combination_key'] === ''
            ) {
                $variantData['combination_key'] =
                    !empty($variantData['sku'])
                        ? 'sku-' . $variantData['sku']
                        : uniqid(
                            'variant_',
                            true
                        );
            }

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
    }
}