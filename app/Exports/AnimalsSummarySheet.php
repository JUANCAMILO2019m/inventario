<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AnimalsSummarySheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    public function __construct(protected Collection $animals)
    {
    }

    public function title(): string
    {
        return 'Resumen económico';
    }

    public function collection(): Enumerable
    {
        return $this->animals;
    }

    public function headings(): array
    {
        return [
            'Animal', 'Código', 'Tipo', 'Estado', 'Ingresadas', 'Bajas', 'Vendidas', 'Mortalidad (%)',
            'Costo compra', 'Costo alimento', 'Costo sanidad', 'Costo total',
            'Ventas', 'Cobrado', 'Por cobrar', 'Resultado', 'Tipo de resultado',
            'Ganancia por cabeza (kg)', 'Ganancia diaria (g/día)', 'Conversión alimenticia', 'Costo por kg ganado',
        ];
    }

    public function map($animal): array
    {
        $s = $animal->stats();

        return [
            $animal->name,
            $animal->code ?? '',
            $animal->type === 'lot' ? 'Lote' : 'Individual',
            match ($animal->status) {
                'active' => 'Activo',
                'sold' => 'Vendido',
                default => 'Baja',
            },
            $s['entered'],
            $s['deaths'],
            $s['sold_heads'],
            $s['mortality_pct'],
            $s['purchase'],
            $s['feed_cost'],
            $s['health_cost'],
            $s['total_cost'],
            $s['revenue'],
            $s['collected'],
            $s['receivable'],
            $s['margin'],
            $s['closed'] ? 'Final' : 'Parcial',
            $s['weight_gain'],
            $s['gdp_g'],
            $s['fcr'],
            $s['cost_per_kg_gain'],
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $lastColumn = $sheet->getHighestColumn();

        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
        ]);

        return [];
    }
}