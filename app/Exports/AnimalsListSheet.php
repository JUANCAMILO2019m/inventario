<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AnimalsListSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle
{
    public function __construct(protected Collection $animals)
    {
    }

    public function title(): string
    {
        return 'Animales';
    }

    public function collection(): Enumerable
    {
        return $this->animals;
    }

    public function headings(): array
    {
        return [
            'Nombre', 'Código', 'Tipo', 'Especie', 'Raza', 'Sexo',
            'Nacimiento', 'Ingreso', 'Proveedor', 'Costo de compra', 'Peso inicial (kg)',
            'Cantidad actual', 'Estado', 'Último peso (kg)', 'Descripción',
        ];
    }
    public function map($animal): array
    {
        $lastWeight = $animal->records
            ->where('type', 'weight')
            ->sortBy([['recorded_at', 'desc'], ['id', 'desc']])
            ->first();

        return [
            $animal->name,
            $animal->code ?? '',
            $animal->type === 'lot' ? 'Lote' : 'Individual',
            $animal->species,
            $animal->breed ?? '',
            match ($animal->sex) {
                'male' => 'Macho',
                'female' => 'Hembra',
                'mixed' => 'Mixto',
                default => '',
            },
            $animal->birth_date?->format('d/m/Y') ?? '',
            $animal->entry_date?->format('d/m/Y') ?? '',
            $animal->supplier ?? '',
            $animal->purchase_cost,
            $animal->initial_weight,
            $animal->quantity,
            match ($animal->status) {
                'active' => 'Activo',
                'sold' => 'Vendido',
                default => 'Baja',
            },
            $lastWeight ? (float) $lastWeight->weight : null,
            $animal->description ?? '',
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $lastColumn = $sheet->getHighestColumn();

        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB'],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(22);

        // Vendidos y dados de baja, en gris
        foreach ($this->animals->values() as $index => $animal) {
            if ($animal->status !== 'active') {
                $row = $index + 2;
                $sheet->getStyle("A{$row}:{$lastColumn}{$row}")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F3F4F6'],
                    ],
                    'font' => ['color' => ['rgb' => '6B7280']],
                ]);
            }
        }

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 26, 'B' => 14, 'C' => 12, 'D' => 16, 'E' => 16, 'F' => 10, 'G' => 13, 'H' => 13,
            'I' => 22, 'J' => 16, 'K' => 16, 'L' => 14, 'M' => 10, 'N' => 16, 'O' => 40,
        ];
    }
}