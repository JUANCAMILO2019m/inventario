<?php

namespace App\Models;

use App\Models\Concerns\TracksUser;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    use TracksUser;

    /** Motivos que el usuario puede elegir, según el tipo de movimiento. */
    public const REASONS = [
        'in' => [
            'purchase' => 'Compra',
            'return'   => 'Devolución',
            'other_in' => 'Otra entrada',
        ],
        'out' => [
            'animal_use' => 'Uso en animales',
            'loss'       => 'Pérdida',
            'damage'     => 'Daño',
            'expired'    => 'Vencido',
            'transfer'   => 'Venta o entrega a terceros',
            'other_out'  => 'Otra salida',
        ],
        'adjust' => [
            'count'      => 'Conteo físico',
            'correction' => 'Corrección',
        ],
    ];

    /** Motivos que genera el sistema por su cuenta. */
    public const SYSTEM_REASONS = [
        'initial'  => 'Stock inicial',
        'reversal' => 'Reverso de registro',
    ];

    /** Salidas que cuentan como merma. */
    public const WASTE = ['loss', 'damage', 'expired'];

    protected $fillable = [
        'product_id', 'type', 'change', 'quantity_after',
        'reason', 'reason_type', 'unit_cost',
    ];

    protected function casts(): array
    {
        return [
            'change'         => 'float',
            'quantity_after' => 'float',
            'unit_cost'      => 'float',
        ];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public static function reasonsFor(?string $type): array
    {
        return self::REASONS[$type] ?? [];
    }

    public static function allReasons(): array
    {
        return array_merge(self::SYSTEM_REASONS, ...array_values(self::REASONS));
    }

    public static function reasonLabel(?string $key): string
    {
        return $key ? (self::allReasons()[$key] ?? $key) : '—';
    }

    /** Valor del movimiento, con el costo de ese momento (o el precio actual si no hay). */
    public function value(): ?float
    {
        $cost = $this->unit_cost
            ?? ($this->product?->price !== null ? (float) $this->product->price : null);

        return $cost === null ? null : round(abs($this->change) * $cost, 2);
    }
}