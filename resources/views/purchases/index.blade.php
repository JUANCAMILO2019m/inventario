@extends('layouts.app')

@section('title', 'Compras')

@section('content')
    @php
        $money = fn ($v) => '$' . number_format((float) $v, 2);
        $input = 'rounded border border-gray-300 px-3 py-2';
        $filtered = request()->hasAny(['supplier', 'from', 'to', 'status']);
    @endphp

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Compras</h1>
        @can('modify-inventory')
            <a href="{{ route('purchases.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                + Registrar compra
            </a>
        @endcan
    </div>

    <form method="GET" action="{{ route('purchases.index') }}"
          class="bg-white shadow rounded p-4 mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-sm font-medium mb-1">Proveedor</label>
            <select name="supplier" class="{{ $input }}">
                <option value="">Todos</option>
                <option value="none" @selected(request('supplier') === 'none')>Sin proveedor</option>
                @foreach ($suppliers as $s)
                    <option value="{{ $s->id }}" @selected((string) request('supplier') === (string) $s->id)>{{ $s->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Desde</label>
            <input type="date" name="from" value="{{ request('from') }}" class="{{ $input }}">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Hasta</label>
            <input type="date" name="to" value="{{ request('to') }}" class="{{ $input }}">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Estado</label>
            <select name="status" class="{{ $input }}">
                <option value="active" @selected(request('status') !== 'voided')>Vigentes</option>
                <option value="voided" @selected(request('status') === 'voided')>Anuladas</option>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Filtrar</button>
            @if ($filtered)
                <a href="{{ route('purchases.index') }}" class="px-4 py-2 rounded border border-gray-300 hover:bg-gray-50">Limpiar</a>
            @endif
        </div>
    </form>

    <p class="text-sm text-gray-600 mb-2">
        Total de las compras listadas (todas las páginas): <span class="font-semibold">{{ $money($total) }}</span>
    </p>

    <div class="bg-white shadow rounded overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-gray-50 text-sm uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Compra</th>
                    <th class="px-4 py-3">Proveedor</th>
                    <th class="px-4 py-3">Factura</th>
                    <th class="px-4 py-3">Productos</th>
                    <th class="px-4 py-3">Total</th>
                    <th class="px-4 py-3">Registró</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($purchases as $p)
                    <tr class="{{ $p->isVoided() ? 'bg-gray-50 text-gray-400' : '' }}">
                        <td class="px-4 py-3 whitespace-nowrap">{{ $p->purchased_at->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('purchases.show', $p) }}" class="text-blue-600 hover:underline">#{{ $p->id }}</a>
                            @if ($p->isVoided())
                                <span class="ml-1 text-xs px-2 py-0.5 rounded bg-gray-200 text-gray-600">Anulada</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $p->supplier?->name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $p->invoice_number ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $p->items_count }}</td>
                        <td class="px-4 py-3 font-medium">{{ $money($p->total) }}</td>
                        <td class="px-4 py-3">{{ $p->user?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                            {{ $filtered ? 'Ninguna compra coincide con los filtros.' : 'Aún no hay compras registradas.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $purchases->links() }}</div>
@endsection