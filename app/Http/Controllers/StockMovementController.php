<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StockMovementController extends Controller
{
    public function index(Product $product)
    {
        $movements = $product->movements()->with('user')->latest('id')->paginate(20);

        return view('movements.index', compact('product', 'movements'));
    }

    public function store(Request $request, Product $product)
    {
        $type = $request->input('type');

        $data = $request->validate([
            'type'        => 'required|in:in,out,adjust',
            'amount'      => ['required', 'numeric', 'decimal:0,3', $type === 'adjust' ? 'min:0' : 'gt:0'],
            'reason_type' => ['required', Rule::in(array_keys(StockMovement::reasonsFor($type)))],
            'reason'      => 'nullable|string|max:255',
        ], [
            'reason_type.required' => 'Elige el motivo del movimiento.',
            'reason_type.in'       => 'El motivo no corresponde al tipo de movimiento.',
        ]);

        if ($data['type'] === 'out' && $data['amount'] > $product->quantity) {
            return back()->withInput()->withErrors([
                'amount' => "No hay suficiente stock. Disponible: {$product->quantity}.",
            ]);
        }

        try {
            $product->registerMovement(
                $data['type'],
                (float) $data['amount'],
                $data['reason'] ?? null,
                $data['reason_type']
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }

        return redirect()->route('products.movements.index', $product)
            ->with('success', 'Movimiento registrado');
    }
}