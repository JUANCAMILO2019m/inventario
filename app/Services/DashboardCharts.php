<?php

namespace App\Services;

use App\Models\Animal;
use App\Models\AnimalRecord;

class DashboardCharts
{
    /** Ventas y costos de los últimos N meses (incluido el actual). */
    public static function monthly(int $months = 12): array
    {
        $start = now()->startOfMonth()->subMonths($months - 1);

        $rows = [];
        for ($i = 0; $i < $months; $i++) {
            $rows[$start->copy()->addMonths($i)->format('Y-m')] = [
                'sales' => 0.0, 'purchase' => 0.0, 'feed' => 0.0, 'health' => 0.0,
            ];
        }

        AnimalRecord::query()
            ->whereDate('recorded_at', '>=', $start->toDateString())
            ->whereIn('type', ['sale', 'entry', 'feeding', 'vaccine', 'treatment'])
            ->get(['type', 'recorded_at', 'amount', 'product_quantity', 'unit_cost'])
            ->each(function ($r) use (&$rows) {
                $key = $r->recorded_at->format('Y-m');

                if (!isset($rows[$key])) {
                    return;
                }

                $cost = (float) $r->unit_cost * (float) $r->product_quantity;

                switch ($r->type) {
                    case 'sale':
                        $rows[$key]['sales'] += (float) $r->amount;
                        break;
                    case 'entry':
                        $rows[$key]['purchase'] += (float) $r->amount;
                        break;
                    case 'feeding':
                        $rows[$key]['feed'] += $cost;
                        break;
                    default:
                        $rows[$key]['health'] += $cost;
                }
            });

        // La compra inicial de cada animal o lote cuenta en el mes de su ingreso
        Animal::where('purchase_cost', '>', 0)
            ->get(['id', 'purchase_cost', 'entry_date', 'created_at'])
            ->each(function ($a) use (&$rows) {
                $key = ($a->entry_date ?? $a->created_at)->format('Y-m');

                if (isset($rows[$key])) {
                    $rows[$key]['purchase'] += (float) $a->purchase_cost;
                }
            });

        $list = [];
        $totalSales = 0.0;
        $totalCost = 0.0;

        foreach ($rows as $key => $v) {
            $cost = $v['purchase'] + $v['feed'] + $v['health'];
            $totalSales += $v['sales'];
            $totalCost += $cost;

            $list[] = [
                'month'    => $key,
                'sales'    => round($v['sales'], 2),
                'cost'     => round($cost, 2),
                'result'   => round($v['sales'] - $cost, 2),
                'purchase' => round($v['purchase'], 2),
                'feed'     => round($v['feed'], 2),
                'health'   => round($v['health'], 2),
            ];
        }

        return [
            'months' => $list,
            'totals' => [
                'sales'  => round($totalSales, 2),
                'cost'   => round($totalCost, 2),
                'result' => round($totalSales - $totalCost, 2),
            ],
        ];
    }
}