<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasPhoto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Product extends Model
{
    use HasPhoto, Auditable;

    protected $fillable = [
        'category_id', 'name', 'sku', 'quantity', 'min_stock', 'unit',
        'package_name', 'package_size', 'location', 'price', 'notes', 'photo',
        'lead_days', 
    ];

    protected function casts(): array
    {
        return [
            'quantity'     => 'float',
            'min_stock'    => 'float',
            'package_size' => 'float',
        ];
    }

    // La cantidad cambia con cada movimiento y ya queda registrada en el historial
    protected function auditExcept(): array
    {
        return ['quantity'];
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function movements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function batches()
    {
        return $this->hasMany(ProductBatch::class);
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /** Ej: "≈ 2 × bulto de 40 kg" cuando el producto tiene empaque definido. */
    public function packagesLabel(): ?string
    {
        if (!$this->package_name || (float) $this->package_size <= 0) {
            return null;
        }

        $packages = round((float) $this->quantity / (float) $this->package_size, 2);
        $size = rtrim(rtrim(number_format((float) $this->package_size, 3, '.', ''), '0'), '.');

        return '≈ ' . rtrim(rtrim(number_format($packages, 2, '.', ''), '0'), '.')
            . ' × ' . $this->package_name . ' de ' . $size . ' ' . $this->unit;
    }

    /**
     * Registra un movimiento y actualiza la cantidad.
     * 'in' suma, 'out' resta, 'adjust' fija la cantidad exacta.
     */
    public function registerMovement(
        string $type,
        float $amount,
        ?string $reason = null,
        ?string $reasonType = null,
        ?float $unitCost = null
    ): StockMovement {
        return DB::transaction(function () use ($type, $amount, $reason, $reasonType, $unitCost) {
            $locked = static::lockForUpdate()->findOrFail($this->getKey());
            $current = round((float) $locked->quantity, 3);

            $new = round(match ($type) {
                'in'     => $current + $amount,
                'out'    => $current - $amount,
                'adjust' => $amount,
            }, 3);

            if ($new < 0) {
                throw new \InvalidArgumentException('La cantidad resultante no puede ser negativa.');
            }

            $locked->update(['quantity' => $new]);
            $this->refresh();

            return $this->movements()->create([
                'type'           => $type,
                'change'         => round($new - $current, 3),
                'quantity_after' => $new,
                'reason'         => $reason,
                'reason_type'    => $reasonType,
                'unit_cost'      => $unitCost ?? ($locked->price !== null ? (float) $locked->price : null),
            ]);
        });
    }

    /**
     * Registra la entrada de una compra y recalcula el costo promedio ponderado.
     * Devuelve [movimiento, costo promedio antes, costo promedio después].
     */
    public function receivePurchase(float $quantity, float $unitCost, string $reason): array
    {
        return DB::transaction(function () use ($quantity, $unitCost, $reason) {
            $locked = static::lockForUpdate()->findOrFail($this->getKey());

            $stockBefore = max(0.0, (float) $locked->quantity);
            $avgBefore = $locked->price !== null ? (float) $locked->price : null;

            $avgAfter = ($avgBefore !== null && $stockBefore > 0)
                ? round(($stockBefore * $avgBefore + $quantity * $unitCost) / ($stockBefore + $quantity), 2)
                : round($unitCost, 2);

            $movement = $this->registerMovement('in', $quantity, $reason, 'purchase', $unitCost);

            $this->update(['price' => $avgAfter]);

            return [$movement, $avgBefore, $avgAfter];
        });
    }
}