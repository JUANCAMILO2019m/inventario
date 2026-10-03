<?php

namespace App\Models;

use App\Models\Concerns\HasPhoto;
use Illuminate\Database\Eloquent\Model;

class Animal extends Model
{
    use HasPhoto;

    protected $fillable = [
        'type', 'name', 'code', 'species', 'breed', 'sex',
        'birth_date', 'quantity', 'status', 'photo', 'description',
        'supplier', 'purchase_cost', 'entry_date', 'initial_weight', 'initial_quantity',
    ];

    protected function casts(): array
    {
        return [
            'birth_date'     => 'date',
            'entry_date'     => 'date',
            'purchase_cost'  => 'float',
            'initial_weight' => 'float',
        ];
    }

    public function records()
    {
        return $this->hasMany(AnimalRecord::class);
    }

    public function isLot(): bool
    {
        return $this->type === 'lot';
    }

    public function lastWeight(): ?AnimalRecord
    {
        return $this->records()
            ->where('type', 'weight')
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first();
    }

    public function hasHeadMovements(): bool
    {
        return $this->records()->whereIn('type', ['entry', 'mortality', 'sale'])->exists();
    }

    /**
     * Suma o resta cabezas según el tipo de movimiento.
     * 'entry' suma; 'mortality' y 'sale' restan. Con $reverse se deshace el movimiento.
     */
    public function adjustHeads(string $type, int $heads, bool $reverse = false): void
    {
        $sign = $type === 'entry' ? 1 : -1;

        if ($reverse) {
            $sign = -$sign;
        }

        $current = (int) $this->quantity;
        $new = $current + ($sign * $heads);

        if ($new < 0) {
            throw new \InvalidArgumentException('Las cabezas resultantes no pueden ser negativas.');
        }

        $attributes = ['quantity' => $new];

        if ($new === 0 && !$reverse) {
            $attributes['status'] = $type === 'sale' ? 'sold' : 'dead';
        } elseif ($new > 0 && $current === 0) {
            $attributes['status'] = 'active';
        }

        $this->update($attributes);
    }

    public function lotStats(): array
    {
        $sum = fn (string $type, string $column) => (float) $this->records()->where('type', $type)->sum($column);

        $entered = (int) $this->initial_quantity + (int) $sum('entry', 'heads');
        $deaths = (int) $sum('mortality', 'heads');
        $sold = (int) $sum('sale', 'heads');
        $invested = (float) $this->purchase_cost + $sum('entry', 'amount');
        $lastWeight = $this->lastWeight()?->weight;
        $lastWeight = $lastWeight !== null ? (float) $lastWeight : null;

        return [
            'entered'       => $entered,
            'deaths'        => $deaths,
            'sold'          => $sold,
            'mortality_pct' => $entered > 0 ? round($deaths / $entered * 100, 1) : 0,
            'invested'      => $invested,
            'cost_per_head' => ($entered > 0 && $invested > 0) ? $invested / $entered : null,
            'revenue'       => $sum('sale', 'amount'),
            'last_weight'   => $lastWeight,
            'weight_gain'   => ($this->initial_weight !== null && $lastWeight !== null)
                ? round($lastWeight - $this->initial_weight, 2)
                : null,
        ];
    }
}