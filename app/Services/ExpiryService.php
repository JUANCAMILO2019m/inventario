<?php

namespace App\Services;

use App\Models\ProductBatch;
use Illuminate\Support\Collection;

class ExpiryService
{
    public const SOON_DAYS = 30;

    /**
     * Lotes con vencimiento y la cantidad estimada que sigue en stock.
     *
     * Es una estimación: se asume que lo que vence primero se consume primero. El stock actual
     * del producto se asigna a los lotes que vencen más tarde, y las unidades que "faltan"
     * se descuentan de los lotes más próximos a vencer.
     */
    public static function batches(?int $withinDays = null, ?int $productId = null): Collection
    {
        $rows = collect();

        ProductBatch::with(['product', 'item'])
            ->where('quantity', '>', 0)
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->get()
            ->groupBy('product_id')
            ->each(function ($group) use (&$rows) {
                $product = $group->first()->product;

                if (!$product) {
                    return;
                }

                $remaining = max(0.0, (float) $product->quantity);

                foreach ($group->sortBy([['expires_on', 'desc'], ['id', 'desc']]) as $batch) {
                    $allocated = round(min((float) $batch->quantity, $remaining), 3);
                    $remaining = round($remaining - $allocated, 3);

                    if ($allocated <= 0) {
                        continue;
                    }

                    $days = (int) round(($batch->expires_on->timestamp - today()->timestamp) / 86400);

                    $rows->push([
                        'batch'    => $batch,
                        'product'  => $product,
                        'quantity' => $allocated,
                        'days'     => $days,
                        'status'   => $days < 0 ? 'expired' : ($days <= self::SOON_DAYS ? 'soon' : 'ok'),
                        'value'    => $product->price !== null ? round($allocated * (float) $product->price, 2) : null,
                    ]);
                }
            });

        return $rows
            ->when($withinDays !== null, fn ($c) => $c->filter(fn ($r) => $r['days'] <= $withinDays))
            ->sortBy('days')
            ->values();
    }
}