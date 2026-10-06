<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\AnimalRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AnimalFaenaController extends Controller
{
    public function store(Request $request, Animal $animal)
    {
        abort_unless($animal->isLot(), 404);

        $data = $request->validateWithBag('faena', [
            'faena.recorded_at'                   => 'required|date',
            'faena.price_mode'                    => 'required|in:per_head,per_kg,total',
            'faena.weight_type'                   => 'nullable|in:scale,estimated',
            'faena.consumed_heads'                => 'nullable|integer|min:0',
            'faena.notes'                         => 'nullable|string|max:500',
            'faena.lines'                         => 'required|array|min:1|max:50',
            'faena.lines.*.buyer'                 => 'required|string|max:255',
            'faena.lines.*.heads'                 => 'required|integer|min:1',
            'faena.lines.*.weight'                => 'nullable|numeric|min:0.01|decimal:0,2',
            'faena.lines.*.price'                 => 'required|numeric|min:0|decimal:0,2',
            'faena.lines.*.payment_status'        => 'required|in:paid,pending,partial',
            'faena.lines.*.amount_paid'           => 'nullable|numeric|min:0|decimal:0,2',
        ], [
            'faena.lines.required'                => 'Agrega al menos un comprador.',
            'faena.lines.*.buyer.required'        => 'Línea :position: indica el comprador.',
            'faena.lines.*.heads.required'        => 'Línea :position: indica las cabezas.',
            'faena.lines.*.heads.integer'         => 'Línea :position: las cabezas deben ser un número entero.',
            'faena.lines.*.heads.min'             => 'Línea :position: debe tener al menos 1 cabeza.',
            'faena.lines.*.price.required'        => 'Línea :position: indica el precio o el valor.',
        ], [
            'faena.recorded_at'                   => 'fecha de la faena',
            'faena.price_mode'                    => 'forma de cobro',
            'faena.consumed_heads'                => 'cabezas de consumo propio',
            'faena.lines.*.weight'                => 'peso',
            'faena.lines.*.price'                 => 'precio',
            'faena.lines.*.amount_paid'           => 'abono',
        ]);

        $f = $data['faena'];
        $mode = $f['price_mode'];
        $weightType = $f['weight_type'] ?? 'scale';
        $consumed = (int) ($f['consumed_heads'] ?? 0);

        $rows = [];
        $problems = [];

        foreach (array_values($f['lines']) as $i => $line) {
            $n = $i + 1;
            $heads = (int) $line['heads'];
            $price = (float) $line['price'];
            $weight = isset($line['weight']) ? (float) $line['weight'] : null;

            if ($mode === 'per_kg' && !$weight) {
                $problems[] = "Línea {$n}: indica el peso en kg.";
                continue;
            }

            $value = round(match ($mode) {
                'per_head' => $heads * $price,
                'per_kg'   => $weight * $price,
                default    => $price,
            }, 2);

            $status = $line['payment_status'];
            $paid = match ($status) {
                'paid'    => $value,
                'pending' => 0.0,
                default   => round((float) ($line['amount_paid'] ?? 0), 2),
            };

            if ($status === 'partial' && ($paid <= 0 || $paid >= $value)) {
                $problems[] = "Línea {$n}: el abono debe ser mayor que 0 y menor que el valor de la venta.";
                continue;
            }

            $rows[] = [
                'buyer'  => trim($line['buyer']),
                'heads'  => $heads,
                'weight' => $weight,
                'price'  => $price,
                'value'  => $value,
                'status' => $status,
                'paid'   => $paid,
            ];
        }

        if ($problems) {
            return back()->withInput()->withErrors($problems, 'faena');
        }

        $soldHeads = array_sum(array_column($rows, 'heads'));

        try {
            DB::transaction(function () use ($animal, $f, $mode, $weightType, $consumed, $rows, $soldHeads) {
                $locked = Animal::lockForUpdate()->findOrFail($animal->id);
                $needed = $soldHeads + $consumed;

                if ($needed > $locked->quantity) {
                    throw new \InvalidArgumentException(
                        "El lote solo tiene {$locked->quantity} cabezas y la faena suma {$needed}."
                    );
                }

                $batchId = (string) Str::uuid();

                foreach ($rows as $row) {
                    $locked->adjustHeads('sale', $row['heads']);

                    AnimalRecord::create([
                        'animal_id'      => $animal->id,
                        'type'           => 'sale',
                        'recorded_at'    => $f['recorded_at'],
                        'title'          => $row['buyer'],
                        'heads'          => $row['heads'],
                        'total_weight'   => $mode === 'per_kg' ? $row['weight'] : null,
                        'weight_type'    => $mode === 'per_kg' ? $weightType : null,
                        'price_mode'     => $mode === 'total' ? null : $mode,
                        'unit_price'     => $mode === 'total' ? null : $row['price'],
                        'amount'         => $row['value'],
                        'payment_status' => $row['status'],
                        'amount_paid'    => $row['paid'],
                        'notes'          => $f['notes'] ?? null,
                        'batch_id'       => $batchId,
                    ]);
                }

                if ($consumed > 0) {
                    $locked->adjustHeads('consumption', $consumed);

                    AnimalRecord::create([
                        'animal_id'   => $animal->id,
                        'type'        => 'consumption',
                        'recorded_at' => $f['recorded_at'],
                        'title'       => 'Consumo propio',
                        'heads'       => $consumed,
                        'notes'       => $f['notes'] ?? null,
                        'batch_id'    => $batchId,
                    ]);
                }
            });
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors([$e->getMessage()], 'faena');
        }

        $buyers = count($rows);
        $message = "Faena registrada: {$soldHeads} cabezas vendidas a {$buyers} "
            . ($buyers === 1 ? 'comprador' : 'compradores')
            . ($consumed > 0 ? " y {$consumed} de consumo propio." : '.');

        return redirect()->route('animals.show', $animal)->with('success', $message);
    }

    public function destroy(Animal $animal, string $batch)
    {
        $records = $animal->records()->where('batch_id', $batch)->get();

        abort_if($records->isEmpty(), 404);

        DB::transaction(function () use ($animal, $records) {
            $locked = Animal::lockForUpdate()->findOrFail($animal->id);

            foreach ($records as $record) {
                $locked->adjustHeads($record->type, (int) $record->heads, reverse: true);
                $record->delete();
            }
        });

        return redirect()->route('animals.show', $animal)
            ->with('success', 'Faena eliminada: las cabezas volvieron al lote.');
    }
}