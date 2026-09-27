<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Exports\ProductsExport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::with('category')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%' . $request->q . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                    ->orWhere('sku', 'like', $term)
                    ->orWhere('location', 'like', $term);
                });
            })
            ->when($request->filled('category'), function ($query) use ($request) {
                if ($request->category === 'none') {
                    $query->whereNull('category_id');
                } else {
                    $query->where('category_id', $request->category);
                }
            })
            ->when($request->boolean('low'), function ($query) {
                $query->whereColumn('quantity', '<=', 'min_stock');
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('products.index', [
            'products'   => $products,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function export(Request $request)
    {
        $products = Product::with('category')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%' . $request->q . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                    ->orWhere('sku', 'like', $term)
                    ->orWhere('location', 'like', $term);
                });
            })
            ->when($request->filled('category'), function ($query) use ($request) {
                if ($request->category === 'none') {
                    $query->whereNull('category_id');
                } else {
                    $query->where('category_id', $request->category);
                }
            })
            ->when($request->boolean('low'), function ($query) {
                $query->whereColumn('quantity', '<=', 'min_stock');
            })
            ->orderBy('name')
            ->get();

        $filename = 'productos_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(new ProductsExport($products), $filename);
    }

    public function create()
    {
        return view('products.create', [
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $initial = (int) $data['quantity'];
        $data['quantity'] = 0;
        unset($data['remove_photo']);

        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('products', 'public');
        }

        $product = Product::create($data);

        if ($initial > 0) {
            $product->registerMovement('in', $initial, 'Stock inicial');
        }

        return redirect()->route('products.index')
            ->with('success', 'Producto creado');
    }


    public function show(Product $product)
    {
        return redirect()->route('products.edit', $product);
    }

    public function edit(Product $product)
    {
        return view('products.edit', [
            'product' => $product,
            'categories' => Category::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request, $product);
        $newQuantity = (int) $data['quantity'];
        unset($data['quantity']);

        if ($request->boolean('remove_photo') && $product->photo) {
            Storage::disk('public')->delete($product->photo);
            $data['photo'] = null;
        }
        unset($data['remove_photo']);

        if ($request->hasFile('photo')) {
            if ($product->photo) {
                Storage::disk('public')->delete($product->photo);
            }
            $data['photo'] = $request->file('photo')->store('products', 'public');
        }

        $product->update($data);

        if ($newQuantity !== (int) $product->quantity) {
            $product->registerMovement('adjust', $newQuantity, 'Ajuste desde edición');
        }

        return redirect()->route('products.index')
            ->with('success', 'Producto actualizado');
    }
    
    public function destroy(Product $product)
    {
        if ($product->photo) {
            Storage::disk('public')->delete($product->photo);
        }
        $product->delete();

        return redirect()->route('products.index')
            ->with('success', 'Producto eliminado');
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        return $request->validate([
            'name'        => 'required|string|max:255',
            'category_id' => 'nullable|exists:categories,id',
            'sku'         => 'nullable|string|max:255|unique:products,sku,' . ($product?->id ?? 'NULL'),
            'quantity'    => 'required|integer|min:0',
            'min_stock'   => 'required|integer|min:0',
            'price'       => 'nullable|numeric|min:0',
            'location'    => 'nullable|string|max:255',
            'notes'       => 'nullable|string',
            'photo'       => 'nullable|image|max:2048',
            'remove_photo' => 'nullable|boolean',
        ]);
    }
}
