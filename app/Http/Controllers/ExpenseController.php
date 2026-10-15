<?php

namespace App\Http\Controllers;

use App\Models\Animal;
use App\Models\Expense;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $this->validateFilters($request);

        $base = $this->filtered($request);

        $total = (float) (clone $base)->sum('amount');
        $count = (clone $base)->count();

        $byCategory = (clone $base)
            ->selectRaw('category, SUM(amount) as total, COUNT(*) as n')
            ->groupBy('category')
            ->orderByDesc('total')
            ->get();

        $expenses = $base->with(['animal', 'supplier', 'user'])
            ->latest('spent_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('expenses.index', [
            'expenses'   => $expenses,
            'total'      => $total,
            'count'      => $count,
            'byCategory' => $byCategory,
            'animals'    => Animal::orderBy('name')->get(['id', 'name']),
            'suppliers'  => Supplier::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function export(Request $request)
    {
        $this->validateFilters($request);

        $expenses = $this->filtered($request)
            ->with(['animal', 'supplier', 'user'])
            ->latest('spent_at')
            ->latest('id')
            ->get();

        $filename = 'gastos_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () use ($expenses) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, ['Fecha', 'Categoría', 'Descripción', 'Valor', 'Animal / Lote', 'Proveedor', 'Registró']);

            foreach ($expenses as $e) {
                fputcsv($handle, [
                    $e->spent_at->format('d/m/Y'),
                    Expense::categoryLabel($e->category),
                    $e->description ?? '',
                    $e->amount,
                    $e->animal?->name ?? 'General',
                    $e->supplier?->name ?? '',
                    $e->user?->name ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function create(Request $request)
    {
        return view('expenses.form', [
            'expense'   => null,
            'animals'   => $this->animalOptions(),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'preAnimal' => $request->filled('animal_id')
                ? Animal::whereKey((int) $request->query('animal_id'))->value('id')
                : null,
            'back'      => $request->query('back'),
        ]);
    }

    public function store(Request $request)
    {
        $expense = Expense::create($this->validated($request));

        if ($request->input('back') === 'animal' && $expense->animal_id) {
            return redirect()->route('animals.show', $expense->animal_id)->with('success', 'Gasto registrado');
        }

        return redirect()->route('expenses.index')->with('success', 'Gasto registrado');
    }

    public function edit(Expense $expense)
    {
        return view('expenses.form', [
            'expense'   => $expense,
            'animals'   => $this->animalOptions(),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'preAnimal' => null,
            'back'      => null,
        ]);
    }

    public function update(Request $request, Expense $expense)
    {
        $expense->update($this->validated($request));

        return redirect()->route('expenses.index')->with('success', 'Gasto actualizado');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return redirect()->route('expenses.index')->with('success', 'Gasto eliminado');
    }

    private function filtered(Request $request): Builder
    {
        return Expense::query()
            ->when($request->filled('from'), fn ($q) => $q->whereDate('spent_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('spent_at', '<=', $request->input('to')))
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->input('category')))
            ->when($request->filled('animal'), function ($q) use ($request) {
                $request->input('animal') === 'none'
                    ? $q->whereNull('animal_id')
                    : $q->where('animal_id', (int) $request->input('animal'));
            })
            ->when($request->filled('supplier'), fn ($q) => $q->where('supplier_id', (int) $request->input('supplier')))
            ->when($request->filled('q'), fn ($q) => $q->where('description', 'like', '%' . $request->input('q') . '%'));
    }

    private function validateFilters(Request $request): void
    {
        $request->validate([
            'from'     => 'nullable|date',
            'to'       => 'nullable|date|after_or_equal:from',
            'category' => ['nullable', Rule::in(array_keys(Expense::CATEGORIES))],
            'animal'   => 'nullable|string|max:20',
            'supplier' => 'nullable|integer',
            'q'        => 'nullable|string|max:100',
        ]);
    }

    private function animalOptions()
    {
        return Animal::orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'name', 'status', 'type']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'spent_at'    => 'required|date|before_or_equal:today',
            'category'    => ['required', Rule::in(array_keys(Expense::CATEGORIES))],
            'description' => 'nullable|string|max:255',
            'amount'      => 'required|numeric|gt:0|decimal:0,2|max:999999999999',
            'animal_id'   => 'nullable|exists:animals,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
        ], [
            'spent_at.before_or_equal' => 'La fecha del gasto no puede ser futura.',
            'amount.gt'                => 'El valor debe ser mayor que 0.',
        ], [
            'spent_at' => 'fecha',
            'category' => 'categoría',
            'amount'   => 'valor',
        ]);
    }
}