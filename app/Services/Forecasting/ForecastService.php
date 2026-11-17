<?php

namespace App\Services\Forecasting;

use App\Models\Animal;
use App\Models\Product;
use App\Models\SipsaPrice;
use App\Models\StockMovement;
use App\Services\AnimalCharts;
use App\Services\DashboardCharts;
use App\Services\ReorderService;
use Carbon\Carbon;

class ForecastService
{
    // ------------------------------------------------------------------ inventario

    /** Consumo semanal proyectado por producto y fecha estimada de agotamiento. */
    public static function inventory(int $weeks = 13, int $horizon = 4): array
    {
        $end = now()->startOfWeek();                  // solo semanas completas
        $start = $end->copy()->subWeeks($weeks);

        $movements = StockMovement::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->where(function ($q) {
                $q->where(function ($o) {
                    $o->where('type', 'out')->where(function ($r) {
                        $r->whereNull('reason_type')->orWhereIn('reason_type', ['animal_use', 'transfer', 'other_out']);
                    });
                })->orWhere(function ($i) {
                    $i->where('type', 'in')->where('reason_type', 'reversal');
                });
            })
            ->get(['product_id', 'type', 'change', 'created_at']);

        $series = [];

        foreach ($movements->groupBy('product_id') as $productId => $group) {
            $weekly = array_fill(0, $weeks, 0.0);

            foreach ($group as $m) {
                $idx = intdiv($m->created_at->timestamp - $start->timestamp, 604800);

                if ($idx < 0 || $idx >= $weeks) {
                    continue;
                }

                $qty = abs((float) $m->change);
                $weekly[$idx] += $m->type === 'out' ? $qty : -$qty;
            }

            $series[$productId] = array_map(fn ($v) => max(0.0, $v), $weekly);
        }

        $products = Product::whereIn('id', array_keys($series))->get()->keyBy('id');
        $reorder = ReorderService::analyze(30, 30, null, false)->keyBy(fn ($r) => $r['product']->id);

        $rows = [];
        $skipped = 0;

        foreach ($series as $id => $weekly) {
            $product = $products->get($id);
            $active = count(array_filter($weekly, fn ($v) => $v > 0));

            if (!$product || $active < 3) {
                $skipped++;
                continue;
            }

            $fc = Forecaster::best($weekly, $horizon);

            if (!$fc) {
                $skipped++;
                continue;
            }

            $usable = $reorder->has($id) ? (float) $reorder[$id]['usable'] : max(0.0, (float) $product->quantity);
            $lead = $reorder->has($id)
                ? (int) $reorder[$id]['lead']
                : (int) ($product->lead_days ?? ReorderService::DEFAULT_LEAD_DAYS);

            $recent = array_slice($weekly, -4);
            $nowWeekly = array_sum($recent) / count($recent);
            $nextWeekly = array_sum($fc['values']) / $horizon;

            $central = Forecaster::daysToDeplete($usable, $fc['values']);
            $early = Forecaster::daysToDeplete($usable, $fc['high']);
            $late = Forecaster::daysToDeplete($usable, $fc['low']);

            $status = $usable <= 0 ? 'out'
                : ($central !== null && $central <= $lead ? 'critical'
                : ($central !== null && $central <= 30 ? 'soon' : 'ok'));

            $rows[] = [
                'product'     => $product,
                'unit'        => $product->unit,
                'usable'      => $usable,
                'lead'        => $lead,
                'now_weekly'  => $nowWeekly,
                'next_weekly' => $nextWeekly,
                'change'      => $nowWeekly > 0 ? ($nextWeekly - $nowWeekly) / $nowWeekly * 100 : null,
                'days'        => $central,
                'days_early'  => $early,
                'days_late'   => $late,
                'date'        => $central !== null ? now()->startOfDay()->addDays((int) floor($central)) : null,
                'cost_next'   => $product->price !== null ? array_sum($fc['values']) * (float) $product->price : null,
                'status'      => $status,
                'method'      => $fc['method'],
                'reliability' => $fc['reliability'],
                'wape'        => $fc['wape'],
            ];
        }

        usort($rows, fn ($a, $b) => [$a['days'] ?? INF, $a['product']->name] <=> [$b['days'] ?? INF, $b['product']->name]);

        return [
            'rows'         => $rows,
            'analyzed'     => count($rows),
            'skipped'      => $skipped,
            'within30'     => count(array_filter($rows, fn ($r) => $r['days'] !== null && $r['days'] <= 30)),
            'cost_total'   => array_sum(array_map(fn ($r) => $r['cost_next'] ?? 0.0, $rows)),
            'cost_missing' => count(array_filter($rows, fn ($r) => $r['cost_next'] === null)),
            'horizon'      => $horizon,
        ];
    }

    // ------------------------------------------------------------------ animales

    /** Peso futuro y fecha estimada de llegar al peso objetivo, con regresión lineal por animal o lote. */
    public static function growth(int $horizonDays = 30): array
    {
        $animals = Animal::where('status', 'active')->with('records')->orderBy('name')->get();
        $today = now()->startOfDay();
        $rows = [];
        $skipped = 0;

        foreach ($animals as $animal) {
            $points = collect(AnimalCharts::weight($animal)['points'])
                ->filter(fn ($p) => in_array($p['kind'], ['initial', 'weighing'], true))
                ->groupBy('date')
                ->map(fn ($g) => $g->last())
                ->sortKeys()
                ->values();

            if ($points->count() < 3) {
                $skipped++;
                continue;
            }

            $first = Carbon::parse($points->first()['date'])->startOfDay();
            $lastDate = Carbon::parse($points->last()['date'])->startOfDay();

            $x = $points->map(fn ($p) => (Carbon::parse($p['date'])->startOfDay()->timestamp - $first->timestamp) / 86400)->all();
            $y = $points->pluck('weight')->map(fn ($w) => (float) $w)->all();

            $span = (int) (($lastDate->timestamp - $first->timestamp) / 86400);
            $fit = Forecaster::linear($x, $y);

            if (!$fit || $span < 14) {
                $skipped++;
                continue;
            }

            $xNow = ($today->timestamp - $first->timestamp) / 86400;
            $current = Forecaster::linearPredict($fit, $xNow);
            $future = Forecaster::linearPredict($fit, $xNow + $horizonDays);
            $staleDays = (int) (($today->timestamp - $lastDate->timestamp) / 86400);

            $reliability = Forecaster::growthReliability($points->count(), $fit['r2'], $span);

            if ($staleDays > 45) {
                $reliability = 'low';
            }

            $target = $animal->target_weight !== null ? (float) $animal->target_weight : null;
            $reach = null;

            if ($target !== null) {
                if ($current['value'] >= $target) {
                    $reach = ['status' => 'done'];
                } elseif ($fit['b'] <= 0) {
                    $reach = ['status' => 'never'];
                } else {
                    $days = Forecaster::daysToReach($fit, $xNow, $target, 'value');
                    $early = Forecaster::daysToReach($fit, $xNow, $target, 'high');
                    $late = Forecaster::daysToReach($fit, $xNow, $target, 'low');

                    $reach = [
                        'status'     => $days === null ? 'far' : 'eta',
                        'days'       => $days,
                        'early'      => $early,
                        'late'       => $late,
                        'date'       => $days !== null ? $today->copy()->addDays($days) : null,
                        'date_early' => $early !== null ? $today->copy()->addDays($early) : null,
                        'date_late'  => $late !== null ? $today->copy()->addDays($late) : null,
                    ];
                }
            }

            // Línea ajustada con su banda, desde el primer pesaje hasta 60 días adelante
            $line = [];
            $xEnd = $xNow + 60;
            $step = max(5, (int) round($xEnd / 12));

            for ($d = 0.0; $d < $xEnd + $step; $d += $step) {
                $at = min($d, $xEnd);
                $p = Forecaster::linearPredict($fit, $at);

                $line[] = [
                    'date'  => $first->copy()->addDays((int) round($at))->toDateString(),
                    'value' => round($p['value'], 2),
                    'low'   => round($p['low'], 2),
                    'high'  => round($p['high'], 2),
                ];

                if ($at >= $xEnd) {
                    break;
                }
            }

            $estimated = $points->where('estimated', true)->count();

            $rows[] = [
                'animal'      => $animal,
                'n'           => $points->count(),
                'span'        => $span,
                'stale'       => $staleDays,
                'estimated'   => $estimated,
                'gain_g'      => $fit['b'] * 1000,
                'r2'          => $fit['r2'],
                'current'     => $current,
                'future'      => $future,
                'target'      => $target,
                'reach'       => $reach,
                'reliability' => $reliability,
                'chart'       => [
                    'name'    => $animal->name,
                    'target'  => $target,
                    'history' => $points->map(fn ($p) => [
                        'date'      => $p['date'],
                        'weight'    => (float) $p['weight'],
                        'estimated' => (bool) $p['estimated'],
                    ])->all(),
                    'line'    => $line,
                ],
            ];
        }

        usort($rows, function ($a, $b) {
            $da = $a['reach']['days'] ?? INF;
            $db = $b['reach']['days'] ?? INF;

            return [$da, $a['animal']->name] <=> [$db, $b['animal']->name];
        });

        return [
            'rows'      => $rows,
            'skipped'   => $skipped,
            'horizon'   => $horizonDays,
            'with_goal' => count(array_filter($rows, fn ($r) => $r['target'] !== null)),
            'within30'  => count(array_filter($rows, fn ($r) => ($r['reach']['days'] ?? INF) <= 30)),
        ];
    }

    // ------------------------------------------------------------------ finanzas

    /** Ventas, costos y resultado de los próximos meses, a partir de los meses completos ya cerrados. */
    public static function finance(int $horizon = 3): array
    {
        $needed = 8;
        $currentKey = now()->format('Y-m');

        $months = array_values(array_filter(
            DashboardCharts::monthly(24)['months'],
            fn ($m) => $m['month'] < $currentKey
        ));

        while (!empty($months) && $months[0]['sales'] == 0 && $months[0]['cost'] == 0) {
            array_shift($months);
        }

        $n = count($months);

        if ($n < $needed) {
            return ['ready' => false, 'months' => $n, 'needed' => $needed];
        }

        $salesFc = Forecaster::best(array_column($months, 'sales'), $horizon);
        $costFc = Forecaster::best(array_column($months, 'cost'), $horizon);

        if (!$salesFc || !$costFc) {
            return ['ready' => false, 'months' => $n, 'needed' => $needed];
        }

        $last = Carbon::createFromFormat('!Y-m', $months[$n - 1]['month']);
        $future = [];

        for ($k = 0; $k < $horizon; $k++) {
            $future[] = [
                'month'  => $last->copy()->addMonthsNoOverflow($k + 1)->format('Y-m'),
                'sales'  => ['value' => $salesFc['values'][$k], 'low' => $salesFc['low'][$k], 'high' => $salesFc['high'][$k]],
                'cost'   => ['value' => $costFc['values'][$k], 'low' => $costFc['low'][$k], 'high' => $costFc['high'][$k]],
                'result' => [
                    'value' => $salesFc['values'][$k] - $costFc['values'][$k],
                    'low'   => $salesFc['low'][$k] - $costFc['high'][$k],
                    'high'  => $salesFc['high'][$k] - $costFc['low'][$k],
                ],
            ];
        }

        $sum = fn (string $key, string $field) => array_sum(array_map(fn ($f) => $f[$key][$field], $future));

        return [
            'ready'    => true,
            'n'        => $n,
            'history'  => array_map(fn ($m) => [
                'month'  => $m['month'],
                'sales'  => $m['sales'],
                'cost'   => $m['cost'],
                'result' => $m['result'],
            ], $months),
            'future'   => $future,
            'totals'   => [
                'sales'  => ['value' => $sum('sales', 'value'), 'low' => $sum('sales', 'low'), 'high' => $sum('sales', 'high')],
                'cost'   => ['value' => $sum('cost', 'value'), 'low' => $sum('cost', 'low'), 'high' => $sum('cost', 'high')],
                'result' => ['value' => $sum('result', 'value'), 'low' => $sum('result', 'low'), 'high' => $sum('result', 'high')],
            ],
            'sales_model' => ['method' => $salesFc['method'], 'reliability' => $salesFc['reliability'], 'wape' => $salesFc['wape']],
            'cost_model'  => ['method' => $costFc['method'], 'reliability' => $costFc['reliability'], 'wape' => $costFc['wape']],
            'horizon'  => $horizon,
        ];
    }

    // ------------------------------------------------------------------ mercado

    /** Tendencia de los precios mayoristas del DANE en una ciudad, a partir de promedios semanales. */
    public static function prices(string $city, int $horizon = 4): array
    {
        $since = now()->subDays(400)->toDateString();

        $groups = SipsaPrice::where('city', $city)
            ->where('captured_on', '>=', $since)
            ->get(['product_code', 'product', 'price', 'captured_on'])
            ->groupBy('product_code');

        $maxDate = SipsaPrice::where('city', $city)->max('captured_on');
        $cutoff = $maxDate ? Carbon::parse($maxDate)->startOfWeek()->subWeeks(3) : null;

        $rows = [];
        $skipped = 0;

        foreach ($groups as $code => $group) {
            $weeks = [];

            foreach ($group as $r) {
                $weeks[$r->captured_on->copy()->startOfWeek()->toDateString()][] = (float) $r->price;
            }

            ksort($weeks);
            $keys = array_keys($weeks);
            $first = Carbon::parse($keys[0]);
            $last = Carbon::parse(end($keys));

            $series = [];
            $labels = [];
            $filled = 0;
            $carry = null;

            for ($d = $first->copy(); $d->lte($last); $d->addWeek()) {
                $key = $d->toDateString();

                if (isset($weeks[$key])) {
                    $carry = array_sum($weeks[$key]) / count($weeks[$key]);
                } else {
                    $filled++;
                }

                $series[] = $carry;
                $labels[] = $key;
            }

            if (count($weeks) < 20 || $filled / count($series) > 0.25 || ($cutoff && $last->lt($cutoff))) {
                $skipped++;
                continue;
            }

            $series = array_slice($series, -60);
            $labels = array_slice($labels, -60);

            $fc = Forecaster::best($series, $horizon);

            if (!$fc) {
                $skipped++;
                continue;
            }

            $current = end($series);
            $endValue = $fc['values'][$horizon - 1];
            $change = $current > 0 ? ($endValue - $current) / $current * 100 : 0.0;

            $futureLabels = [];
            for ($k = 1; $k <= $horizon; $k++) {
                $futureLabels[] = Carbon::parse(end($labels))->addWeeks($k)->toDateString();
            }

            $history = array_slice(array_map(null, $labels, $series), -52);

            $rows[] = [
                'code'        => (int) $code,
                'name'        => SipsaPrice::productLabel($group->first()->product),
                'current'     => $current,
                'end'         => $endValue,
                'low'         => $fc['low'][$horizon - 1],
                'high'        => $fc['high'][$horizon - 1],
                'change'      => $change,
                'trend'       => $change > 3 ? 'up' : ($change < -3 ? 'down' : 'stable'),
                'method'      => $fc['method'],
                'reliability' => $fc['reliability'],
                'wape'        => $fc['wape'],
                'chart'       => [
                    'name'     => SipsaPrice::productLabel($group->first()->product),
                    'history'  => array_map(fn ($p) => ['date' => $p[0], 'price' => round($p[1], 2)], $history),
                    'forecast' => array_map(fn ($date, $v, $lo, $hi) => [
                        'date'  => $date,
                        'value' => round($v, 2),
                        'low'   => round($lo, 2),
                        'high'  => round($hi, 2),
                    ], $futureLabels, $fc['values'], $fc['low'], $fc['high']),
                ],
            ];
        }

        usort($rows, fn ($a, $b) => strcmp(\Illuminate\Support\Str::ascii($a['name']), \Illuminate\Support\Str::ascii($b['name'])));

        return [
            'rows'    => $rows,
            'skipped' => $skipped,
            'horizon' => $horizon,
            'last'    => $maxDate ? Carbon::parse($maxDate) : null,
            'up'      => count(array_filter($rows, fn ($r) => $r['trend'] === 'up')),
            'down'    => count(array_filter($rows, fn ($r) => $r['trend'] === 'down')),
        ];
    }
}