<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\EffectiveCategoryFilterController;
use App\Http\Controllers\Api\AdminCategoryController;
use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AdminAttributeController;
use App\Http\Controllers\Api\AdminProductController;
use App\Http\Controllers\Api\AdminArticleController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\AdminVehicleController;
use App\Http\Controllers\Api\VehicleController;
use App\Http\Controllers\Api\FilteredProductController;
use App\Http\Controllers\Api\ProductDetailController;
use App\Http\Controllers\Api\AdminProductImageController;
use App\Http\Controllers\Api\CustomerAuthController;
use App\Http\Controllers\Api\CustomerCheckoutController;
use App\Http\Controllers\Api\CustomerOrderController;
use App\Http\Controllers\Api\CustomerWishlistController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AdminOrderController;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\ProductCommentController;
use App\Http\Controllers\Api\ArticleCommentController;
use App\Http\Controllers\Api\CommentReplyController;
use App\Http\Controllers\Api\AdminCommentController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// ── Customer Auth ──────────────────────────────────────────
Route::post('/customer/register', [CustomerAuthController::class, 'register'])
    ->middleware('throttle:10,1');
Route::post('/customer/login', [CustomerAuthController::class, 'login'])
    ->middleware('throttle:10,1');
Route::post('/customer/send-otp', [CustomerAuthController::class, 'sendOtp'])
    ->middleware('throttle:10,1');
Route::post('/customer/verify-otp', [CustomerAuthController::class, 'verifyOtp'])
    ->middleware('throttle:30,1');
Route::middleware('auth:sanctum')->prefix('customer')->group(function () {
    Route::get('/orders', [CustomerOrderController::class, 'index']);
    Route::get('/orders/{order}', [CustomerOrderController::class, 'show'])->whereNumber('order');
    Route::patch('/orders/{order}/cancel', [CustomerOrderController::class, 'cancel'])->whereNumber('order');
    Route::post('/checkout', [CustomerCheckoutController::class, 'store']);
    Route::get('/me', [CustomerAuthController::class, 'me']);
    Route::post('/logout', [CustomerAuthController::class, 'logout']);
    Route::put('/profile', [CustomerAuthController::class, 'updateProfile']);
    Route::get('/wishlist', [CustomerWishlistController::class, 'index']);
    Route::post('/wishlist', [CustomerWishlistController::class, 'store']);
    Route::delete('/wishlist/{product}', [CustomerWishlistController::class, 'destroy'])->whereNumber('product');
    Route::get('/wishlist/check/{product}', [CustomerWishlistController::class, 'check'])->whereNumber('product');
    Route::post('/orders/{order}/pay', [PaymentController::class, 'initiate'])->whereNumber('order');
    Route::get('/orders/{order}/payment-status', [PaymentController::class, 'status'])->whereNumber('order');
});

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/tree', [CategoryController::class, 'index']);
Route::get(
    '/categories/{slug}/filters',
    [EffectiveCategoryFilterController::class, 'index']
);
Route::get('/categories/{slug}', [CategoryController::class, 'show']);

// ── Public Articles ──────────────────────────────────────────
Route::get('/articles', [ArticleController::class, 'index']);
Route::get('/articles/{slug}', [ArticleController::class, 'show']);
Route::get('/products', [FilteredProductController::class, 'index']);
Route::get('/products/{id}', [ProductDetailController::class, 'show'])
    ->whereNumber('id');

// ── Public Comments ──────────────────────────────────────────
Route::get('/products/{product}/comments', [ProductCommentController::class, 'index']);
Route::get('/articles/{article}/comments', [ArticleCommentController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/products/{product}/comments', [ProductCommentController::class, 'store']);
    Route::post('/articles/{article}/comments', [ArticleCommentController::class, 'store']);
    Route::post('/comments/{comment}/replies', [CommentReplyController::class, 'store']);
});

// ── Public vehicle hierarchy ────────────────────────────────────
Route::get('/vehicles/brands', [VehicleController::class, 'indexBrands']);
Route::get('/vehicles/brands/{brand}', [VehicleController::class, 'showBrand']);
Route::get('/vehicles/brands/{brand}/models', [VehicleController::class, 'indexModels']);
Route::get('/vehicles/models/{model}/generations', [VehicleController::class, 'indexGenerations']);
Route::get('/vehicles/generations/{generation}/trims', [VehicleController::class, 'indexTrims']);
Route::get('/vehicles/trims/{trim}/engines', [VehicleController::class, 'indexEngines']);
Route::post('/admin/login', [AdminAuthController::class, 'login'])
    ->middleware('throttle:5,1');
Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->group(function () {
    Route::get('/me', [AdminAuthController::class, 'me']);
    Route::post('/logout', [AdminAuthController::class, 'logout']);
    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->whereNumber('order');
    Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->whereNumber('order');
    Route::get('/categories', [AdminCategoryController::class, 'index']);
    Route::get('/categories/search', [AdminCategoryController::class, 'search']);
    Route::post('/categories', [AdminCategoryController::class, 'store']);
    Route::get('/categories/{category}', [AdminCategoryController::class, 'show']);
    Route::put('/categories/{category}', [AdminCategoryController::class, 'update']);
    Route::get('/categories/{category}/attributes', [AdminCategoryController::class, 'attributes']);
    Route::put('/categories/{category}/attributes', [AdminCategoryController::class, 'attributes']);
    Route::delete('/categories/{category}', [AdminCategoryController::class, 'destroy']);
    Route::get('/attributes', [AdminAttributeController::class, 'index']);
    Route::post('/attributes', [AdminAttributeController::class, 'store']);
    Route::put('/attributes/{attribute}', [AdminAttributeController::class, 'update']);
    Route::delete('/attributes/{attribute}', [AdminAttributeController::class, 'destroy']);
    Route::post('/attributes/{attribute}/values', [AdminAttributeController::class, 'storeValue']);
    Route::put('/attributes/{attribute}/values/{value}', [AdminAttributeController::class, 'updateValue']);
    Route::delete('/attributes/{attribute}/values/{value}', [AdminAttributeController::class, 'destroyValue']);
    Route::get('/products', [AdminProductController::class, 'index']);
    Route::get('/products/meta', [AdminProductController::class, 'meta']);
    Route::get('/products/{product}', [AdminProductController::class, 'show']);
    Route::post('/products', [AdminProductController::class, 'store']);
    Route::put('/products/{product}', [AdminProductController::class, 'update']);
    Route::delete('/products/{product}', [AdminProductController::class, 'destroy']);
    Route::post(
        '/products/{product}/images',
        [AdminProductImageController::class, 'store']
    );
    Route::delete(
        '/product-images/{image}',
        [AdminProductImageController::class, 'destroy']
    );
    Route::patch(
        '/products/{product}/images/{image}/primary',
        [AdminProductImageController::class, 'setPrimary']
    );
    Route::put(
        '/products/{product}/images/reorder',
        [AdminProductImageController::class, 'reorder']
    );
    Route::delete(
        '/products/{product}/images/{image}',
        [AdminProductImageController::class, 'destroy']
    );

    // ── Articles ──────────────────────────────────────────────────────
    Route::get('/articles', [AdminArticleController::class, 'index']);
    Route::post('/articles', [AdminArticleController::class, 'store']);
    Route::get('/articles/{article}', [AdminArticleController::class, 'show']);
    Route::put('/articles/{article}', [AdminArticleController::class, 'update']);
    Route::delete('/articles/{article}', [AdminArticleController::class, 'destroy']);

    // ── Users ───────────────────────────────────────────────────────
    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->whereNumber('user');
    Route::patch('/users/{user}/status', [AdminUserController::class, 'updateStatus'])->whereNumber('user');

    // ── Vehicle hierarchy ──────────────────────────────────────────
    Route::get('/vehicles/brands', [AdminVehicleController::class, 'indexBrands']);
    Route::post('/vehicles/brands', [AdminVehicleController::class, 'storeBrand']);
    Route::get('/vehicles/brands/{brand}', [AdminVehicleController::class, 'showBrand']);
    Route::put('/vehicles/brands/{brand}', [AdminVehicleController::class, 'updateBrand']);
    Route::delete('/vehicles/brands/{brand}', [AdminVehicleController::class, 'destroyBrand']);

    Route::get('/vehicles/brands/{brand}/models', [AdminVehicleController::class, 'indexModels']);
    Route::post('/vehicles/brands/{brand}/models', [AdminVehicleController::class, 'storeModel']);
    Route::get('/vehicles/brands/{brand}/models/{model}', [AdminVehicleController::class, 'showModel']);
    Route::put('/vehicles/brands/{brand}/models/{model}', [AdminVehicleController::class, 'updateModel']);
    Route::delete('/vehicles/brands/{brand}/models/{model}', [AdminVehicleController::class, 'destroyModel']);

    Route::get('/vehicles/brands/{brand}/models/{model}/generations', [AdminVehicleController::class, 'indexGenerations']);
    Route::post('/vehicles/brands/{brand}/models/{model}/generations', [AdminVehicleController::class, 'storeGeneration']);
    Route::get('/vehicles/brands/{brand}/models/{model}/generations/{generation}', [AdminVehicleController::class, 'showGeneration']);
    Route::put('/vehicles/brands/{brand}/models/{model}/generations/{generation}', [AdminVehicleController::class, 'updateGeneration']);
    Route::delete('/vehicles/brands/{brand}/models/{model}/generations/{generation}', [AdminVehicleController::class, 'destroyGeneration']);

    Route::get('/vehicles/brands/{brand}/models/{model}/generations/{generation}/trims', [AdminVehicleController::class, 'indexTrims']);
    Route::post('/vehicles/brands/{brand}/models/{model}/generations/{generation}/trims', [AdminVehicleController::class, 'storeTrim']);
    Route::get('/vehicles/brands/{brand}/models/{model}/generations/{generation}/trims/{trim}', [AdminVehicleController::class, 'showTrim']);
    Route::put('/vehicles/brands/{brand}/models/{model}/generations/{generation}/trims/{trim}', [AdminVehicleController::class, 'updateTrim']);
    Route::delete('/vehicles/brands/{brand}/models/{model}/generations/{generation}/trims/{trim}', [AdminVehicleController::class, 'destroyTrim']);

    Route::get('/vehicles/engines', [AdminVehicleController::class, 'indexAllEngines']);

    Route::get('/vehicles/brands/{brand}/models/{model}/generations/{generation}/trims/{trim}/engines', [AdminVehicleController::class, 'indexEngines']);
    Route::post('/vehicles/brands/{brand}/models/{model}/generations/{generation}/trims/{trim}/engines', [AdminVehicleController::class, 'storeEngine']);
    Route::get('/vehicles/brands/{brand}/models/{model}/generations/{generation}/trims/{trim}/engines/{engine}', [AdminVehicleController::class, 'showEngine']);
    Route::put('/vehicles/brands/{brand}/models/{model}/generations/{generation}/trims/{trim}/engines/{engine}', [AdminVehicleController::class, 'updateEngine']);
    Route::delete('/vehicles/brands/{brand}/models/{model}/generations/{generation}/trims/{trim}/engines/{engine}', [AdminVehicleController::class, 'destroyEngine']);

    // ── Product ↔ Vehicle compatibility ───────────────────────────
    Route::get('/products/{product}/vehicle-compat', [AdminVehicleController::class, 'indexCompatibility']);
    Route::post('/products/{product}/vehicle-compat', [AdminVehicleController::class, 'attachCompatibility']);
    Route::delete('/products/{product}/vehicle-compat/{engine}', [AdminVehicleController::class, 'detachCompatibility']);

    // ── Comments ──────────────────────────────────────────────────
    Route::get('/comments', [AdminCommentController::class, 'index']);
    Route::patch('/comments/{comment}/status', [AdminCommentController::class, 'updateStatus']);
    Route::delete('/comments/{comment}', [AdminCommentController::class, 'destroy']);
});
