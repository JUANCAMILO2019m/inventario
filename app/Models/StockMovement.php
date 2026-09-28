<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = ['product_id', 'type', 'change', 'quantity_after', 'reason'];

    protected function casts(): array
    {
        return [
            'change'         => 'float',
            'quantity_after' => 'float',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
