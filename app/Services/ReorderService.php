<?php

namespace App\Services;

use App\Models\Product;
use App\Models\PurchaseItem;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class ReorderService
{
    /** Días que tarda en llegar una compra, si el producto no define otro. */
    public const DEFAULT_LEAD_DAYS = 3;

    /** Margen sobre el tiempo de entrega para avisar "comprar pronto". */
    public const SAFETY_DAYS = 7;

    /** Salidas que cuentan como consumo (no incluye pérdidas, daños, vencidos ni correcciones). */
    private const CONSUMPTION_REASONS = ['animal_use', 'transfer', 'other_out'];

    private const SEVERITY = ['out' => 0, 'critical' => 1, 'soon' => 2, 'ok' => 3, 'idle' => 4];

    public static function analyze(
        int $windowDays = 30,
        int $coverDays = 30,
        ?int $categoryId = null,
        bool $includeIdle = false
    ): Collection {
        $now = now();
        $since = $now->copy()->subDays($windowDays);

        $products = Product::with('category')
            ->when($categoryId, fn ($q) => $q->where('category_id', $categoryId))
            ->orderBy('name')
            ->get();

        if ($products->isEmpty()) {
            return collect();
        }

        $ids = $products->pluck('id');

        // Primer movimiento de cada producto: evita subestimar el consumo de los productos nuevos
        $firstAt = StockMovement::whereIn('product_id', $ids)
            ->selectRaw('product_id, MIN(created_at) as first_at')
            ->groupBy('product_id')
            ->pluck('first_at', 'product_id');

        // Consumo neto del período: salidas de consumo menos reversos de registros eliminados
        $movements = StockMovement::whereIn('product_id', $ids)
            ->where('created_at', '>=', $since)
            ->where(function ($q) {
                $q->where(function ($o) {
                    $o->where('type', 'out')->where(function ($r) {
                        $r->whereNull('reason_type')->orWhereIn('reason_type', self::CONSUMPTION_REASONS);
                    });
                })->orWhere(function ($i) {
                    $i->where('type', 'in')->where('reason_type', 'reversal');
                });
            })
            ->get(['product_id', 'type', 'change'])
            ->groupBy('product_id');

        // Última compra vigente de cada producto: costo de referencia y proveedor habitual
        $lastIds = PurchaseItem::whereIn('product_id', $ids)
            ->whereHas('purchase', fn ($q) => $q->whereNull('voided_at'))
            ->selectRaw('product_id, MAX(id) as last_id')
            ->groupBy('product_id')
            ->pluck('last_id');

        $lastPurchases = PurchaseItem::with('purchase.supplier')
            ->whereIn('id', $lastIds)
            ->get()
            ->keyBy('product_id');

        $expiry = ExpiryService::batches()->groupBy(fn ($r) => $r['product']->id);

        $rows = $products->map(function (Product $product) use (
            $now, $windowDays, $coverDays, $firstAt, $movements, $lastPurchases, $expiry
        ) {
            $stock = max(0.0, (float) $product->quantity);
            $lead = $product->lead_days ?? self::DEFAULT_LEAD_DAYS;

            $group = $movements->get($product->id, collect());
            $outs = $group->where('type', 'out');
            $consumed = max(
                0.0,
                (float) $outs->sum(fn ($m) => abs($m->change)) - (float) $group->where('type', 'in')->sum('change')
            );

            $first = $firstAt->get($product->id);
            $ageDays = $first
                ? max(1, (int) ceil(($now->timestamp - Carbon::parse($first)->timestamp) / 86400))
                : 1;
            $observed = max(1, min($windowDays, $ageDays));
            $rate = $consumed / $observed;

            $last = $lastPurchases->get($product->id);
            $lastCost = $last
                ? (float) $last->unit_cost
                : ($product->price !== null ? (float) $product->price : null);
            $supplier = $last?->purchase?->supplier
                ? ['id' => $last->purchase->supplier->id, 'name' => $last->purchase->supplier->name]
                : null;

            $base = [
                'product'       => $product,
                'stock'         => $stock,
                'usable'        => $stock,
                'expired_qty'   => 0.0,
                'waste_total'   => 0.0,
                'rate'          => $rate,
                'observed_days' => $observed,
                'outs_count'    => $outs->count(),
                'lead'          => $lead,
                'confidence'    => ($outs->count() < 3 || $observed < 7) ? 'low' : 'ok',
                'last_cost'     => $lastCost,
                'supplier'      => $supplier,
                'days_cover'    => null,
                'order_in_days' => null,
                'status'        => 'idle',
                'suggest'       => null,
                'est_cost'      => null,
            ];

            if ($rate <= 0) {
                return $base;
            }

            // Vencimientos: lo vencido no sirve, y lo que vencerá antes de usarse tampoco
            $horizon = $lead + $coverDays;
            $expiredQty = 0.0;
            $wasteTotal = 0.0;
            $wasteWithin = 0.0;

            $batches = $expiry->get($product->id);

            if ($batches) {
                $consumedSoFar = 0.0;

                foreach ($batches->sortBy('days') as $b) {
                    $qty = (float) $b['quantity'];

                    if ($b['days'] < 0) {
                        $expiredQty += $qty;
                        continue;
                    }

                    // Se consume primero lo que vence antes
                    $available = max(0.0, $rate * $b['days'] - $consumedSoFar);
                    $used = min($qty, $available);
                    $consumedSoFar += $used;

                    $waste = $qty - $used;
                    $wasteTotal += $waste;

                    if ($b['days'] <= $horizon) {
                        $wasteWithin += $waste;
                    }
                }
            }

            $usable = max(0.0, $stock - $expiredQty);
            $daysCover = $usable > 0 ? $usable / $rate : 0.0;

            $status = $usable <= 0 ? 'out'
                : ($daysCover <= $lead ? 'critical'
                : ($daysCover <= $lead + self::SAFETY_DAYS ? 'soon' : 'ok'));

            // Cuánto falta para cubrir el tiempo de entrega más los días elegidos
            $target = $rate * $horizon;
            $needed = max(0.0, $target - max(0.0, $usable - $wasteWithin));

            $suggest = null;

            if ($needed > 0.0005) {
                $size = (float) $product->package_size;

                if ($product->package_name && $size > 0) {
                    $packages = max(1, (int) ceil($needed / $size - 1e-9));
                    $suggest = ['mode' => 'package', 'packages' => $packages, 'qty' => round($packages * $size, 3)];
                } else {
                    $suggest = ['mode' => 'unit', 'packages' => null, 'qty' => (float) max(1, ceil($needed - 1e-9))];
                }
            }

            return array_merge($base, [
                'usable'        => $usable,
                'expired_qty'   => $expiredQty,
                'waste_total'   => $wasteTotal,
                'days_cover'    => $daysCover,
                'order_in_days' => $daysCover - $lead,
                'status'        => $status,
                'suggest'       => $suggest,
                'est_cost'      => ($suggest && $lastCost !== null) ? round($suggest['qty'] * $lastCost, 2) : null,
            ]);
        });

        return $rows
            ->when(!$includeIdle, fn ($c) => $c->where('status', '!=', 'idle'))
            ->sortBy([
                fn ($a, $b) => self::SEVERITY[$a['status']] <=> self::SEVERITY[$b['status']],
                fn ($a, $b) => ($a['days_cover'] ?? INF) <=> ($b['days_cover'] ?? INF),
            ])
            ->values();
    }
}