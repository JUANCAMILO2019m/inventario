<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    public function index(Product $product)
    {
        $movements = $product->movements()->latest('id')->paginate(20);

        return view('movements.index', compact('product', 'movements'));
    }

    public function store(Request $request, Product $product)
    {
        $data = $request->validate([
            'type'   => 'required|in:in,out,adjust',
            'amount' => ['required', 'integer', $request->type === 'adjust' ? 'min:0' : 'min:1'],
            'reason' => 'nullable|string|max:255',
        ]);

        if ($data['type'] === 'out' && $data['amount'] > $product->quantity) {
            return back()->withInput()->withErrors([
                'amount' => "No hay suficiente stock. Disponible: {$product->quantity}.",
            ]);
        }

        $product->registerMovement($data['type'], (int) $data['amount'], $data['reason'] ?? null);

        return redirect()->route('products.movements.index', $product)
            ->with('success', 'Movimiento registrado');
    }
}
