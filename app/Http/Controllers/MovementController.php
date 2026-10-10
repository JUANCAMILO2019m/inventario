<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class MovementController extends Controller
{
    public function index(Request $request)
    {
        $movements = $this->filtered($request)
            ->with(['product', 'user'])
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $users = Gate::allows('manage-users')
            ? User::orderBy('name')->get(['id', 'name'])
            : collect();

        return view('movements.global', compact('movements', 'users'));
    }

    public function export(Request $request)
    {
        $movements = $this->filtered($request)
            ->with(['product', 'user'])
            ->latest('id')
            ->get();

        $filename = 'movimientos_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($movements) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Fecha', 'Producto', 'Tipo', 'Motivo', 'Detalle', 'Cambio', 'Quedó en', 'Usuario']);

            foreach ($movements as $m) {
                fputcsv($handle, [
                    $m->created_at->format('d/m/Y H:i'),
                    $m->product?->name ?? 'Producto eliminado',
                    match ($m->type) { 'in' => 'Entrada', 'out' => 'Salida', default => 'Ajuste' },
                    $m->reason_type ? StockMovement::reasonLabel($m->reason_type) : '',
                    $m->reason ?? '',
                    $m->change,
                    $m->quantity_after,
                    $m->user?->name ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function filtered(Request $request): Builder
    {
        return StockMovement::query()
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->when($request->filled('reason_type'), fn ($q) => $q->where('reason_type', $request->input('reason_type')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->input('to')))
            ->when(
                $request->filled('user') && Gate::allows('manage-users'),
                fn ($q) => $q->where('user_id', $request->input('user'))
            )
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%' . $request->input('q') . '%';
                $q->whereHas('product', fn ($p) => $p->where('name', 'like', $term));
            });
    }
}