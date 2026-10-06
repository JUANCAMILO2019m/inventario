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
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AnimalRecordsSheet implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
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
        return $this->animals->flatMap(
            fn ($animal) => $animal->records
                ->sortBy([['recorded_at', 'desc'], ['id', 'desc']])
                ->map(fn ($record) => ['animal' => $animal, 'record' => $record])
        )->values();
    }

    public function headings(): array
    {
        return [
            'Animal', 'Código', 'Fecha', 'Tipo', 'Detalle', 'Peso (kg)', 'Tipo de peso', 'Cabezas',
            'Peso total vendido (kg)', 'Modo de precio', 'Precio unitario', 'Valor', 'Pagado', 'Saldo',
            'Estado de pago', 'Insumo', 'Cantidad usada', 'Unidad', 'Costo unitario', 'Costo total',
            'Próximo control', 'Notas',
        ];
    }

    public function map($row): array
    {
        $animal = $row['animal'];
        $r = $row['record'];

        $heads = match ($r->type) {
            'entry' => $r->heads,
            'mortality', 'sale', 'consumption' => $r->heads !== null ? -$r->heads : null,
            default => null,
        };

        $balance = $r->type === 'sale'
            ? max(0, round((float) $r->amount - (float) $r->amount_paid, 2))
            : null;

        $cost = ($r->unit_cost !== null && $r->product_quantity !== null)
            ? round($r->unit_cost * $r->product_quantity, 2)
            : null;

        return [
            $animal->name,
            $animal->code ?? '',
            $r->recorded_at->format('d/m/Y'),
            match ($r->type) {
                'feeding' => 'Alimentación',
                'vaccine' => 'Vacuna/Desparasitación',
                'weight' => 'Pesaje',
                'mortality' => 'Baja (mortalidad)',
                'sale' => 'Venta',
                'consumption' => 'Consumo propio',
                'entry' => 'Ingreso de animales',
                default => 'Tratamiento',
            },
            $r->title ?? '',
            $r->weight !== null ? (float) $r->weight : null,
            match ($r->weight_type) {
                'scale' => 'Báscula',
                'estimated' => 'Estimado',
                default => '',
            },
            $heads,
            $r->total_weight,
            match ($r->price_mode) {
                'per_kg' => 'Por kilo',
                'per_head' => 'Por cabeza',
                default => '',
            },
            $r->unit_price,
            $r->amount,
            $r->type === 'sale' ? $r->amount_paid : null,
            $balance,
            match ($r->payment_status) {
                'paid' => 'Pagado',
                'partial' => 'Parcial',
                'pending' => 'Pendiente',
                default => '',
            },
            $r->product?->name ?? '',
            $r->product_quantity,
            $r->product?->unit ?? '',
            $r->unit_cost,
            $cost,
            $r->next_due_date?->format('d/m/Y') ?? '',
            $r->notes ?? '',
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $lastColumn = $sheet->getHighestColumn();

        $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(22);

        return [];
    }
}