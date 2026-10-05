<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Animal;
use App\Models\AnimalRecord;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $totalProducts = Product::count();

        $totalValue = Product::whereNotNull('price')
            ->sum(DB::raw('price * quantity'));

        $lowStock = Product::whereColumn('quantity', '<=', 'min_stock')
            ->orderBy('quantity')
            ->limit(5)
            ->get();

        $lowStockCount = Product::whereColumn('quantity', '<=', 'min_stock')->count();

        $totalCategories = \App\Models\Category::count();

        $recentMovements = StockMovement::with('product')
            ->latest('id')
            ->limit(8)
            ->get();

        $activeAnimals = Animal::where('status', 'active')->sum('quantity');

        $upcomingDue = AnimalRecord::with('animal')
            ->whereNotNull('next_due_date')
            ->where('next_due_date', '<=', now()->addDays(30))
            ->whereHas('animal', fn ($q) => $q->where('status', 'active'))
            ->whereNotExists(function ($q) {
                // Si ya hay un registro posterior del mismo animal, tipo y título,
                // el control se considera realizado y no se muestra la alerta.
                $q->select(DB::raw(1))
                    ->from('animal_records as newer')
                    ->whereColumn('newer.animal_id', 'animal_records.animal_id')
                    ->whereColumn('newer.type', 'animal_records.type')
                    ->whereColumn('newer.title', 'animal_records.title')
                    ->whereColumn('newer.id', '>', 'animal_records.id');
            })
            ->orderBy('next_due_date')
            ->limit(8)
            ->get();
        $receivables = AnimalRecord::with('animal')
            ->where('type', 'sale')
            ->whereIn('payment_status', ['pending', 'partial'])
            ->orderBy('recorded_at')
            ->get();

        $receivableTotal = $receivables->sum(fn ($r) => (float) $r->amount - (float) $r->amount_paid);

        return view('dashboard', compact(
            'totalProducts',
            'totalValue',
            'lowStock',
            'lowStockCount',
            'totalCategories',
            'recentMovements',
            'activeAnimals',
            'upcomingDue',
            'receivables',
            'receivableTotal'
        ));
    }
}