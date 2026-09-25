<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\RestockController;
use App\Http\Controllers\StockOutController;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\WarehouseController;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout']);

Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return Inertia::render('Dashboard');
    });

    Route::get('/restock', function () {
        return Inertia::render('Restock');
    });

    Route::get('/stok-keluar', function () {
        return Inertia::render('StockOut');
    });

    Route::get('/produk', function () {
        return Inertia::render('Produk');
    });

    Route::get('/gudang', function () {
        return Inertia::render('Warehouse');
    });

    Route::prefix('api')->group(function () {
        Route::get('/restock', [RestockController::class, 'index']);
        Route::post('/restock', [RestockController::class, 'store']);
        Route::patch('/restock/{id}/status', [RestockController::class, 'updateStatus']);
        Route::delete('/restock/{id}', [RestockController::class, 'destroy']);
        Route::post('/restock/import-preview', [RestockController::class, 'importPreview']);
        Route::post('/restock/import-confirm', [RestockController::class, 'importConfirm']);
        Route::get('/restock/chart-stats', [RestockController::class, 'chartStats']);
        Route::get('/restock/produk-list', [RestockController::class, 'productNames']);
        Route::get('/warehouse', [WarehouseController::class, 'index']);

        Route::get('/stock-out', [StockOutController::class, 'index']);
        Route::post('/stock-out', [StockOutController::class, 'store']);
        Route::delete('/stock-out/{id}', [StockOutController::class, 'destroy']);
        Route::get('/stock-out/chart-stats', [StockOutController::class, 'chartStats']);
        Route::post('/stock-out/import', [StockOutController::class, 'import']);

        Route::get('/produk', [ProdukController::class, 'index']);
    });
});