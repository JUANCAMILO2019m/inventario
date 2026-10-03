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

class AnimalRecordsSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths, WithTitle
{
    public function __construct(protected Collection $animals)
    {
    }

    public function title(): string
    {
        return 'Historial';
    }

    public function collection(): Enumerable
    {
        // Agrupado por animal; dentro de cada uno, lo más reciente primero
        return $this->animals->flatMap(
            fn ($animal) => $animal->records
                ->sortBy([['recorded_at', 'desc'], ['id', 'desc']])
                ->map(fn ($record) => ['animal' => $animal, 'record' => $record])
        )->values();
    }

    public function headings(): array
    {
        return [
            'Animal', 'Código', 'Fecha', 'Tipo', 'Detalle', 'Peso (kg)', 'Cabezas', 'Valor',
            'Insumo', 'Cantidad usada', 'Unidad', 'Próximo control', 'Notas',
        ];
    }

    public function map($row): array
    {
        $animal = $row['animal'];
        $r = $row['record'];

        $heads = match ($r->type) {
            'entry' => $r->heads,
            'mortality', 'sale' => $r->heads !== null ? -$r->heads : null,
            default => null,
        };

        return [
            $animal->name,
            $animal->code ?? '',
            $r->recorded_at->format('d/m/Y'),
            match ($r->type) {
                'feeding' => 'Alimentación',
                'vaccine' => 'Vacuna/Desparasitación',
                'weight' => 'Pesaje',
                'mortality' => 'Baja (mortalidad)',
                'sale' => 'Venta parcial',
                'entry' => 'Ingreso de animales',
                default => 'Tratamiento',
            },
            $r->title ?? '',
            $r->weight !== null ? (float) $r->weight : null,
            $heads,
            $r->amount,
            $r->product?->name ?? '',
            $r->product_quantity,
            $r->product?->unit ?? '',
            $r->next_due_date?->format('d/m/Y') ?? '',
            $r->notes ?? '',
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

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 26, 'B' => 14, 'C' => 12, 'D' => 24, 'E' => 26, 'F' => 11, 'G' => 10,
            'H' => 14, 'I' => 26, 'J' => 14, 'K' => 10, 'L' => 16, 'M' => 40,
        ];
    }
}