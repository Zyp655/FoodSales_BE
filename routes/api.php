<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\SellerProductController;
use App\Http\Controllers\DeliveryController;
use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\DeliveryMiddleware;
use App\Http\Controllers\DeliveryTicketController;
use App\Http\Controllers\SellerOrderController;
use App\Http\Controllers\ChatController; 

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'unifiedLogin']);
    Route::post('register/user', [AuthController::class, 'registerUser']);
    Route::post('register/seller', [AuthController::class, 'registerSeller']);
});

Route::prefix('gen')->group(function () {
    Route::get('search', [ProductController::class, 'combinedSearch']);
    Route::get('products/search', [ProductController::class, 'searchProducts']);
    Route::get('sellers', [SellerProductController::class, 'listSellers']);
    Route::get('categories', [ProductController::class, 'listCategories']);
});

Route::prefix('product')->group(function () {
    Route::get('{id}', [ProductController::class, 'getSingleProduct']);
});


Route::middleware('auth:sanctum')->group(function () {
    Route::post('auth/logout', [AuthController::class, 'logout']);
    Route::post('/user/request-delivery', [DeliveryTicketController::class, 'createTicket']);
    Route::get('/delivery/ticket/status', [DeliveryTicketController::class, 'getTicketStatus']);
    
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/seller/info', [AuthController::class, 'updateSellerInfo']);

    Route::put('/user/address', [AuthController::class, 'updateAddress']);
    Route::put('/user/profile', [AuthController::class, 'updateProfile']);
    Route::post('/user/password', [AuthController::class, 'changePassword']);


    Route::prefix('cart')->group(function () {
        Route::post('add', [CartController::class, 'addToCart']);
        Route::put('update/{cartitemId}', [CartController::class, 'updateCartItem']);
        Route::get('/', [CartController::class, 'getCart']);
        Route::delete('remove/{cartitemId}', [CartController::class, 'removeFromCart']);
    });

    Route::prefix('order')->group(function () {
        Route::post('create', [OrderController::class, 'createOrder']);
        Route::get('user', action: [OrderController::class, 'getOrdersByUser']);
        Route::put('{id}/status', [OrderController::class, 'updateOrderStatus']);
        Route::get('detail/{id}', [OrderController::class, 'getOrderDetails']);
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
        Route::get('/', [OrderController::class, 'getOrdersBySeller']);
        Route::put('{id}/status', [OrderController::class, 'updateSellerOrderStatus']);
        Route::get('analytics', [SellerOrderController::class, 'getAnalytics']);
    });
    
    Route::middleware(AdminMiddleware::class)->prefix('admin')->group(function () {
        Route::get('delivery-tickets', [DeliveryTicketController::class, 'getTickets']);
        Route::post('delivery-tickets/{ticketId}/status', [DeliveryTicketController::class, 'updateTicketStatus']);
        
        Route::get('orders', [AdminController::class, 'listAllOrders']);
        Route::get('sellers', [AdminController::class, 'listAllSellers']);
        Route::put('sellers/{id}/status', [AdminController::class, 'updateSellerStatus']);
        Route::get('users', [AdminController::class, 'listAllUsers']);
        Route::put('users/{id}/role', [AdminController::class, 'updateUserRole']);
        Route::get('products', [AdminController::class, 'listAllProducts']);
        Route::delete('products/{id}', [AdminController::class, 'destroyProduct']);
        Route::put('orders/{id}/assign-driver', [AdminController::class, 'assignDriver']);
        Route::get('accounts', [AdminController::class, 'getAllAccounts']);
        Route::delete('users/{id}', [AdminController::class, 'deleteUser']);
        Route::delete('sellers/{id}', [AdminController::class, 'deleteSeller']);

        Route::get('categories', [AdminController::class, 'adminListCategories']);
        Route::post('categories', [AdminController::class, 'adminCreateCategory']);
        Route::put('categories/{id}', [AdminController::class, 'adminUpdateCategory']);
        Route::delete('categories/{id}', [AdminController::class, 'adminDeleteCategory']);
        Route::get('fix-old-orders', [OrderController::class, 'fixMissingCommissions']);
    });

    Route::middleware(DeliveryMiddleware::class)->prefix('delivery')->group(function () {
        Route::get('stats', [DeliveryController::class, 'getDeliveryStats']);
        Route::get('assigned-orders', [DeliveryController::class, 'getAssignedOrders']);
        Route::put('orders/{id}/status', [DeliveryController::class, 'updateDeliveryStatus']);
        Route::get('available-orders', [DeliveryController::class, 'getAvailableOrders']);
        Route::post('orders/{id}/accept', [DeliveryController::class, 'acceptOrder']);
    });
    
    Route::post('chat/send', [ChatController::class, 'sendMessage']);
});