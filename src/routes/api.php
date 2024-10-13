<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;

Route::post('/login', [AuthController::class, 'login']);

Route::group(
    [
        'middleware' => ['throttle:api', 'auth:sanctum'],
        'prefix' => 'v1',
        'as' => 'api',
    ],
    function () {

        // --- Orders --- //
        Route::get('/product/{id}', [ProductController::class, 'show']);
        Route::post('/product', [ProductController::class, 'store']);
        Route::put('/product/{id}', [ProductController::class, 'update']);
        Route::delete('/product/{id}', [ProductController::class, 'destroy']);
    }
);