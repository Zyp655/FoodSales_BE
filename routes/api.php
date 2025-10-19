<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UtilityController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\SellerProductController;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'unifiedLogin']);
    Route::post('register/user', [AuthController::class, 'registerUser']);
    Route::post('register/seller', [AuthController::class, 'registerSeller']);
});

Route::prefix('gen')->group(function () {
    Route::get('products/search', [ProductController::class, 'searchProducts']); 
    Route::get('sellers', [ProductController::class, 'listSellers']); 
    Route::get('distance', [UtilityController::class, 'getDistance']);
    Route::post('interaction/increment', [UtilityController::class, 'incrementInteraction']);
});

Route::prefix('product')->group(function () {
    Route::get('{id}', [ProductController::class, 'getSingleProduct']);
});


Route::middleware('auth:sanctum')->group(function () {
    
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::prefix('cart')->group(function () {
        Route::post('add', [CartController::class, 'addToCart']); 
        Route::put('update', [CartController::class, 'updateCartItem']); 
        Route::get('/', [CartController::class, 'getCart']); 
    });

    Route::prefix('order')->group(function () {
        Route::post('create', [OrderController::class, 'createOrder']); 
        Route::get('user', [OrderController::class, 'getUserOrders']); 
        Route::put('{id}/status', [OrderController::class, 'updateOrderStatus']);
    });
    
    Route::prefix('transaction')->group(function () {
        Route::post('generate-qr', [TransactionController::class, 'generateQrTransaction']);
        Route::put('{id}/status', [TransactionController::class, 'updateStatus']); 
    });
    
    Route::prefix('seller/product')->group(function () {
        Route::post('add', [SellerProductController::class, 'addProduct']); 
        Route::get('/', [SellerProductController::class, 'getSellerProducts']); 
        Route::put('{id}', [SellerProductController::class, 'updateProduct']); 
        Route::delete('{id}', [SellerProductController::class, 'deleteProduct']); 
    });
    
    Route::middleware('admin')->prefix('admin')->group(function () {
        Route::get('sellers', [AdminController::class, 'listAllSellers']);
        Route::put('sellers/{id}/status', [AdminController::class, 'updateSellerStatus']);
        Route::get('users', [AdminController::class, 'listAllUsers']);
        Route::put('users/{id}/role', [AdminController::class, 'updateUserRole']);
        Route::get('products', [AdminController::class, 'listAllProducts']);
        Route::delete('products/{id}', [AdminController::class, 'destroyProduct']);
    });
});
