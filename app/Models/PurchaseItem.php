<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PurchaseItem extends Model
{
    protected $fillable = [
        'purchase_id', 'product_id', 'product_name', 'unit', 'packages', 'package_size',
        'package_name', 'quantity', 'line_total', 'unit_cost', 'avg_before', 'avg_after',
        'stock_movement_id',
    ];

    protected function casts(): array
    {
        return [
            'packages'     => 'float',
            'package_size' => 'float',
            'quantity'     => 'float',
            'line_total'   => 'float',
            'unit_cost'    => 'float',
            'avg_before'   => 'float',
            'avg_after'    => 'float',
        ];
    }

    public function purchase()
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function batch()
    {
        return $this->hasOne(ProductBatch::class);
    }
}