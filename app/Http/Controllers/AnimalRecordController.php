<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\AnimalRecord;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnimalRecordController extends Controller
{
    private const HEAD_TYPES = ['mortality', 'sale', 'entry'];

    public function store(Request $request, Animal $animal)
    {
        $data = $request->validate([
            'type'             => 'required|in:feeding,vaccine,weight,treatment,mortality,sale,entry',
            'recorded_at'      => 'required|date',
            'title'            => 'nullable|string|max:255',
            'product_id'       => 'nullable|exists:products,id',
            'product_quantity' => 'nullable|numeric|min:0.001|decimal:0,3',
            'weight'           => 'nullable|numeric|min:0|decimal:0,2',
            'heads'            => 'nullable|integer|min:1',
            'amount'           => 'nullable|numeric|min:0|decimal:0,2',
            'next_due_date'    => 'nullable|date',
            'notes'            => 'nullable|string',
        ]);

        if (in_array($data['type'], self::HEAD_TYPES, true)) {
            return $this->storeHeadMovement($data, $animal);
        }

        unset($data['heads'], $data['amount']);

        if ($data['type'] === 'weight' && empty($data['weight'])) {
            return back()->withInput()->withErrors(['weight' => 'El peso es obligatorio para este tipo de registro.']);
        }

        if (!empty($data['product_id']) && !empty($data['product_quantity'])) {
            $product = Product::findOrFail($data['product_id']);

            if ($data['product_quantity'] > $product->quantity) {
                return back()->withInput()->withErrors([
                    'product_quantity' => "No hay suficiente stock de {$product->name}. Disponible: {$product->quantity}.",
                ]);
            }

            DB::transaction(function () use (&$data, $animal, $product) {
                $reasonType = match ($data['type']) {
                    'feeding' => 'Alimentación',
                    'vaccine' => 'Vacuna/Desparasitación',
                    default   => 'Uso veterinario',
                };

                $movement = $product->registerMovement(
                    'out',
                    (float) $data['product_quantity'],
                    "{$reasonType} - {$animal->name}"
                );

                $data['stock_movement_id'] = $movement->id;
                $data['animal_id'] = $animal->id;

                AnimalRecord::create($data);
            });
        } else {
            $data['animal_id'] = $animal->id;
            AnimalRecord::create($data);
        }

        return redirect()->route('animals.show', $animal)->with('success', 'Registro agregado');
    }

    public function destroy(Animal $animal, AnimalRecord $record)
    {
        abort_if($record->animal_id !== $animal->id, 404);

        try {
            DB::transaction(function () use ($animal, $record) {
                // Movimiento de cabezas: se deshace sobre el lote
                if (in_array($record->type, self::HEAD_TYPES, true) && $record->heads) {
                    $locked = Animal::lockForUpdate()->findOrFail($animal->id);
                    $locked->adjustHeads($record->type, (int) $record->heads, reverse: true);
                }

                // Descuento de inventario: se revierte
                if ($record->stock_movement_id && $record->product_id && $record->product_quantity) {
                    $product = Product::find($record->product_id);

                    if ($product) {
                        $product->registerMovement(
                            'in',
                            (float) $record->product_quantity,
                            "Reverso por eliminacion de registro - {$animal->name}"
                        );
                    }
                }

                $record->delete();
            });
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', 'No se puede eliminar este registro: dejaría el lote con cabezas negativas.');
        }

        return redirect()->route('animals.show', $animal)->with('success', 'Registro eliminado');
    }

    private function storeHeadMovement(array $data, Animal $animal)
    {
        if (!$animal->isLot()) {
            return back()->withInput()->withErrors(['type' => 'Este movimiento solo aplica a lotes.']);
        }

        if (empty($data['heads'])) {
            return back()->withInput()->withErrors(['heads' => 'Indica el número de cabezas.']);
        }

        try {
            DB::transaction(function () use ($data, $animal) {
                $locked = Animal::lockForUpdate()->findOrFail($animal->id);

                if ($data['type'] !== 'entry' && $data['heads'] > $locked->quantity) {
                    throw new \InvalidArgumentException("El lote solo tiene {$locked->quantity} cabezas.");
                }

                $locked->adjustHeads($data['type'], (int) $data['heads']);

                AnimalRecord::create([
                    'animal_id'   => $animal->id,
                    'type'        => $data['type'],
                    'recorded_at' => $data['recorded_at'],
                    'title'       => $data['title'] ?? null,
                    'heads'       => (int) $data['heads'],
                    'amount'      => $data['amount'] ?? null,
                    'notes'       => $data['notes'] ?? null,
                ]);
            });
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['heads' => $e->getMessage()]);
        }

        return redirect()->route('animals.show', $animal)->with('success', 'Movimiento registrado');
    }
}