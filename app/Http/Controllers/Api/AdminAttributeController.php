<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminAttributeController extends Controller
{
    public function index()
    {
        return response()->json([
            'data' => Attribute::query()
                ->with('values')
                ->orderBy('sort_order')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $attribute = Attribute::create($this->validatedAttribute($request));

        return response()->json([
            'data' => $attribute->load('values'),
        ], 201);
    }

    public function update(Request $request, Attribute $attribute)
    {
        $data = $this->validatedAttribute($request, $attribute);

        if (
            array_key_exists('type', $data)
            && $data['type'] !== $attribute->type
            && $this->typeCannotChange($attribute)
        ) {
            throw ValidationException::withMessages([
                'type' => [
                    'An attribute type cannot change after it is configured or used.',
                ],
            ]);
        }

        $attribute->update($data);

        return response()->json([
            'data' => $attribute->fresh()->load('values'),
        ]);
    }

    public function destroy(Attribute $attribute)
    {
        if ($this->typeIsInUse($attribute)) {
            throw ValidationException::withMessages([
                'attribute' => [
                    'This attribute cannot be deleted while it is configured or used.',
                ],
            ]);
        }

        $attribute->delete();

        return response()->noContent();
    }

    public function storeValue(Request $request, Attribute $attribute)
    {
        $this->ensureOptionAttribute($attribute);

        $value = $attribute->values()->create(
            $this->validatedValue($request, $attribute)
        );

        return response()->json(['data' => $value], 201);
    }

    public function updateValue(
        Request $request,
        Attribute $attribute,
        AttributeValue $value
    ) {
        $this->ensureValueBelongsToAttribute($attribute, $value);
        $this->ensureOptionAttribute($attribute);

        $value->update($this->validatedValue($request, $attribute, $value));

        return response()->json(['data' => $value->fresh()]);
    }

    public function destroyValue(Attribute $attribute, AttributeValue $value)
    {
        $this->ensureValueBelongsToAttribute($attribute, $value);

        if ($value->products()->exists() || $value->variants()->exists()) {
            throw ValidationException::withMessages([
                'value' => ['This value cannot be deleted while it is in use.'],
            ]);
        }

        $value->delete();

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function validatedAttribute(Request $request, ?Attribute $attribute = null): array
    {
        return $request->validate([
            'name' => [$attribute ? 'sometimes' : 'required', 'string', 'max:255'],
            'slug' => [
                $attribute ? 'sometimes' : 'required',
                'string',
                'max:255',
                Rule::unique('attributes', 'slug')->ignore($attribute?->id),
            ],
            'type' => [
                $attribute ? 'sometimes' : 'required',
                Rule::in(['select', 'multiselect', 'color', 'number', 'boolean', 'text']),
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
    }

    /** @return array<string, mixed> */
    private function validatedValue(
        Request $request,
        Attribute $attribute,
        ?AttributeValue $value = null
    ): array {
        return $request->validate([
            'label' => [$value ? 'sometimes' : 'required', 'string', 'max:255'],
            'value' => [
                $value ? 'sometimes' : 'required',
                'string',
                'max:255',
                Rule::unique('attribute_values', 'value')
                    ->where('attribute_id', $attribute->id)
                    ->ignore($value?->id),
            ],
            'hex_color' => [
                'sometimes',
                'nullable',
                'regex:/^#[0-9A-Fa-f]{6}$/',
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);
    }

    private function ensureOptionAttribute(Attribute $attribute): void
    {
        if (in_array($attribute->type, ['select', 'multiselect', 'color'], true)) {
            return;
        }

        throw ValidationException::withMessages([
            'attribute' => ['Only option-based attributes can have predefined values.'],
        ]);
    }

    private function ensureValueBelongsToAttribute(
        Attribute $attribute,
        AttributeValue $value
    ): void {
        abort_unless($value->attribute_id === $attribute->id, 404);
    }

    private function typeIsInUse(Attribute $attribute): bool
    {
        return $attribute->categories()->exists()
            || $attribute->customProductValues()->exists()
            || $attribute->values()
                ->where(function ($query) {
                    $query->whereHas('products')->orWhereHas('variants');
                })
                ->exists();
    }

    private function typeCannotChange(Attribute $attribute): bool
    {
        return $this->typeIsInUse($attribute)
            || $attribute->values()->exists();
    }
}
