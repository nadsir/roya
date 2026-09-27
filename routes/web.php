<?php

use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;

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

Route::get('/products/{id}', fn () => view('welcome'))
    ->whereNumber('id');

Route::get('/articles', fn () => view('welcome'));

Route::get('/articles/{slug}', fn () => view('welcome'))
    ->where('slug', '[a-zA-Z0-9\-_]+');

Route::get('/c/{slug}', fn () => view('welcome'));

Route::get('/payment/callback/{order}', [PaymentController::class, 'callback'])
    ->whereNumber('order')
    ->name('payment.callback');
