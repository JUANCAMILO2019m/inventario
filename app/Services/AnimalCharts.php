<?php

namespace App\Services;

use App\Models\Animal;
use Carbon\Carbon;

class AnimalCharts
{
    private const KIND_ORDER = ['initial' => 0, 'weighing' => 1, 'sale' => 2];

    private const HEAD_LABELS = [
        'entry'       => 'Ingreso',
        'mortality'   => 'Baja',
        'sale'        => 'Venta',
        'consumption' => 'Consumo propio',
    ];

    /** Peso promedio por cabeza en el tiempo (pesaje, peso inicial y ventas con peso). */
    public static function weight(Animal $animal): array
    {
        $animal->loadMissing('records');
        $points = [];

        if ($animal->initial_weight !== null && $animal->entry_date) {
            $points[] = [
                'date'      => $animal->entry_date->toDateString(),
                'weight'    => (float) $animal->initial_weight,
                'kind'      => 'initial',
                'estimated' => false,
            ];
        }

        foreach ($animal->records->where('type', 'weight') as $r) {
            if ($r->weight === null) {
                continue;
            }

            $points[] = [
                'date'      => $r->recorded_at->toDateString(),
                'weight'    => (float) $r->weight,
                'kind'      => 'weighing',
                'estimated' => $r->weight_type === 'estimated',
            ];
        }

        // Las ventas de un mismo día (faena) se promedian: peso total ÷ cabezas
        $salesByDay = $animal->records
            ->where('type', 'sale')
            ->filter(fn ($r) => $r->total_weight && $r->heads)
            ->groupBy(fn ($r) => $r->recorded_at->toDateString());

        foreach ($salesByDay as $date => $group) {
            $points[] = [
                'date'      => $date,
                'weight'    => round((float) $group->sum('total_weight') / (int) $group->sum('heads'), 2),
                'kind'      => 'sale',
                'estimated' => $group->contains('weight_type', 'estimated'),
            ];
        }

        usort($points, fn ($a, $b) => [$a['date'], self::KIND_ORDER[$a['kind']]]
            <=> [$b['date'], self::KIND_ORDER[$b['kind']]]);

        return ['points' => $points];
    }

    /** Cabezas del lote a lo largo del tiempo, reconstruidas desde los movimientos. */
    public static function heads(Animal $animal): array
    {
        $animal->loadMissing('records');

        $moves = $animal->records
            ->whereIn('type', ['entry', 'mortality', 'sale', 'consumption'])
            ->filter(fn ($r) => (int) $r->heads > 0)
            ->sortBy([['recorded_at', 'asc'], ['id', 'asc']])
            ->values();

        $start = ($animal->entry_date ?? $animal->created_at)->copy()->startOfDay();

        if ($moves->isNotEmpty() && $moves->first()->recorded_at->lt($start)) {
            $start = $moves->first()->recorded_at->copy()->startOfDay();
        }

        $in = (int) $moves->where('type', 'entry')->sum('heads');
        $out = (int) $moves->whereIn('type', ['mortality', 'sale', 'consumption'])->sum('heads');

        $initial = $animal->initial_quantity !== null
            ? (int) $animal->initial_quantity
            : max(0, (int) $animal->quantity + $out - $in);

        $current = $initial;
        $byDate = [
            $start->toDateString() => ['heads' => $current, 'events' => ['Cantidad inicial: ' . $initial]],
        ];

        foreach ($moves->groupBy(fn ($r) => $r->recorded_at->toDateString()) as $date => $dayMoves) {
            $events = [];

            foreach (['entry', 'mortality', 'sale', 'consumption'] as $type) {
                $group = $dayMoves->where('type', $type);

                if ($group->isEmpty()) {
                    continue;
                }

                $total = (int) $group->sum('heads');
                $sign = $type === 'entry' ? 1 : -1;
                $current += $sign * $total;

                $label = self::HEAD_LABELS[$type] . ' ' . ($sign > 0 ? '+' : '−') . $total;

                if ($type === 'sale' && $group->count() > 1) {
                    $label .= ' (' . $group->count() . ' ventas)';
                }

                $events[] = $label;
            }

            $previous = $byDate[$date]['events'] ?? [];
            $byDate[$date] = ['heads' => $current, 'events' => array_merge($previous, $events)];
        }

        // Mientras el lote siga activo, la línea llega hasta hoy
        $today = now()->toDateString();

        if ($animal->quantity > 0 && $today > array_key_last($byDate)) {
            $byDate[$today] = ['heads' => (int) $animal->quantity, 'events' => ['Hoy']];
        }

        $points = [];
        foreach ($byDate as $date => $v) {
            $points[] = ['date' => $date, 'heads' => $v['heads'], 'events' => $v['events']];
        }

        return ['points' => $points, 'has_movements' => $moves->isNotEmpty()];
    }

    /** Alimento consumido por semana (kg) y costo acumulado. */
    public static function feed(Animal $animal): array
    {
        $animal->loadMissing('records.product');

        $toKg = ['kg' => 1.0, 'g' => 0.001, 'lb' => 0.45359237];

        $feedings = $animal->records
            ->where('type', 'feeding')
            ->filter(fn ($r) => (float) $r->product_quantity > 0)
            ->sortBy('recorded_at');

        if ($feedings->isEmpty()) {
            return ['weeks' => [], 'unconvertible' => false, 'missing_price' => false];
        }

        $weeks = [];
        $unconvertible = false;
        $missingPrice = false;

        foreach ($feedings as $r) {
            $key = $r->recorded_at->copy()->startOfWeek()->toDateString();
            $weeks[$key] ??= ['kg' => 0.0, 'cost' => 0.0];

            $factor = $toKg[$r->product?->unit ?? ''] ?? null;

            if ($factor === null) {
                $unconvertible = true;
            } else {
                $weeks[$key]['kg'] += (float) $r->product_quantity * $factor;
            }

            if ($r->unit_cost === null) {
                $missingPrice = true;
            } else {
                $weeks[$key]['cost'] += (float) $r->unit_cost * (float) $r->product_quantity;
            }
        }

        // Se rellenan las semanas sin consumo para que el eje del tiempo sea continuo
        $first = Carbon::parse(array_key_first($weeks));
        $last = Carbon::parse(array_key_last($weeks));
        $series = [];
        $cumulative = 0.0;

        for ($d = $first->copy(); $d->lte($last); $d->addWeek()) {
            $key = $d->toDateString();
            $w = $weeks[$key] ?? ['kg' => 0.0, 'cost' => 0.0];
            $cumulative += $w['cost'];

            $series[] = [
                'week'       => $key,
                'kg'         => round($w['kg'], 2),
                'cost'       => round($w['cost'], 2),
                'cumulative' => round($cumulative, 2),
            ];
        }

        return ['weeks' => $series, 'unconvertible' => $unconvertible, 'missing_price' => $missingPrice];
    }
}