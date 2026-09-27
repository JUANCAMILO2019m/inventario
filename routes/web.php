<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MovementController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

/*Route::get('/debug-scheme', function (\Illuminate\Http\Request $request) {
    return response()->json([
        'isSecure' => $request->isSecure(),
        'scheme' => $request->getScheme(),
        'X-Forwarded-Proto' => $request->header('X-Forwarded-Proto'),
        'X-Forwarded-Ssl' => $request->header('X-Forwarded-Ssl'),
        'all_forwarded_headers' => collect($request->headers->all())
            ->filter(fn ($v, $k) => str_contains($k, 'forwarded'))
            ->all(),
    ]);
})->withoutMiddleware('web');*/

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/movements/export', [MovementController::class, 'export'])->name('movements.export');
    Route::get('/movements', [MovementController::class, 'index'])->name('movements.index');
    Route::get('/products/export', [ProductController::class, 'export'])->name('products.export');
    Route::resource('products', ProductController::class);
    Route::resource('categories', CategoryController::class)->except(['show']);
    Route::resource('products.movements', StockMovementController::class)->only(['index', 'store']);

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';