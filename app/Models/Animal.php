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
     * 'entry' suma cabezas; 'mortality' y 'sale' restan. Con $reverse se deshace el movimiento.
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

    /**
     * Cabezas, costos, ventas, cobros y rentabilidad (lotes e individuales).
     */
    public function stats(): array
    {
        $this->loadMissing('records.product');
        $records = $this->records;

        $heads = fn (string $type) => (int) $records->where('type', $type)->sum('heads');

        $entered = $this->isLot() ? (int) $this->initial_quantity + $heads('entry') : 1;
        $deaths = $heads('mortality');
        $soldHeads = $heads('sale');

        // Costos
        $consumption = fn ($r) => (float) $r->unit_cost * (float) $r->product_quantity;

        $purchase = (float) $this->purchase_cost + (float) $records->where('type', 'entry')->sum('amount');
        $feedCost = (float) $records->where('type', 'feeding')->sum($consumption);
        $healthCost = (float) $records->whereIn('type', ['vaccine', 'treatment'])->sum($consumption);
        $totalCost = $purchase + $feedCost + $healthCost;

        $missingPrices = $records->filter(
            fn ($r) => in_array($r->type, ['feeding', 'vaccine', 'treatment'], true)
                && $r->product_quantity > 0
                && $r->unit_cost === null
        )->count();

        // Ventas y cobros
        $sales = $records->where('type', 'sale');
        $revenue = (float) $sales->sum('amount');
        $collected = (float) $sales->sum('amount_paid');
        $receivable = max(0, round($revenue - $collected, 2));

        $closed = $this->isLot() ? (int) $this->quantity === 0 : $this->status !== 'active';
        $margin = $revenue - $totalCost;

        // Último peso promedio conocido (pesaje o venta con peso)
        $last = $records->map(function ($r) {
            if ($r->type === 'weight' && $r->weight !== null) {
                return ['ts' => $r->recorded_at->timestamp, 'id' => $r->id, 'avg' => (float) $r->weight, 'type' => $r->weight_type];
            }

            if ($r->type === 'sale' && $r->total_weight && $r->heads) {
                return ['ts' => $r->recorded_at->timestamp, 'id' => $r->id, 'avg' => (float) $r->total_weight / (int) $r->heads, 'type' => $r->weight_type];
            }

            return null;
        })->filter()->sortBy([['ts', 'desc'], ['id', 'desc']])->values()->first();

        $initial = $this->initial_weight;
        $gain = ($initial !== null && $last) ? round($last['avg'] - $initial, 2) : null;

        $days = null;
        if ($last && $this->entry_date) {
            $seconds = $last['ts'] - $this->entry_date->timestamp;
            $days = $seconds >= 86400 ? $seconds / 86400 : null;
        }

        $gdp = ($gain !== null && $days) ? round($gain * 1000 / $days) : null;

        // Kilos ganados (aproximado): ganancia por cabeza × cabezas que llegaron al final
        $finalHeads = max(0, $entered - $deaths);
        $totalGain = ($gain !== null && $gain > 0) ? $gain * $finalHeads : null;

        // Alimento consumido en kg (solo productos en kg, g o lb)
        $toKg = ['kg' => 1.0, 'g' => 0.001, 'lb' => 0.45359237];
        $feedKg = 0.0;
        $nonConvertible = false;

        foreach ($records->where('type', 'feeding') as $r) {
            if (!$r->product_quantity) {
                continue;
            }

            $factor = $toKg[$r->product?->unit ?? ''] ?? null;

            if ($factor === null) {
                $nonConvertible = true;
                continue;
            }

            $feedKg += (float) $r->product_quantity * $factor;
        }

        $productionCost = $feedCost + $healthCost;
        $fcr = ($totalGain && $feedKg > 0) ? round($feedKg / $totalGain, 2) : null;
        $costPerKgGain = ($totalGain && $productionCost > 0) ? round($productionCost / $totalGain, 2) : null;

        // Avisos para interpretar bien los números
        $warnings = [];

        if ($purchase <= 0) {
            $warnings[] = 'No hay costo de compra registrado: el resultado puede estar sobrestimado.';
        }
        if ($missingPrices > 0) {
            $warnings[] = "{$missingPrices} consumo(s) de insumos sin precio: el costo está subestimado.";
        }
        if ($nonConvertible) {
            $warnings[] = 'Hay alimento en unidades distintas a kg, g o lb: la conversión alimenticia no lo incluye.';
        }
        if ($last && $last['type'] === 'estimated') {
            $warnings[] = 'El último peso usado es estimado: los indicadores de peso son aproximados.';
        }
        if ($last && ($initial === null || !$this->entry_date)) {
            $warnings[] = 'Falta el peso inicial o la fecha de ingreso: no se calcula la ganancia diaria.';
        }

        return [
            'entered'          => $entered,
            'deaths'           => $deaths,
            'sold_heads'       => $soldHeads,
            'mortality_pct'    => $entered > 0 ? round($deaths / $entered * 100, 1) : 0,
            'purchase'         => $purchase,
            'feed_cost'        => $feedCost,
            'health_cost'      => $healthCost,
            'total_cost'       => $totalCost,
            'cost_per_head'    => ($entered > 0 && $totalCost > 0) ? $totalCost / $entered : null,
            'revenue'          => $revenue,
            'collected'        => $collected,
            'receivable'       => $receivable,
            'margin'           => $margin,
            'margin_pct'       => $totalCost > 0 ? round($margin / $totalCost * 100, 1) : null,
            'closed'           => $closed,
            'last_weight'      => $last['avg'] ?? null,
            'last_weight_type' => $last['type'] ?? null,
            'weight_gain'      => $gain,
            'gdp_g'            => $gdp,
            'fcr'              => $fcr,
            'cost_per_kg_gain' => $costPerKgGain,
            'warnings'         => $warnings,
        ];
    }
}