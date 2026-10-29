<?php

use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\CategoryPageController;
use App\Http\Controllers\LegacyUrlRedirectController;
use App\Http\Controllers\ProductPageController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', [SitemapController::class, 'index']);

// ---------------------------------------------------------------------------
// Server-rendered SEO storefront pages (semantic, slug-based canonical URLs).
// ---------------------------------------------------------------------------
Route::get('/category/{category:slug}', [CategoryPageController::class, 'show'])
    ->name('category.show');

Route::get('/product/{product:slug}', [ProductPageController::class, 'show'])
    ->name('product.show');

// ---------------------------------------------------------------------------
// Legacy URL structure: permanent 301 redirects to the canonical URLs above.
// ---------------------------------------------------------------------------
Route::get('/c/{slug}', [LegacyUrlRedirectController::class, 'redirectToCategory'])
    ->where('slug', '[^/]+');

Route::get('/products/{id}', [LegacyUrlRedirectController::class, 'redirectToProduct'])
    ->whereNumber('id');

Route::get('/admin', fn () => view('welcome'));

Route::get('/', fn () => view('welcome'));

Route::get('/store', fn () => view('welcome'));

Route::get('/cart', fn () => view('welcome'));

Route::get('/checkout', fn () => view('welcome'));

Route::get('/order-success', fn () => view('welcome'));

Route::get('/orders', fn () => view('welcome'));

Route::get('/orders/{id}', fn () => view('welcome'))->whereNumber('id');

Route::get('/wishlist', fn () => view('welcome'));

Route::get('/login', fn () => view('welcome'));

Route::get('/register', fn () => view('welcome'));

Route::get('/account', fn () => view('welcome'));

Route::get('/articles', fn () => view('welcome'));

Route::get('/articles/{slug}', fn () => view('welcome'))
    ->where('slug', '[a-zA-Z0-9\-_]+');

Route::get('/payment/callback/{order}', [PaymentController::class, 'callback'])
    ->whereNumber('order')
    ->name('payment.callback');
