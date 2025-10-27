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
use App\Http\Controllers\DeliveryController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\DeliveryMiddleware;
use App\Http\Controllers\SellerOrderController;

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'unifiedLogin']);
    Route::post('register/user', [AuthController::class, 'registerUser']);
    Route::post('register/seller', [AuthController::class, 'registerSeller']);
});

Route::prefix('gen')->group(function () {
    Route::get('search', [ProductController::class, 'combinedSearch']);
    Route::get('products/search', [ProductController::class, 'searchProducts']);
    Route::get('sellers', [SellerProductController::class, 'listSellers']);
    Route::get('distance', [UtilityController::class, 'getDistance']);
    Route::post('interaction/increment', [UtilityController::class, 'incrementInteraction']);
    Route::get('categories', [ProductController::class, 'listCategories']);
});

Route::prefix('product')->group(function () {
    Route::get('{id}', [ProductController::class, 'getSingleProduct']);
});


Route::middleware('auth:sanctum')->group(function () {

    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::put('/user/address', [AuthController::class, 'updateAddress']);
    Route::put('/user/contact', [AuthController::class, 'updateContact']); 
    Route::post('/user/password', [AuthController::class, 'changePassword']); 


    Route::prefix('cart')->group(function () {
        Route::post('add', [CartController::class, 'addToCart']);
        Route::put('update/{cartitemId}', [CartController::class, 'updateCartItem']);
        Route::get('/', [CartController::class, 'getCart']);
    });

    Route::prefix('order')->group(function () {
        Route::post('create', [OrderController::class, 'createOrder']);
        Route::get('user', [OrderController::class, 'getOrdersByUser']);
        Route::put('{id}/status', [OrderController::class, 'updateOrderStatus']);
    });

    Route::prefix('transaction')->group(function () {
        Route::post('generate-qr', [TransactionController::class, 'generateQrTransaction']);
        Route::put('{id}/status', [TransactionController::class, 'updateStatus']);
    });

    Route::prefix('seller/product')->group(function () {
        Route::post('add', [SellerProductController::class, 'addProduct']);
        Route::get('/', [SellerProductController::class, 'getProductsBySeller']);
        Route::put('{id}', [SellerProductController::class, 'updateProduct']);
        Route::delete('{id}', [SellerProductController::class, 'deleteProduct']);
    });

    Route::prefix('seller/orders')->group(function () {
        Route::get('/', [SellerOrderController::class, 'index']);
        Route::put('{id}/status', [SellerOrderController::class, 'updateStatus']);
    });

    Route::middleware(AdminMiddleware::class)->prefix('admin')->group(function () {
        Route::get('sellers', [AdminController::class, 'listAllSellers']);
        Route::put('sellers/{id}/status', [AdminController::class, 'updateSellerStatus']);
        Route::get('users', [AdminController::class, 'listAllUsers']);
        Route::put('users/{id}/role', [AdminController::class, 'updateUserRole']);
        Route::get('products', [AdminController::class, 'listAllProducts']);
        Route::delete('products/{id}', [AdminController::class, 'destroyProduct']);
        Route::put('orders/{id}/assign-driver', [AdminController::class, 'assignDriver']);
    });

    Route::middleware(DeliveryMiddleware::class)->prefix('delivery')->group(function () {
        Route::get('orders', [DeliveryController::class, 'getAssignedOrders']);
        Route::put('orders/{id}/status', [DeliveryController::class, 'updateDeliveryStatus']);
    });
});