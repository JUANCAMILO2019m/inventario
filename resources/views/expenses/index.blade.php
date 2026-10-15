@extends('layouts.app')

@section('title', 'Gastos')

@section('content')
    @php
        $money = fn ($v) => '$' . number_format((float) $v, 2);
        $input = 'rounded border border-gray-300 px-3 py-2';
        $filtered = request()->hasAny(['from', 'to', 'category', 'animal', 'supplier', 'q']);
    @endphp

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Gastos</h1>
        @can('modify-inventory')
            <a href="{{ route('expenses.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                + Registrar gasto
            </a>
        @endcan
    </div>

    <form method="GET" action="{{ route('expenses.index') }}"
          class="bg-white shadow rounded p-4 mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-sm font-medium mb-1">Desde</label>
            <input type="date" name="from" value="{{ request('from') }}" class="{{ $input }}">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Hasta</label>
            <input type="date" name="to" value="{{ request('to') }}" class="{{ $input }}">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Categoría</label>
            <select name="category" class="{{ $input }}">
                <option value="">Todas</option>
                @foreach (\App\Models\Expense::CATEGORIES as $key => $label)
                    <option value="{{ $key }}" @selected(request('category') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Animal / Lote</label>
            <select name="animal" class="{{ $input }}">
                <option value="">Todos</option>
                <option value="none" @selected(request('animal') === 'none')>Generales (sin asignar)</option>
                @foreach ($animals as $a)
                    <option value="{{ $a->id }}" @selected((string) request('animal') === (string) $a->id)>{{ $a->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Proveedor</label>
            <select name="supplier" class="{{ $input }}">
                <option value="">Todos</option>
                @foreach ($suppliers as $s)
                    <option value="{{ $s->id }}" @selected((string) request('supplier') === (string) $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex-1 min-w-[160px]">
            <label class="block text-sm font-medium mb-1">Buscar</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="En la descripción"
                   class="w-full {{ $input }}">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Filtrar</button>
            @if ($filtered)
                <a href="{{ route('expenses.index') }}" class="px-4 py-2 rounded border border-gray-300 hover:bg-gray-50">Limpiar</a>
            @endif
            <a href="{{ route('expenses.export', request()->query()) }}"
               class="px-4 py-2 rounded border border-green-600 text-green-700 hover:bg-green-50">Exportar CSV</a>
        </div>
    </form>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-4">
        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Total de los gastos listados (todas las páginas)</p>
            <p class="text-2xl font-bold">{{ $money($total) }}</p>
            <p class="text-xs text-gray-500 mt-1">{{ $count }} {{ $count === 1 ? 'gasto' : 'gastos' }}</p>
        </div>

        <div class="bg-white shadow rounded p-4 lg:col-span-2">
            <p class="text-sm font-semibold mb-2">Por categoría</p>
            @forelse ($byCategory as $c)
                @php $pct = $total > 0 ? round($c->total / $total * 100) : 0; @endphp
                <div class="mb-2">
                    <div class="flex justify-between text-sm">
                        <span>{{ \App\Models\Expense::categoryLabel($c->category) }}
                            <span class="text-gray-400">({{ $c->n }})</span></span>
                        <span class="font-medium">{{ $money($c->total) }} · {{ $pct }}%</span>
                    </div>
                    <div class="h-2 bg-gray-100 rounded">
                        <div class="h-2 bg-blue-400 rounded" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500">Sin datos para mostrar.</p>
            @endforelse
        </div>
    </div>

    <div class="bg-white shadow rounded overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Categoría</th>
                    <th class="px-4 py-3">Descripción</th>
                    <th class="px-4 py-3">Animal / Lote</th>
                    <th class="px-4 py-3">Proveedor</th>
                    <th class="px-4 py-3">Valor</th>
                    <th class="px-4 py-3">Registró</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($expenses as $e)
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $e->spent_at->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">{{ \App\Models\Expense::categoryLabel($e->category) }}</td>
                        <td class="px-4 py-3">{{ $e->description ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($e->animal)
                                <a href="{{ route('animals.show', $e->animal) }}" class="text-blue-600 hover:underline">{{ $e->animal->name }}</a>
                            @else
                                <span class="text-gray-400">General</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $e->supplier?->name ?? '—' }}</td>
                        <td class="px-4 py-3 font-medium">{{ $money($e->amount) }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $e->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @can('modify-inventory')
                                <a href="{{ route('expenses.edit', $e) }}" class="text-blue-600 hover:underline">Editar</a>
                                <form action="{{ route('expenses.destroy', $e) }}" method="POST" class="inline"
                                      onsubmit="return confirm('¿Eliminar este gasto de {{ $money($e->amount) }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-red-600 hover:underline ml-3">Eliminar</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                            {{ $filtered ? 'Ningún gasto coincide con los filtros.' : 'Aún no hay gastos registrados.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $expenses->links() }}</div>
@endsection