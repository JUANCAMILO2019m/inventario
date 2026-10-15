@extends('layouts.app')

@section('title', $expense ? 'Editar gasto' : 'Registrar gasto')

@section('content')
    @php
        $input = 'w-full rounded border border-gray-300 px-3 py-2';
        $selectedAnimal = old('animal_id', $expense->animal_id ?? $preAnimal ?? '');
        $active = $animals->where('status', 'active');
        $closed = $animals->where('status', '!=', 'active');
    @endphp

    <h1 class="text-2xl font-bold mb-4">{{ $expense ? 'Editar gasto' : 'Registrar gasto' }}</h1>

    <form action="{{ $expense ? route('expenses.update', $expense) : route('expenses.store') }}" method="POST"
            class="bg-white shadow rounded p-6 max-w-2xl">
        @csrf
        @if ($expense) @method('PUT') @endif
        @if ($back) <input type="hidden" name="back" value="{{ $back }}"> @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1">Fecha *</label>
                <input type="date" name="spent_at" value="{{ old('spent_at', isset($expense) ? $expense->spent_at->format('Y-m-d') : date('Y-m-d')) }}"
                        max="{{ date('Y-m-d') }}" required class="{{ $input }}">
                @error('spent_at') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Categoría *</label>
                <select name="category" required class="{{ $input }}">
                    @foreach (\App\Models\Expense::CATEGORIES as $key => $label)
                        <option value="{{ $key }}" @selected(old('category', $expense->category ?? 'other') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('category') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Valor *</label>
                <input type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount', $expense->amount ?? '') }}"
                        required class="{{ $input }}">
                @error('amount') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Proveedor (opcional)</label>
                <select name="supplier_id" class="{{ $input }}">
                    <option value="">— Ninguno —</option>
                    @foreach ($suppliers as $s)
                        <option value="{{ $s->id }}" @selected((string) old('supplier_id', $expense->supplier_id ?? '') === (string) $s->id)>{{ $s->name }}</option>
                    @endforeach
                </select>
                @error('supplier_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">Descripción</label>
                <input type="text" name="description" maxlength="255" value="{{ old('description', $expense->description ?? '') }}"
                        placeholder="Ej: Visita del veterinario, jornal de la semana, flete de concentrado"
                        class="{{ $input }}">
                @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">Asignar a</label>
                <select name="animal_id" class="{{ $input }}">
                    <option value="">— General (no asignado a un animal o lote) —</option>
                    @if ($active->isNotEmpty())
                        <optgroup label="Activos">
                            @foreach ($active as $a)
                                <option value="{{ $a->id }}" @selected((string) $selectedAnimal === (string) $a->id)>
                                    {{ $a->name }} ({{ $a->type === 'lot' ? 'lote' : 'individual' }})
                                </option>
                            @endforeach
                        </optgroup>
                    @endif
                    @if ($closed->isNotEmpty())
                        <optgroup label="Vendidos o dados de baja">
                            @foreach ($closed as $a)
                                <option value="{{ $a->id }}" @selected((string) $selectedAnimal === (string) $a->id)>
                                    {{ $a->name }} ({{ $a->status === 'sold' ? 'vendido' : 'baja' }})
                                </option>
                            @endforeach
                        </optgroup>
                    @endif
                </select>
                <p class="text-xs text-gray-500 mt-1">
                    Si lo asignas, se suma a los costos de ese animal o lote y baja su resultado. Si es general,
                    solo aparece aquí y en la gráfica del dashboard.
                </p>
                @error('animal_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded">Guardar</button>
            <a href="{{ $back === 'animal' && $preAnimal ? route('animals.show', $preAnimal) : route('expenses.index') }}"
                class="px-5 py-2 rounded border border-gray-300 hover:bg-gray-50">Cancelar</a>
        </div>
    </form>
@endsection