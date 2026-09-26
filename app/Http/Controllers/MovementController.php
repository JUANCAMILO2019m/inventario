<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use Illuminate\Http\Request;

class MovementController extends Controller
{
    public function index(Request $request)
    {
        $movements = StockMovement::with('product')
            ->when($request->filled('type'), function ($query) use ($request) {
                $query->where('type', $request->type);
            })
            ->when($request->filled('from'), function ($query) use ($request) {
                $query->whereDate('created_at', '>=', $request->from);
            })
            ->when($request->filled('to'), function ($query) use ($request) {
                $query->whereDate('created_at', '<=', $request->to);
            })
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%' . $request->q . '%';
                $query->whereHas('product', function ($q) use ($term) {
                    $q->where('name', 'like', $term);
                });
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return view('movements.global', compact('movements'));
    }

    public function export(Request $request)
    {
        $movements = StockMovement::with('product')
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->to))
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%' . $request->q . '%';
                $query->whereHas('product', fn ($q) => $q->where('name', 'like', $term));
            })
            ->latest('id')
            ->get();

        $filename = 'movimientos_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($movements) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Fecha', 'Producto', 'Tipo', 'Cambio', 'Quedó en', 'Motivo']);

            foreach ($movements as $m) {
                fputcsv($handle, [
                    $m->created_at->format('d/m/Y H:i'),
                    $m->product?->name ?? 'Producto eliminado',
                    match ($m->type) { 'in' => 'Entrada', 'out' => 'Salida', default => 'Ajuste' },
                    $m->change,
                    $m->quantity_after,
                    $m->reason ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
