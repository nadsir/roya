<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryTreeResource;
use App\Models\Attribute;
use App\Models\Category;
use App\Services\CategoryTreeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminCategoryController extends Controller
{
    public function __construct(
        private readonly CategoryTreeService $categoryTree
    ) {
    }

    public function index()
    {
        return CategoryTreeResource::collection(
            $this->categoryTree->tree(activeOnly: false)
        );
    }

    public function show(Category $category)
    {
        return new CategoryTreeResource(
            $category->setRelation('children', collect())
        );
    }

    public function search(Request $request)
    {
        $query = $request->get('q', '');

        if (empty(trim($query))) {
            return response()->json(['data' => []]);
        }

        $categories = Category::query()
            ->where('name', 'LIKE', "%{$query}%")
            ->orWhere('slug', 'LIKE', "%{$query}%")
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(50)
            ->get();

        // Build ancestor paths for each matched category
        $results = $categories->map(function ($category) {
            $ancestors = $category->ancestors()->toArray();
            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'parent_id' => $category->parent_id,
                'is_active' => (bool) $category->is_active,
                'sort_order' => $category->sort_order,
                'ancestors' => $ancestors,
                'ancestor_ids' => array_column($ancestors, 'id'),
            ];
        });

        return response()->json(['data' => $results]);
    }

    public function store(Request $request)
    {
        $category = Category::create($this->validatedData($request));

        return (new CategoryTreeResource(
            $category->setRelation('children', collect())
        ))->response()->setStatusCode(201);
    }

    public function update(Request $request, Category $category)
    {
        $data = $this->validatedData($request, $category);

        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null) {
            $this->ensureValidParent($category, (int) $data['parent_id']);
        }

        $category->update($data);

        return new CategoryTreeResource(
            $category->fresh()->setRelation('children', collect())
        );
    }

    public function destroy(Category $category)
    {
        $dependencies = [
            'children' => $category->children()->exists(),
            'products' => $category->products()->exists(),
            'attribute configurations' => $category->attributes()->exists(),
        ];

        $blockingDependencies = array_keys(
            array_filter($dependencies)
        );

        if ($blockingDependencies !== []) {
            throw ValidationException::withMessages([
                'category' => [
                    'This category cannot be deleted while it has '
                        . implode(', ', $blockingDependencies) . '.',
                ],
            ]);
        }

        $category->delete();

        return response()->noContent();
    }

    public function attributes(Request $request, Category $category)
    {
        if ($request->isMethod('get')) {
            return response()->json([
                'data' => $this->buildAttributeStateResponse($category),
            ]);
        }

        $data = $request->validate([
            'attributes' => ['required', 'array'],
            'attributes.*.attribute_id' => [
                'required',
                'integer',
                'distinct',
                'exists:attributes,id',
            ],
            'attributes.*.is_enabled' => ['required', 'boolean'],
            'attributes.*.is_required' => ['required', 'boolean'],
            'attributes.*.is_filterable' => ['required', 'boolean'],
            'attributes.*.is_variant_axis' => ['required', 'boolean'],
            'attributes.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $this->validateVariantAxes($data['attributes']);

        DB::transaction(function () use ($category, $data) {
            $category->attributes()->sync(
                collect($data['attributes'])
                    ->mapWithKeys(fn (array $configuration) => [
                        $configuration['attribute_id'] => [
                            'is_enabled' => $configuration['is_enabled'],
                            'is_required' => $configuration['is_required'],
                            'is_filterable' => $configuration['is_filterable'],
                            'is_variant_axis' => $configuration['is_variant_axis'],
                            'sort_order' => $configuration['sort_order'],
                        ],
                    ])
                    ->all()
            );
        });

        return response()->json([
            'data' => $category->attributes()
                ->with('values')
                ->orderByPivot('sort_order')
                ->get(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?Category $category = null): array
    {
        $categoryId = $category?->id;
        $isUpdate = $category !== null;

        return $request->validate([
            'name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'max:255',
                Rule::unique('categories', 'slug')->ignore($categoryId),
            ],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'description' => ['sometimes', 'nullable', 'string'],
            'image' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
    }

    private function ensureValidParent(Category $category, int $parentId): void
    {
        if (! $this->categoryTree->wouldCreateCycle($category, $parentId)) {
            return;
        }

        throw ValidationException::withMessages([
            'parent_id' => [
                'A category cannot be its own parent or a descendant of itself.',
            ],
        ]);
    }

    /**
     * Build the merged attribute list for the three-state UI.
     *
     * Direct attributes (is_enabled=true/false) → state: 'enabled'|'disabled'
     * Inherited-only attributes (from parent, no override) → state: 'inherit'
     *
     * @return \Illuminate\Support\Collection
     */
    private function buildAttributeStateResponse(Category $category)
    {
        $directAttributes = $category->attributes()
            ->with('values')
            ->orderByPivot('sort_order')
            ->get();

        $directMap = $directAttributes->mapWithKeys(fn ($attr) => [
            $attr->id => [
                'id' => $attr->id,
                'name' => $attr->name,
                'slug' => $attr->slug,
                'type' => $attr->type,
                'sort_order' => $attr->pivot->sort_order,
                'state' => $attr->pivot->is_enabled ? 'enabled' : 'disabled',
                'config' => [
                    'is_enabled' => (bool) $attr->pivot->is_enabled,
                    'is_required' => (bool) $attr->pivot->is_required,
                    'is_filterable' => (bool) $attr->pivot->is_filterable,
                    'is_variant_axis' => (bool) $attr->pivot->is_variant_axis,
                    'sort_order' => $attr->pivot->sort_order,
                ],
                'values' => $attr->values,
            ],
        ]);

        if (! $category->parent_id) {
            return $directMap->values();
        }

        $parentCategory = Category::query()->find($category->parent_id);

        if (! $parentCategory) {
            return $directMap->values();
        }

        $inheritedAttributes = app(\App\Services\EffectiveCategoryAttributesResolver::class)
            ->for($parentCategory);

        foreach ($inheritedAttributes as $attr) {
            if ($directMap->has($attr->id)) {
                continue;
            }

            $directMap->put($attr->id, [
                'id' => $attr->id,
                'name' => $attr->name,
                'slug' => $attr->slug,
                'type' => $attr->type,
                'sort_order' => $attr->pivot->sort_order,
                'state' => 'inherit',
                'config' => [
                    'is_enabled' => true,
                    'is_required' => (bool) $attr->pivot->is_required,
                    'is_filterable' => (bool) $attr->pivot->is_filterable,
                    'is_variant_axis' => (bool) $attr->pivot->is_variant_axis,
                    'sort_order' => $attr->pivot->sort_order,
                ],
                'values' => $attr->values,
            ]);
        }

        return $directMap->values();
    }

    /**
     * @param array<int, array<string, mixed>> $configurations
     */
    private function validateVariantAxes(array $configurations): void
    {
        $variantAttributeIds = collect($configurations)
            ->filter(fn (array $configuration) => $configuration['is_variant_axis'])
            ->pluck('attribute_id');

        if ($variantAttributeIds->isEmpty()) {
            return;
        }

        $unsupportedIds = Attribute::query()
            ->whereIn('id', $variantAttributeIds)
            ->whereNotIn('type', ['select', 'multiselect', 'color'])
            ->pluck('id');

        if ($unsupportedIds->isEmpty()) {
            return;
        }

        throw ValidationException::withMessages([
            'attributes' => [
                'Only select, multiselect, and color attributes can be variant axes.',
            ],
        ]);
    }
}
