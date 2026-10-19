<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockCountItem extends Model
{
    protected $fillable = [
        'stock_count_id', 'product_id', 'product_name', 'category_name', 'unit',
        'system_qty', 'unit_cost', 'counted_qty', 'applied_change', 'stock_movement_id',
    ];

    protected function casts(): array
    {
        return [
            'system_qty'     => 'float',
            'unit_cost'      => 'float',
            'counted_qty'    => 'float',
            'applied_change' => 'float',
        ];
    }

    public function count()
    {
        return $this->belongsTo(StockCount::class, 'stock_count_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function movement()
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }

    /** Estado de la fila: uncounted, adjusted, same, skipped o pending. */
    public function state(string $countStatus): string
    {
        if ($this->counted_qty === null) {
            return 'uncounted';
        }

        if ($this->applied_change !== null) {
            return abs($this->applied_change) > 0.0005 ? 'adjusted' : 'same';
        }

        return $countStatus === 'applied' ? 'skipped' : 'pending';
    }
}