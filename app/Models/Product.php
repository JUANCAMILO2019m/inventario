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
        'category_id', 'name', 'sku', 'quantity',
        'min_stock', 'unit', 'location', 'price', 'notes', 'photo',
    ];

    protected function casts(): array
    {
        return [
            'quantity'  => 'float',
            'min_stock' => 'float',
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

    /**
     * Registra un movimiento y actualiza la cantidad.
     * 'in' suma, 'out' resta, 'adjust' fija la cantidad exacta.
     */
    public function registerMovement(
        string $type,
        float $amount,
        ?string $reason = null,
        ?string $reasonType = null
    ): StockMovement {
        return DB::transaction(function () use ($type, $amount, $reason, $reasonType) {
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
                'unit_cost'      => $locked->price !== null ? (float) $locked->price : null,
            ]);
        });
    }
}