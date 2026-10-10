<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\AnimalRecord;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnimalRecordController extends Controller
{
    private const HEAD_TYPES = ['mortality', 'sale', 'entry', 'consumption'];

    public function store(Request $request, Animal $animal)
    {
        // Un animal individual vende siempre 1 cabeza
        if ($request->input('type') === 'sale' && !$animal->isLot()) {
            $request->merge(['heads' => 1]);
        }

        $data = $request->validate([
            'type'             => 'required|in:feeding,vaccine,weight,treatment,mortality,sale,entry',
            'recorded_at'      => 'required|date',
            'title'            => 'nullable|string|max:255',
            'notes'            => 'nullable|string',
            'product_id'       => 'nullable|exists:products,id',
            'product_quantity' => 'nullable|numeric|min:0.001|decimal:0,3',
            'next_due_date'    => 'nullable|date',
            'weight'           => 'required_if:type,weight|nullable|numeric|min:0.01|decimal:0,2',
            'weight_type'      => ['nullable', 'in:scale,estimated', 'required_if:type,weight', 'required_with:total_weight'],
            'heads'            => 'required_if:type,mortality,sale,entry|nullable|integer|min:1',
            'total_weight'     => 'required_if:price_mode,per_kg|nullable|numeric|min:0.01|decimal:0,2',
            'price_mode'       => 'nullable|in:per_kg,per_head',
            'unit_price'       => 'required_with:price_mode|nullable|numeric|min:0|decimal:0,2',
            'amount'           => 'nullable|numeric|min:0|decimal:0,2',
            'payment_status'   => 'required_if:type,sale|nullable|in:paid,pending,partial',
            'amount_paid'      => 'nullable|numeric|min:0|decimal:0,2',
        ], [
            'heads.required_if'          => 'Indica el número de cabezas.',
            'weight.required_if'         => 'El peso es obligatorio para este tipo de registro.',
            'weight_type.required_if'    => 'Indica si el peso fue de báscula o estimado.',
            'weight_type.required_with'  => 'Indica si el peso fue de báscula o estimado.',
            'total_weight.required_if'   => 'El peso total es obligatorio cuando el precio es por kilo.',
            'unit_price.required_with'   => 'Indica el precio unitario.',
            'payment_status.required_if' => 'Indica el estado de pago.',
        ], [
            'product_quantity' => 'cantidad usada',
            'total_weight'     => 'peso total',
            'unit_price'       => 'precio unitario',
            'amount'           => 'valor',
            'amount_paid'      => 'abono',
            'weight'           => 'peso',
        ]);

        return match ($data['type']) {
            'sale'               => $this->storeSale($data, $animal),
            'mortality', 'entry' => $this->storeHeadMovement($data, $animal),
            default              => $this->storeEvent($data, $animal),
        };
    }

    public function destroy(Animal $animal, AnimalRecord $record)
    {
        abort_if($record->animal_id !== $animal->id, 404);

        try {
            DB::transaction(function () use ($animal, $record) {
                // Movimientos de cabezas: se deshacen sobre el animal o lote
                if (in_array($record->type, self::HEAD_TYPES, true) && $record->heads) {
                    $locked = Animal::lockForUpdate()->findOrFail($animal->id);

                    if ($locked->isLot()) {
                        $locked->adjustHeads($record->type, (int) $record->heads, reverse: true);
                    } elseif ($record->type === 'sale') {
                        $otherSales = $locked->records()
                            ->where('type', 'sale')
                            ->where('id', '!=', $record->id)
                            ->exists();

                        if (!$otherSales && $locked->status === 'sold') {
                            $locked->update(['status' => 'active']);
                        }
                    }
                }

                // Descuento de inventario: se revierte
                if ($record->stock_movement_id && $record->product_id && $record->product_quantity) {
                    $product = Product::find($record->product_id);

                    if ($product) {
                        $product->registerMovement(
                            'in',
                            (float) $record->product_quantity,
                            "Reverso por eliminacion de registro - {$animal->name}",
                            'reversal'
                        );
                    }
                }

                $record->delete();
            });
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', 'No se puede eliminar este registro: dejaría el lote con cabezas negativas.');
        }

        return $this->done($animal, 'Registro eliminado');
    }

    public function addPayment(Request $request, Animal $animal, AnimalRecord $record)
    {
        abort_if($record->animal_id !== $animal->id || $record->type !== 'sale', 404);

        $data = $request->validate(
            ['payment' => 'required|numeric|gt:0|decimal:0,2'],
            [],
            ['payment' => 'abono']
        );

        $error = null;

        DB::transaction(function () use ($record, $data, &$error) {
            $locked = AnimalRecord::lockForUpdate()->findOrFail($record->id);
            $balance = round((float) $locked->amount - (float) $locked->amount_paid, 2);

            if ($balance <= 0) {
                $error = 'Esta venta ya está pagada.';
                return;
            }

            if ((float) $data['payment'] > $balance + 0.004) {
                $error = 'El abono supera el saldo pendiente ($' . number_format($balance, 2) . ').';
                return;
            }

            $paid = round((float) $locked->amount_paid + (float) $data['payment'], 2);

            $locked->update([
                'amount_paid'    => $paid,
                'payment_status' => $paid >= (float) $locked->amount ? 'paid' : 'partial',
            ]);
        });

        if ($error) {
            return back()->with('error', $error);
        }

        return $this->done($animal, 'Abono registrado');
    }

    /** Alimentación, vacuna, pesaje y tratamiento */
    private function storeEvent(array $data, Animal $animal)
    {
        $record = [
            'animal_id'     => $animal->id,
            'type'          => $data['type'],
            'recorded_at'   => $data['recorded_at'],
            'title'         => $data['title'] ?? null,
            'notes'         => $data['notes'] ?? null,
            'next_due_date' => $data['next_due_date'] ?? null,
        ];

        if ($data['type'] === 'weight') {
            $record['weight'] = $data['weight'];
            $record['weight_type'] = $data['weight_type'];
        }

        if (empty($data['product_id']) || empty($data['product_quantity'])) {
            AnimalRecord::create($record);

            return $this->done($animal, 'Registro agregado');
        }

        $product = Product::findOrFail($data['product_id']);

        if ($data['product_quantity'] > $product->quantity) {
            return back()->withInput()->withErrors([
                'product_quantity' => "No hay suficiente stock de {$product->name}. Disponible: {$product->quantity}.",
            ]);
        }

        DB::transaction(function () use ($record, $data, $animal, $product) {
            $reasonType = match ($data['type']) {
                'feeding' => 'Alimentación',
                'vaccine' => 'Vacuna/Desparasitación',
                default   => 'Uso veterinario',
            };

            $movement = $product->registerMovement(
                'out',
                (float) $data['product_quantity'],
                "{$reasonType} - {$animal->name}",
                'animal_use'
            );

            AnimalRecord::create($record + [
                'product_id'        => $product->id,
                'product_quantity'  => $data['product_quantity'],
                'stock_movement_id' => $movement->id,
                'unit_cost'         => $product->price !== null ? (float) $product->price : null,
            ]);
        });

        return $this->done($animal, 'Registro agregado');
    }

    /** Baja por mortalidad e ingreso de animales (solo lotes) */
    private function storeHeadMovement(array $data, Animal $animal)
    {
        if (!$animal->isLot()) {
            return back()->withInput()->withErrors(['type' => 'Este movimiento solo aplica a lotes.']);
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

        return $this->done($animal, 'Movimiento registrado');
    }

    /** Venta de un lote (parcial o total) o de un animal individual */
    private function storeSale(array $data, Animal $animal)
    {
        $heads = (int) $data['heads'];
        $amount = $data['amount'] ?? null;

        if ($amount === null && isset($data['unit_price'])) {
            $amount = match ($data['price_mode'] ?? null) {
                'per_kg'   => (float) ($data['total_weight'] ?? 0) * (float) $data['unit_price'],
                'per_head' => $heads * (float) $data['unit_price'],
                default    => null,
            };
        }

        if ($amount === null) {
            return back()->withInput()->withErrors(['amount' => 'Indica el valor de la venta o el precio unitario.']);
        }

        $amount = round((float) $amount, 2);

        $paid = match ($data['payment_status']) {
            'paid'    => $amount,
            'pending' => 0.0,
            default   => round((float) ($data['amount_paid'] ?? 0), 2),
        };

        if ($data['payment_status'] === 'partial' && ($paid <= 0 || $paid >= $amount)) {
            return back()->withInput()->withErrors([
                'amount_paid' => 'El abono inicial debe ser mayor que 0 y menor que el valor de la venta.',
            ]);
        }

        try {
            DB::transaction(function () use ($data, $animal, $heads, $amount, $paid) {
                $locked = Animal::lockForUpdate()->findOrFail($animal->id);

                if ($locked->isLot()) {
                    if ($heads > $locked->quantity) {
                        throw new \InvalidArgumentException("El lote solo tiene {$locked->quantity} cabezas.");
                    }

                    $locked->adjustHeads('sale', $heads);
                } else {
                    if ($locked->status !== 'active') {
                        throw new \InvalidArgumentException('Este animal ya no está activo.');
                    }

                    $locked->update(['status' => 'sold']);
                }

                $hasWeight = isset($data['total_weight']);
                $hasMode = isset($data['price_mode']);

                AnimalRecord::create([
                    'animal_id'      => $animal->id,
                    'type'           => 'sale',
                    'recorded_at'    => $data['recorded_at'],
                    'title'          => $data['title'] ?? null,
                    'heads'          => $heads,
                    'total_weight'   => $hasWeight ? $data['total_weight'] : null,
                    'weight_type'    => $hasWeight ? ($data['weight_type'] ?? null) : null,
                    'price_mode'     => $hasMode ? $data['price_mode'] : null,
                    'unit_price'     => $hasMode ? ($data['unit_price'] ?? null) : null,
                    'amount'         => $amount,
                    'payment_status' => $data['payment_status'],
                    'amount_paid'    => $paid,
                    'notes'          => $data['notes'] ?? null,
                ]);
            });
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return $this->done($animal, 'Venta registrada');
    }

    private function done(Animal $animal, string $message)
    {
        return redirect()->route('animals.show', $animal)->with('success', $message);
    }
}