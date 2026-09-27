<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MovementController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Lectura: disponible para los tres roles (superadmin, admin, personal)
    Route::get('/products/export', [ProductController::class, 'export'])->name('products.export');
    Route::resource('products', ProductController::class)->only(['index']);
    Route::resource('categories', CategoryController::class)->only(['index']);
    Route::resource('products.movements', StockMovementController::class)->only(['index']);
    Route::get('/movements/export', [MovementController::class, 'export'])->name('movements.export');
    Route::get('/movements', [MovementController::class, 'index'])->name('movements.index');

    // Escritura: solo superadmin y admin
    Route::middleware('can:modify-inventory')->group(function () {
        Route::resource('products', ProductController::class)->except(['index']);
        Route::resource('categories', CategoryController::class)->except(['index']);
        Route::resource('products.movements', StockMovementController::class)->only(['store']);
    });

    // Gestion de usuarios: solo superadmin
    Route::middleware('can:manage-users')->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';