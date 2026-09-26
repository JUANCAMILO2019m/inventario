<?php

namespace App\Models;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
            'category_id', 'name', 'sku', 'quantity',
            'min_stock', 'location', 'price', 'notes', 'photo',
        ];

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
        public function registerMovement(string $type, int $amount, ?string $reason = null): StockMovement
        {
            return DB::transaction(function () use ($type, $amount, $reason) {
                $current = (int) $this->fresh()->quantity;

                $new = match ($type) {
                    'in'     => $current + $amount,
                    'out'    => $current - $amount,
                    'adjust' => $amount,
                };

                $this->update(['quantity' => $new]);

                return $this->movements()->create([
                    'type'           => $type,
                    'change'         => $new - $current,
                    'quantity_after' => $new,
                    'reason'         => $reason,
                ]);
            });
        }
}