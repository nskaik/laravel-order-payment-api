<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the "RouteServiceProvider" and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Authentication routes (public)
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes
Route::middleware('jwt.auth')->group(function () {
    Route::get('/user', [UserController::class, 'show']);

    // Order routes
    Route::patch('/orders/{id}/confirm', [OrderController::class, 'confirm']);
    Route::patch('/orders/{id}/cancel', [OrderController::class, 'cancel']);
    Route::get('/orders/{id}/payment', [PaymentController::class, 'showForOrder']);
    Route::apiResource('orders', OrderController::class);

    // Payment routes
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/{id}', [PaymentController::class, 'show']);
    Route::post('/payments', [PaymentController::class, 'store']);
});

