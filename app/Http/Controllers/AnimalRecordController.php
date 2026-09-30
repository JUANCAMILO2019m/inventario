<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\AnimalRecord;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnimalRecordController extends Controller
{
    public function store(Request $request, Animal $animal)
    {
        $data = $request->validate([
            'type'         => 'required|in:feeding,vaccine,weight,treatment',
            'recorded_at'  => 'required|date',
            'title'        => 'nullable|string|max:255',
            'product_id'   => 'nullable|exists:products,id',
            'product_quantity' => 'nullable|numeric|min:0.001|decimal:0,3',
            'weight'       => 'nullable|numeric|min:0|decimal:0,2',
            'next_due_date'=> 'nullable|date',
            'notes'        => 'nullable|string',
        ]);

        // El peso es obligatorio solo para registros de tipo "weight"
        if ($data['type'] === 'weight' && empty($data['weight'])) {
            return back()->withInput()->withErrors(['weight' => 'El peso es obligatorio para este tipo de registro.']);
        }

        // Si eligió un producto e indicó cantidad, se descuenta del inventario
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
        // Si el registro tenia un descuento de inventario, lo revertimos
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

        return redirect()->route('animals.show', $animal)->with('success', 'Registro eliminado');
    }
}