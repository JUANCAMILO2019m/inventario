<?php

namespace App\Models;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\HasPhoto;

class Product extends Model
{
    use HasPhoto;
    
    protected $fillable = [
            'category_id', 'name', 'sku', 'quantity',
            'min_stock', 'unit','location', 'price', 'notes', 'photo',
        ];
    
        protected function casts(): array
        {
            return [
                'quantity'  => 'float',
                'min_stock' => 'float',
            ];
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
        public function registerMovement(string $type, float $amount, ?string $reason = null): StockMovement
        {
            return DB::transaction(function () use ($type, $amount, $reason) {
                $current = round((float) $this->fresh()->quantity, 3);

                $new = round(match ($type) {
                    'in'     => $current + $amount,
                    'out'    => $current - $amount,
                    'adjust' => $amount,
                }, 3);

                if ($new < 0) {
                    throw new \InvalidArgumentException('La cantidad resultante no puede ser negativa.');
                }

                $this->update(['quantity' => $new]);

                return $this->movements()->create([
                    'type'           => $type,
                    'change'         => round($new - $current, 3),
                    'quantity_after' => $new,
                    'reason'         => $reason,
                ]);
            });
        }
}