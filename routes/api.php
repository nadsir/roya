<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CategoryFilterController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\AdminProductImageController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/categories', [CategoryController::class, 'index']);
Route::get(
    '/categories/{slug}/filters',
    [CategoryFilterController::class, 'index']
);
Route::get('/products', [ProductController::class, 'index']);
use App\Http\Controllers\Api\AdminProductController;
Route::get('/admin/products/meta', [AdminProductController::class, 'meta']);
Route::get('/admin/products/{product}', [AdminProductController::class, 'show']);
Route::post('/admin/products', [AdminProductController::class, 'store']);
Route::put('/admin/products/{product}', [AdminProductController::class, 'update']);
Route::delete('/admin/products/{product}', [AdminProductController::class, 'destroy']);
Route::post(
    '/admin/products/{product}/images',
    [AdminProductImageController::class, 'store']
);

Route::delete(
    '/admin/product-images/{image}',
    [AdminProductImageController::class, 'destroy']
);

Route::post(
    '/admin/products/{product}/images',
    [AdminProductImageController::class, 'store']
);

Route::patch(
    '/admin/products/{product}/images/{image}/primary',
    [AdminProductImageController::class, 'setPrimary']
);

Route::put(
    '/admin/products/{product}/images/reorder',
    [AdminProductImageController::class, 'reorder']
);

Route::delete(
    '/admin/products/{product}/images/{image}',
    [AdminProductImageController::class, 'destroy']
);