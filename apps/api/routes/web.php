<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'service' => 'clothing-commerce-api',
        'status' => 'online',
        'framework' => 'Laravel 13',
    ]);
});
