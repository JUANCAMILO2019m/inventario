<?php

namespace App\Http\Controllers;

use App\Exports\AnimalsExport;
use App\Models\Animal;
use App\Models\Product;
use App\Services\ImageStorage;
use App\Services\AnimalCharts;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class AnimalController extends Controller
{
    public function index(Request $request)
    {
        $animals = $this->filteredQuery($request)
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('animals.index', compact('animals'));
    }

    public function export(Request $request)
    {
        $animals = $this->filteredQuery($request)
            ->with('records.product')
            ->orderBy('name')
            ->get();

        return Excel::download(
            new AnimalsExport($animals),
            'animales_' . now()->format('Y-m-d_His') . '.xlsx'
        );
    }

    public function create()
    {
        return view('animals.create');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        unset($data['photo'], $data['remove_photo']);

        if ($data['type'] === 'individual') {
            $data['quantity'] = 1;
        } else {
            $data['initial_quantity'] = $data['quantity'];
        }

        if ($request->hasFile('photo')) {
            $data['photo'] = ImageStorage::store($request->file('photo'), 'animals');
        }

        Animal::create($data);

        return redirect()->route('animals.index')->with('success', 'Animal registrado');
    }

    public function show(Animal $animal)
    {
        $records = $animal->records()
            ->with(['product', 'user'])
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->paginate(15);

        $products = Product::orderBy('name')->get();
        $stats = $animal->stats();
        $buyers = $animal->isLot() ? $animal->buyerSummary() : collect();
        $faenas = $animal->isLot() ? $animal->faenaSummary() : collect();

        $charts = [
            'weight' => AnimalCharts::weight($animal),
            'heads'  => $animal->isLot() ? AnimalCharts::heads($animal) : null,
            'feed'   => AnimalCharts::feed($animal),
        ];

        return view('animals.show', compact('animal', 'records', 'products', 'stats', 'buyers', 'faenas', 'charts'));
    }
    
    public function edit(Animal $animal)
    {
        return view('animals.edit', compact('animal'));
    }

    public function update(Request $request, Animal $animal)
    {
        $data = $this->validated($request, $animal);
        unset($data['photo'], $data['remove_photo']);

        if ($animal->isLot()) {
            // Mientras no haya movimientos, la cantidad se puede corregir.
            // Con movimientos, solo cambia registrándolos desde la ficha.
            if (!$animal->hasHeadMovements() && isset($data['quantity'])) {
                $data['initial_quantity'] = $data['quantity'];
            } else {
                unset($data['quantity']);
            }
        }

        if ($request->hasFile('photo')) {
            $newPhoto = ImageStorage::store($request->file('photo'), 'animals');
            ImageStorage::delete($animal->photo);
            $data['photo'] = $newPhoto;
        } elseif ($request->boolean('remove_photo') && $animal->photo) {
            ImageStorage::delete($animal->photo);
            $data['photo'] = null;
        }

        $animal->update($data);

        return redirect()->route('animals.show', $animal)->with('success', 'Animal actualizado');
    }

    public function destroy(Animal $animal)
    {
        ImageStorage::delete($animal->photo);

        $animal->delete();

        return redirect()->route('animals.index')->with('success', 'Animal eliminado');
    }

    private function filteredQuery(Request $request): Builder
    {
        return Animal::query()
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%' . $request->q . '%';
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                      ->orWhere('code', 'like', $term)
                      ->orWhere('species', 'like', $term);
                });
            })
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status));
    }

    private function validated(Request $request, ?Animal $animal = null): array
    {
        $type = $animal?->type ?? $request->input('type');

        $rules = [
            'name'           => 'required|string|max:255',
            'code'           => ['nullable', 'string', 'max:255', Rule::unique('animals', 'code')->ignore($animal?->id)],
            'species'        => 'required|string|max:255',
            'breed'          => 'nullable|string|max:255',
            'sex'            => 'nullable|in:male,female,mixed',
            'birth_date'     => 'nullable|date',
            'status'         => 'required|in:active,sold,dead',
            'description'    => 'nullable|string',
            'photo'          => 'nullable|image|max:2048',
            'remove_photo'   => 'nullable|boolean',
            'supplier'       => 'nullable|string|max:255',
            'purchase_cost'  => 'nullable|numeric|min:0|decimal:0,2',
            'entry_date'     => 'nullable|date',
            'initial_weight' => 'nullable|numeric|min:0|decimal:0,2',
        ];

        if (!$animal) {
            $rules['type'] = 'required|in:individual,lot';
        }

        if ($type === 'lot') {
            $rules['quantity'] = $animal ? 'nullable|integer|min:1' : 'required|integer|min:1';
        }

        return $request->validate($rules);
    }
}