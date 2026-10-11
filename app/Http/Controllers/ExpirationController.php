<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductBatch;
use App\Services\ExpiryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExpirationController extends Controller
{
    public function index(Request $request)
    {
        $days = $request->input('days', '60');
        $window = $days === 'all' ? null : (in_array((int) $days, [15, 30, 60, 90], true) ? (int) $days : 60);

        $all = ExpiryService::batches();
        $rows = $window === null ? $all : $all->filter(fn ($r) => $r['days'] <= $window)->values();

        $summary = [
            'expired_count' => $all->where('status', 'expired')->count(),
            'expired_value' => (float) $all->where('status', 'expired')->sum('value'),
            'soon_count'    => $all->where('status', 'soon')->count(),
            'soon_value'    => (float) $all->where('status', 'soon')->sum('value'),
        ];

        $products = Product::orderBy('name')->get(['id', 'name', 'unit']);

        return view('expirations.index', compact('rows', 'summary', 'products'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity'   => 'required|numeric|gt:0|decimal:0,3',
            'expires_on' => 'required|date',
            'lot_code'   => 'nullable|string|max:100',
        ]);

        ProductBatch::create($data);

        return redirect()->route('expirations.index')->with('success', 'Vencimiento registrado.');
    }

    public function expire(ProductBatch $batch)
    {
        $row = ExpiryService::batches(null, $batch->product_id)
            ->first(fn ($r) => $r['batch']->id === $batch->id);

        if (!$row || $row['quantity'] <= 0) {
            return back()->with('error', 'Este lote no tiene unidades estimadas en stock.');
        }

        try {
            DB::transaction(function () use ($batch, $row) {
                $label = $batch->lot_code ? "lote {$batch->lot_code}" : 'lote sin código';

                $row['product']->registerMovement(
                    'out',
                    (float) $row['quantity'],
                    "Vencido: {$label} ({$batch->expires_on->format('d/m/Y')})",
                    'expired'
                );

                $batch->update(['quantity' => 0]);
            });
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Baja registrada como vencido. Ya cuenta en el reporte de mermas.');
    }

    public function destroy(ProductBatch $batch)
    {
        $batch->delete();

        return back()->with('success', 'Lote quitado del control de vencimientos.');
    }
}