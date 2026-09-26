<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
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

        return view('dashboard', compact(
            'totalProducts',
            'totalValue',
            'lowStock',
            'lowStockCount',
            'totalCategories',
            'recentMovements'
        ));
    }
}