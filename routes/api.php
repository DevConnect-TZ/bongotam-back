<?php

use App\Http\Controllers\Api\AuthSessionController;
use App\Http\Controllers\Api\FrontendSecurityController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PaymentGatewayController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\TransactionController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VideoController;
use App\Http\Controllers\StatusController;
use Illuminate\Support\Facades\Route;

Route::get('/uptime', [StatusController::class, 'uptime'])->name('api.uptime');

// Auth
Route::post('/auth/session', [AuthSessionController::class, 'store']);
Route::post('/auth/refresh', [AuthSessionController::class, 'refresh']);
Route::post('/auth/logout', [AuthSessionController::class, 'destroy']);

// Frontend security
Route::get('/frontend/security', [FrontendSecurityController::class, 'show']);

// Uploads
// Videos
Route::get('/videos', [VideoController::class, 'index']);
Route::get('/videos/{id}', [VideoController::class, 'show']);
Route::post('/videos/{id}/views', [VideoController::class, 'incrementViews']);
Route::post('/payments/sonicpesa/webhook', [PaymentController::class, 'webhook']);

Route::middleware('api.auth')->group(function (): void {
    Route::get('/users/lookup', [UserController::class, 'lookup']);
    Route::post('/users', [UserController::class, 'storeOrUpdate']);

    Route::get('/transactions', [TransactionController::class, 'index']);

    Route::post('/payments/sonicpesa/order', [PaymentController::class, 'createOrder']);
    Route::get('/payments/sonicpesa/orders/{orderId}', [PaymentController::class, 'status']);
    Route::get('/payments/gateway', [PaymentGatewayController::class, 'show']);

    Route::post('/videos/{id}/stream', [VideoController::class, 'stream']);

    // Wakubwa Zone subscription
    Route::get('/subscription/wakubwa/status', [SubscriptionController::class, 'status']);
    Route::post('/subscription/wakubwa/subscribe', [SubscriptionController::class, 'subscribe']);
    Route::get('/subscription/wakubwa/price', [SubscriptionController::class, 'price']);
});

Route::middleware(['api.auth', 'api.admin'])->group(function (): void {
    Route::put('/frontend/security', [FrontendSecurityController::class, 'update']);

    // Uploads
    Route::post('/uploads/image', [UploadController::class, 'uploadImage']);
    Route::post('/uploads/video', [UploadController::class, 'uploadVideo']);

    // Videos
    Route::get('/videos/{id}/manage', [VideoController::class, 'manage']);
    Route::post('/videos', [VideoController::class, 'store']);
    Route::put('/videos/{id}', [VideoController::class, 'update']);
    Route::delete('/videos/{id}', [VideoController::class, 'destroy']);

    // Users
    Route::get('/users', [UserController::class, 'index']);
    Route::put('/users/{id}/role', [UserController::class, 'updateRole']);
    Route::put('/users/{id}/status', [UserController::class, 'updateStatus']);
    Route::post('/users/{id}/unlock-video', [UserController::class, 'unlockVideo']);

    // Transactions
    Route::post('/transactions', [TransactionController::class, 'store']);

    // Subscription price (admin only)
    Route::put('/subscription/wakubwa/price', [SubscriptionController::class, 'price']);

    // Payment gateway switching (admin only)
    Route::put('/payments/gateway', [PaymentGatewayController::class, 'update']);
});
