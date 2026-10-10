<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function waste(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : now()->subDays(89)->startOfDay();

        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : now()->endOfDay();

        $outs = StockMovement::with(['product', 'user'])
            ->where('type', 'out')
            ->whereBetween('created_at', [$from, $to])
            ->latest('id')
            ->get();

        $waste = $outs->whereIn('reason_type', StockMovement::WASTE)->values();

        $wasteValue = (float) $waste->sum(fn ($m) => (float) $m->value());
        $outflowValue = (float) $outs->sum(fn ($m) => (float) $m->value());

        $byReason = $waste->groupBy('reason_type')
            ->map(fn ($group, $key) => [
                'label' => StockMovement::reasonLabel($key),
                'count' => $group->count(),
                'value' => (float) $group->sum(fn ($m) => (float) $m->value()),
            ])
            ->sortByDesc('value')
            ->values();

        $byProduct = $waste->groupBy('product_id')
            ->map(function ($group) {
                $product = $group->first()->product;

                return [
                    'product'  => $product,
                    'name'     => $product?->name ?? 'Producto eliminado',
                    'unit'     => $product?->unit,
                    'quantity' => round((float) $group->sum(fn ($m) => abs($m->change)), 3),
                    'value'    => (float) $group->sum(fn ($m) => (float) $m->value()),
                ];
            })
            ->sortByDesc('value')
            ->values();

        return view('reports.waste', [
            'from'         => $from->toDateString(),
            'to'           => $to->toDateString(),
            'waste'        => $waste,
            'wasteValue'   => $wasteValue,
            'pct'          => $outflowValue > 0 ? round($wasteValue / $outflowValue * 100, 1) : null,
            'byReason'     => $byReason,
            'byProduct'    => $byProduct,
            'details'      => $waste->take(50),
            'missingCost'  => $waste->filter(fn ($m) => $m->value() === null)->count(),
        ]);
    }
}