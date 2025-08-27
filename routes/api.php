<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('login', [AuthController::class, 'login'])->name('login');

Route::match(['post'], 'software/migrate', [OrderController::class, 'runMigrationsAndSeeders']);
Route::middleware(['auth:api'])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::match(['post'], 'software/reset', [OrderController::class, 'resetDatabase']);

    // Create new order (transaction + async job)
    Route::match(['get', 'post'], 'orders', [OrderController::class, 'store']);
    // List all orders
    Route::match(['post', 'get'], 'order-list', [OrderController::class, 'index']);
    // Show single order with items
    Route::match(['post', 'get'], 'orders/{id}', [OrderController::class, 'show']);
});
