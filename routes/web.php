<?php

use Illuminate\Support\Facades\Route;

Route::get('/admin', fn () => view('welcome'));

Route::get('/', fn () => view('welcome'));