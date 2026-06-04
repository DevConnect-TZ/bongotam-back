<?php

use App\Http\Controllers\ConnectionAccessController;
use App\Http\Controllers\ConnectionDashboardController;
use App\Http\Controllers\StatusController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StatusController::class, 'index'])->name('status.dashboard');
Route::get('/connection/login', [ConnectionAccessController::class, 'login'])->name('connection.login');
Route::post('/connection/login', [ConnectionAccessController::class, 'authenticate'])->name('connection.authenticate');
Route::post('/connection/login/verify', [ConnectionAccessController::class, 'verifyOtp'])->name('connection.verify');
Route::post('/connection/login/reset', [ConnectionAccessController::class, 'resetOtp'])->name('connection.reset');
Route::post('/connection/access', [ConnectionAccessController::class, 'access'])->name('connection.access');
Route::post('/connection/session', [ConnectionAccessController::class, 'session'])->name('connection.session');
Route::post('/connection/logout', [ConnectionAccessController::class, 'logout'])->name('connection.logout');
Route::post('/connection/gateway', [ConnectionDashboardController::class, 'switchGateway'])->name('connection.gateway');
Route::get('/connection', [ConnectionDashboardController::class, 'index'])->name('connection.dashboard');
