<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnimalRecord extends Model
{
    protected $fillable = [
        'animal_id', 'type', 'recorded_at', 'title', 'product_id',
        'product_quantity', 'stock_movement_id', 'weight', 'weight_type',
        'heads', 'amount', 'total_weight', 'price_mode', 'unit_price',
        'payment_status', 'amount_paid', 'unit_cost', 'next_due_date', 'notes', 'batch_id',
    ];

    protected function casts(): array
    {
        return [
            'recorded_at'      => 'date',
            'next_due_date'    => 'date',
            'weight'           => 'decimal:2',
            'product_quantity' => 'float',
            'heads'            => 'integer',
            'amount'           => 'float',
            'total_weight'     => 'float',
            'unit_price'       => 'float',
            'amount_paid'      => 'float',
            'unit_cost'        => 'float',
        ];
    }

    public function animal()
    {
        return $this->belongsTo(Animal::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function movement()
    {
        return $this->belongsTo(StockMovement::class, 'stock_movement_id');
    }
}