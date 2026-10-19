<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockCount;
use App\Models\StockCountItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StockCountController extends Controller
{
    private const TOLERANCE = 0.0005;

    public function index()
    {
        $counts = StockCount::with(['user', 'category', 'closer'])
            ->withCount([
                'items',
                'items as counted_count' => fn ($q) => $q->whereNotNull('counted_qty'),
            ])
            ->latest('id')
            ->paginate(15);

        $open = StockCount::where('status', 'open')->first();
        $openCounted = $open ? $open->items()->whereNotNull('counted_qty')->count() : 0;
        $openTotal = $open ? $open->items()->count() : 0;

        return view('counts.index', compact('counts', 'open', 'openCounted', 'openTotal'));
    }

    public function create()
    {
        if ($open = StockCount::where('status', 'open')->first()) {
            return redirect()->route('counts.show', $open)
                ->with('error', 'Ya hay un conteo abierto. Termínalo o cancélalo antes de empezar otro.');
        }

        return view('counts.create', [
            'categories' => Category::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'notes'        => 'nullable|string|max:1000',
            'category_id'  => 'nullable|exists:categories,id',
            'blind'        => 'nullable|boolean',
            'include_zero' => 'nullable|boolean',
        ]);

        $count = null;

        try {
            DB::transaction(function () use ($request, $data, &$count) {
                if (StockCount::where('status', 'open')->lockForUpdate()->exists()) {
                    throw new \RuntimeException('Ya hay un conteo abierto. Termínalo o cancélalo antes de empezar otro.');
                }

                $products = Product::with('category')
                    ->when(!empty($data['category_id']), fn ($q) => $q->where('category_id', $data['category_id']))
                    ->when(!$request->boolean('include_zero'), fn ($q) => $q->where('quantity', '>', 0))
                    ->orderBy('name')
                    ->get();

                if ($products->isEmpty()) {
                    throw new \RuntimeException('No hay productos para contar con ese alcance.');
                }

                $count = StockCount::create([
                    'name'        => $data['name'],
                    'notes'       => $data['notes'] ?? null,
                    'category_id' => $data['category_id'] ?? null,
                    'blind'       => $request->boolean('blind'),
                    'status'      => 'open',
                ]);

                $now = now();

                $rows = $products->map(fn ($p) => [
                    'stock_count_id' => $count->id,
                    'product_id'     => $p->id,
                    'product_name'   => $p->name,
                    'category_name'  => $p->category?->name,
                    'unit'           => $p->unit,
                    'system_qty'     => (float) $p->quantity,
                    'unit_cost'      => $p->price !== null ? (float) $p->price : null,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ])->all();

                foreach (array_chunk($rows, 200) as $chunk) {
                    StockCountItem::insert($chunk);
                }
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('counts.show', $count)
            ->with('success', 'Conteo abierto. Anota lo que cuentes y guarda el avance cuando quieras.');
    }

    public function show(StockCount $count)
    {
        $count->load(['user', 'category', 'closer']);

        $items = $count->items()
            ->orderBy('category_name')
            ->orderBy('product_name')
            ->get();

        $counted = $items->whereNotNull('counted_qty')->count();

        $summary = null;

        if ($count->status === 'applied') {
            $adjusted = $items->filter(fn ($i) => $i->applied_change !== null && abs($i->applied_change) > self::TOLERANCE);
            $value = fn ($i) => $i->unit_cost !== null ? $i->applied_change * $i->unit_cost : 0.0;

            $summary = [
                'adjusted'     => $adjusted->count(),
                'short'        => (float) $adjusted->filter(fn ($i) => $i->applied_change < 0)->sum($value),
                'surplus'      => (float) $adjusted->filter(fn ($i) => $i->applied_change > 0)->sum($value),
                'skipped'      => $items->filter(fn ($i) => $i->state('applied') === 'skipped')->count(),
                'uncounted'    => $items->whereNull('counted_qty')->count(),
                'missing_cost' => $adjusted->whereNull('unit_cost')->count(),
            ];
        }

        return view('counts.show', compact('count', 'items', 'counted', 'summary'));
    }

    public function save(Request $request, StockCount $count)
    {
        if (!$count->isOpen()) {
            return redirect()->route('counts.show', $count)->with('error', 'Este conteo ya está cerrado.');
        }

        $data = $request->validate([
            'items'           => 'nullable|array',
            'items.*.counted' => 'nullable|numeric|min:0|max:999999999|decimal:0,3',
        ], [], ['items.*.counted' => 'cantidad contada']);

        $items = $count->items()->get()->keyBy('id');

        DB::transaction(function () use ($data, $items) {
            foreach (($data['items'] ?? []) as $id => $row) {
                $item = $items->get((int) $id);

                if (!$item) {
                    continue;
                }

                $raw = $row['counted'] ?? null;
                $value = ($raw === null || $raw === '') ? null : round((float) $raw, 3);

                $changed = ($item->counted_qty === null) !== ($value === null)
                    || ($value !== null && abs($item->counted_qty - $value) > 0.0004);

                if ($changed) {
                    $item->update(['counted_qty' => $value]);
                }
            }
        });

        if ($request->input('go') === 'review') {
            return redirect()->route('counts.review', $count);
        }

        return redirect()->route('counts.show', $count)->with('success', 'Avance guardado.');
    }

    public function review(StockCount $count)
    {
        if (!$count->isOpen()) {
            return redirect()->route('counts.show', $count)->with('error', 'Este conteo ya está cerrado.');
        }

        $items = $count->items()
            ->with('product')
            ->orderBy('category_name')
            ->orderBy('product_name')
            ->get();

        $rows = [];
        $same = collect();
        $uncounted = collect();
        $missing = collect();

        foreach ($items as $item) {
            if ($item->counted_qty === null) {
                $uncounted->push($item);
                continue;
            }

            $product = $item->product;

            if (!$product) {
                $missing->push($item);
                continue;
            }

            $current = (float) $product->quantity;
            $diff = round($item->counted_qty - $current, 3);

            if (abs($diff) < self::TOLERANCE) {
                $same->push($item);
                continue;
            }

            $cost = $item->unit_cost ?? ($product->price !== null ? (float) $product->price : null);

            $rows[] = [
                'item'    => $item,
                'product' => $product,
                'current' => $current,
                'diff'    => $diff,
                'cost'    => $cost,
                'value'   => $cost !== null ? round($diff * $cost, 2) : null,
                'changed' => abs($current - (float) $item->system_qty) > self::TOLERANCE,
            ];
        }

        $rows = collect($rows);

        return view('counts.review', [
            'count'        => $count,
            'rows'         => $rows,
            'same'         => $same,
            'uncounted'    => $uncounted,
            'missing'      => $missing,
            'shortValue'   => (float) $rows->filter(fn ($r) => $r['diff'] < 0)->sum('value'),
            'surplusValue' => (float) $rows->filter(fn ($r) => $r['diff'] > 0)->sum('value'),
            'missingCost'  => $rows->whereNull('value')->count(),
        ]);
    }

    public function apply(Request $request, StockCount $count)
    {
        $data = $request->validate([
            'include'   => 'nullable|array',
            'include.*' => 'integer',
        ]);

        $include = collect($data['include'] ?? [])->map(fn ($v) => (int) $v)->all();

        if (empty($include)) {
            return back()->with('error', 'Selecciona al menos una diferencia para aplicar.');
        }

        $applied = 0;

        try {
            DB::transaction(function () use ($count, $include, &$applied) {
                $locked = StockCount::lockForUpdate()->findOrFail($count->id);

                if (!$locked->isOpen()) {
                    throw new \RuntimeException('Este conteo ya está cerrado.');
                }

                $reason = 'Conteo físico: ' . Str::limit($locked->name, 200, '');

                $items = $locked->items()
                    ->whereNotNull('counted_qty')
                    ->with('product')
                    ->get();

                foreach ($items as $item) {
                    $product = $item->product;

                    if (!$product) {
                        continue;
                    }

                    $current = round((float) $product->fresh()->quantity, 3);
                    $diff = round($item->counted_qty - $current, 3);

                    if (abs($diff) < self::TOLERANCE) {
                        $item->update(['applied_change' => 0]);
                        continue;
                    }

                    if (!in_array($item->id, $include, true)) {
                        continue;
                    }

                    $movement = $product->registerMovement('adjust', (float) $item->counted_qty, $reason, 'count');

                    $item->update([
                        'applied_change'    => $movement->change,
                        'stock_movement_id' => $movement->id,
                    ]);

                    $applied++;
                }

                $locked->update([
                    'status'     => 'applied',
                    'applied_at' => now(),
                    'closed_by'  => auth()->id(),
                ]);
            });
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('counts.show', $count)
            ->with('success', "Conteo aplicado: {$applied} ajuste(s) registrados con el motivo Conteo físico.");
    }

    public function cancel(StockCount $count)
    {
        if (!$count->isOpen()) {
            return back()->with('error', 'Este conteo ya está cerrado.');
        }

        $count->update([
            'status'       => 'cancelled',
            'cancelled_at' => now(),
            'closed_by'    => auth()->id(),
        ]);

        return redirect()->route('counts.index')->with('success', 'Conteo cancelado: no se hizo ningún ajuste.');
    }

    public function export(StockCount $count)
    {
        $items = $count->items()->orderBy('category_name')->orderBy('product_name')->get();
        $filename = 'conteo_' . $count->id . '_' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($items, $count) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Producto', 'Categoría', 'Unidad', 'Sistema al iniciar', 'Contado',
                'Ajuste aplicado', 'Costo unitario', 'Valor del ajuste', 'Estado',
            ]);

            foreach ($items as $i) {
                $state = match ($i->state($count->status)) {
                    'uncounted' => 'Sin contar',
                    'adjusted'  => 'Ajustado',
                    'same'      => 'Sin diferencia',
                    'skipped'   => 'No aplicado',
                    default     => 'Pendiente',
                };

                $adjusted = $i->applied_change !== null && abs($i->applied_change) > self::TOLERANCE;

                fputcsv($handle, [
                    $i->product_name,
                    $i->category_name ?? '',
                    $i->unit ?? '',
                    $i->system_qty,
                    $i->counted_qty ?? '',
                    $adjusted ? $i->applied_change : '',
                    $i->unit_cost ?? '',
                    ($adjusted && $i->unit_cost !== null) ? round($i->applied_change * $i->unit_cost, 2) : '',
                    $state,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}