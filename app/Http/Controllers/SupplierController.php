<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::query()
            ->withCount(['purchases as purchases_count' => fn ($q) => $q->whereNull('voided_at')])
            ->withSum(['purchases as purchases_total' => fn ($q) => $q->whereNull('voided_at')], 'total')
            ->orderBy('name')
            ->paginate(20);

        return view('suppliers.index', compact('suppliers'));
    }

    public function create()
    {
        return view('suppliers.form', ['supplier' => null]);
    }

    public function store(Request $request)
    {
        $supplier = Supplier::create($this->validated($request));

        if ($request->wantsJson()) {
            return response()->json($supplier);
        }

        return redirect()->route('suppliers.index')->with('success', 'Proveedor creado');
    }

    public function edit(Supplier $supplier)
    {
        return view('suppliers.form', compact('supplier'));
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update($this->validated($request, $supplier));

        return redirect()->route('suppliers.index')->with('success', 'Proveedor actualizado');
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->purchases()->exists()) {
            return back()->with('error', 'Este proveedor tiene compras registradas y no se puede eliminar.');
        }

        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', 'Proveedor eliminado');
    }

    private function validated(Request $request, ?Supplier $supplier = null): array
    {
        return $request->validate([
            'name'         => ['required', 'string', 'max:255', Rule::unique('suppliers', 'name')->ignore($supplier?->id)],
            'contact_name' => 'nullable|string|max:255',
            'phone'        => 'nullable|string|max:50',
            'email'        => 'nullable|email|max:255',
            'tax_id'       => 'nullable|string|max:50',
            'notes'        => 'nullable|string|max:1000',
        ]);
    }
}