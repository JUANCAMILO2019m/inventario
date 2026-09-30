<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use Illuminate\Http\Request;

class AnimalController extends Controller
{
    public function index(Request $request)
    {
        $animals = Animal::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%' . $request->q . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term)
                        ->orWhere('species', 'like', $term);
                });
            })
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('animals.index', compact('animals'));
    }

    public function create()
    {
        return view('animals.create');
    }

    public function store(Request $request)
    {
        Animal::create($this->validated($request));

        return redirect()->route('animals.index')->with('success', 'Animal registrado');
    }

    public function show(Animal $animal)
    {
        $records = $animal->records()
            ->with('product')
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->paginate(15);

        $products = \App\Models\Product::orderBy('name')->get();

        return view('animals.show', compact('animal', 'records', 'products'));
    }

    public function edit(Animal $animal)
    {
        return view('animals.edit', compact('animal'));
    }

    public function update(Request $request, Animal $animal)
    {
        $animal->update($this->validated($request));

        return redirect()->route('animals.show', $animal)->with('success', 'Animal actualizado');
    }

    public function destroy(Animal $animal)
    {
        $animal->delete();

        return redirect()->route('animals.index')->with('success', 'Animal eliminado');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'type'        => 'required|in:individual,lot',
            'name'        => 'required|string|max:255',
            'code'        => 'nullable|string|max:255|unique:animals,code,' . ($this->currentAnimalId($request) ?? 'NULL'),
            'species'     => 'required|string|max:255',
            'breed'       => 'nullable|string|max:255',
            'sex'         => 'nullable|in:male,female,mixed',
            'birth_date'  => 'nullable|date',
            'quantity'    => 'required|integer|min:1',
            'status'      => 'required|in:active,sold,dead',
            'description' => 'nullable|string',
        ]);
    }

    private function currentAnimalId(Request $request): ?int
    {
        return $request->route('animal')?->id;
    }
}