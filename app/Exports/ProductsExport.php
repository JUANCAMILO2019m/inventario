<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ProductsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithColumnWidths
{
    public function __construct(protected Collection $products)
    {
    }

    public function collection(): \Illuminate\Support\Enumerable
    {
        return $this->products;
    }

    public function headings(): array
    {
        return ['Nombre', 'Categoría', 'SKU', 'Cantidad', 'Stock mínimo', 'Ubicación', 'Precio', 'Notas'];
    }

    public function map($product): array
    {
        return [
            $product->name,
            $product->category?->name ?? '',
            $product->sku ?? '',
            $product->quantity,
            $product->min_stock,
            $product->location ?? '',
            $product->price ?? '',
            $product->notes ?? '',
        ];
    }

    public function styles(Worksheet $sheet): ?array
    {
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB'],
            ],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(22);

        $highestRow = $sheet->getHighestRow();
        for ($row = 2; $row <= $highestRow; $row++) {
            $quantity = $sheet->getCell("D{$row}")->getValue();
            $minStock = $sheet->getCell("E{$row}")->getValue();

            if ($quantity !== null && $minStock !== null && $quantity <= $minStock) {
                $sheet->getStyle("A{$row}:H{$row}")->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'FEE2E2'],
                    ],
                ]);
            }
        }

        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 28, 'B' => 18, 'C' => 15, 'D' => 10,
            'E' => 12, 'F' => 16, 'G' => 12, 'H' => 30,
        ];
    }
}