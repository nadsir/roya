<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

class ProductDetailController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $product = Product::query()
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
            ->where('is_active', true)
            ->find($id);

        if (! $product) {
            return response()->json(['message' => 'Product not found.'], 404);
        }

        return response()->json(new ProductResource($product));
    }
}
