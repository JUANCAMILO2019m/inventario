<?php

use App\Http\Controllers\AnimalController;
use App\Http\Controllers\AnimalFaenaController;
use App\Http\Controllers\AnimalRecordController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpirationController;
use App\Http\Controllers\MovementController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ReorderController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\StockCountController;
use App\Http\Controllers\PriceReferenceController;
use Illuminate\Support\Facades\Http;//añadido para la prueba de la api
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Escritura que debe registrarse ANTES que las rutas de lectura con comodín
    // (para que "animals/create" no choque con "animals/{animal}", ni "purchases/create" con "purchases/{purchase}")
    Route::middleware('can:modify-inventory')->group(function () {
        Route::resource('animals', AnimalController::class)->except(['index', 'show']);
        Route::post('/animals/{animal}/records', [AnimalRecordController::class, 'store'])->name('animals.records.store');
        Route::delete('/animals/{animal}/records/{record}', [AnimalRecordController::class, 'destroy'])->name('animals.records.destroy');
        Route::post('/animals/{animal}/records/{record}/payment', [AnimalRecordController::class, 'addPayment'])->name('animals.records.payment');
        Route::post('/animals/{animal}/faenas', [AnimalFaenaController::class, 'store'])->name('animals.faenas.store');
        Route::delete('/animals/{animal}/faenas/{batch}', [AnimalFaenaController::class, 'destroy'])->name('animals.faenas.destroy');
        Route::get('/counts/create', [StockCountController::class, 'create'])->name('counts.create');
        Route::post('/counts', [StockCountController::class, 'store'])->name('counts.store');
        Route::post('/counts/{count}/save', [StockCountController::class, 'save'])->name('counts.save');
        Route::get('/counts/{count}/review', [StockCountController::class, 'review'])->name('counts.review');
        Route::post('/counts/{count}/apply', [StockCountController::class, 'apply'])->name('counts.apply');
        Route::post('/counts/{count}/cancel', [StockCountController::class, 'cancel'])->name('counts.cancel');

        Route::resource('purchases', PurchaseController::class)->only(['create', 'store']);
        Route::post('/purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->name('purchases.cancel');
    });

    // Lectura: disponible para los tres roles
    Route::get('/products/export', [ProductController::class, 'export'])->name('products.export');
    Route::resource('products', ProductController::class)->only(['index']);
    Route::resource('categories', CategoryController::class)->only(['index']);
    Route::resource('products.movements', StockMovementController::class)->only(['index']);
    Route::get('/animals/export', [AnimalController::class, 'export'])->name('animals.export');
    Route::resource('animals', AnimalController::class)->only(['index', 'show']);
    Route::get('/movements/export', [MovementController::class, 'export'])->name('movements.export');
    Route::get('/movements', [MovementController::class, 'index'])->name('movements.index');
    Route::get('/reports/waste', [ReportController::class, 'waste'])->name('reports.waste');
    Route::resource('suppliers', SupplierController::class)->only(['index']);
    Route::resource('purchases', PurchaseController::class)->only(['index', 'show']);
    Route::get('/expirations', [ExpirationController::class, 'index'])->name('expirations.index');
    Route::get('/counts', [StockCountController::class, 'index'])->name('counts.index');
    Route::get('/counts/{count}/export', [StockCountController::class, 'export'])->name('counts.export');
    Route::get('/counts/{count}', [StockCountController::class, 'show'])->name('counts.show');
    Route::get('/reorder', [ReorderController::class, 'index'])->name('reorder.index');
    Route::get('/prices', [PriceReferenceController::class, 'index'])->name('prices.index');
    Route::get('/prices/{code}', [PriceReferenceController::class, 'show'])->whereNumber('code')->name('prices.show');
    Route::get('/expenses/export', [ExpenseController::class, 'export'])->name('expenses.export');

    Route::get('/diagnostico-sipsa/{token}', function ($token) {

        $expectedToken = env('SIPSA_DIAGNOSTIC_TOKEN');

        abort_unless(
            $expectedToken && hash_equals($expectedToken, $token),
            404
        );

        try {

            $response = Http::connectTimeout(15)
                ->timeout(60)
                ->get(
                    'https://appweb.dane.gov.co/sipsaWS/SrvSipsaUpraBeanService?WSDL'
                );

            return response()->json([
                'conexion' => 'establecida',
                'http_status' => $response->status(),
                'correcto' => $response->successful(),
                'content_type' => $response->header('Content-Type'),
                'bytes_recibidos' => strlen($response->body()),
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'conexion' => 'fallida',
                'error' => get_class($e),
                'mensaje' => $e->getMessage(),
            ], 502);
        }
    });

    Route::resource('expenses', ExpenseController::class)->only(['index']);


    // Escritura de productos, categorías, movimientos, proveedores y vencimientos
    Route::middleware('can:modify-inventory')->group(function () {
        Route::resource('products', ProductController::class)->except(['index']);
        Route::resource('categories', CategoryController::class)->except(['index']);
        Route::resource('products.movements', StockMovementController::class)->only(['store']);
        Route::resource('suppliers', SupplierController::class)->except(['index', 'show']);
        Route::resource('expenses', ExpenseController::class)->except(['index', 'show']);
        Route::post('/expirations', [ExpirationController::class, 'store'])->name('expirations.store');
        Route::post('/expirations/{batch}/expire', [ExpirationController::class, 'expire'])->name('expirations.expire');
        Route::delete('/expirations/{batch}', [ExpirationController::class, 'destroy'])->name('expirations.destroy');
    });

    // Usuarios y actividad: solo superadmin
    Route::middleware('can:manage-users')->group(function () {
        Route::resource('users', UserController::class)->except(['show']);
        Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';