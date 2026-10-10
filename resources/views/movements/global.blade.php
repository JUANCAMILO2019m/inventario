@extends('layouts.app')

@section('title', 'Historial de movimientos')

@section('content')
    @php
        $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.');
        $typeLabel = fn ($t) => match ($t) {
            'in' => 'Entrada',
            'out' => 'Salida',
            default => 'Ajuste',
        };
        $reasonLabel = fn ($m) => $m->reason_type
            ? \App\Models\StockMovement::reasonLabel($m->reason_type)
            : ($m->reason ?? '—');
        $filtered = request()->hasAny(['q', 'type', 'reason_type', 'from', 'to', 'user']);
        $input = 'rounded border border-gray-300 px-3 py-2';
    @endphp

    <h1 class="text-2xl font-bold mb-4">Historial de movimientos</h1>

    <form method="GET" action="{{ route('movements.index') }}"
          class="bg-white shadow rounded p-4 mb-4 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[180px]">
            <label class="block text-sm font-medium mb-1">Producto</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Nombre del producto"
                   class="w-full {{ $input }}">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Tipo</label>
            <select name="type" class="{{ $input }}">
                <option value="">Todos</option>
                <option value="in" @selected(request('type') === 'in')>Entrada</option>
                <option value="out" @selected(request('type') === 'out')>Salida</option>
                <option value="adjust" @selected(request('type') === 'adjust')>Ajuste</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Motivo</label>
            <select name="reason_type" class="{{ $input }}">
                <option value="">Todos</option>
                @foreach (\App\Models\StockMovement::allReasons() as $key => $label)
                    <option value="{{ $key }}" @selected(request('reason_type') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        @can('manage-users')
            <div>
                <label class="block text-sm font-medium mb-1">Usuario</label>
                <select name="user" class="{{ $input }}">
                    <option value="">Todos</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected((string) request('user') === (string) $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
        @endcan

        <div>
            <label class="block text-sm font-medium mb-1">Desde</label>
            <input type="date" name="from" value="{{ request('from') }}" class="{{ $input }}">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Hasta</label>
            <input type="date" name="to" value="{{ request('to') }}" class="{{ $input }}">
        </div>

        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Filtrar</button>
            @if ($filtered)
                <a href="{{ route('movements.index') }}" class="px-4 py-2 rounded border border-gray-300 hover:bg-gray-50">Limpiar</a>
            @endif
            <a href="{{ route('movements.export', request()->query()) }}"
               class="px-4 py-2 rounded border border-green-600 text-green-700 hover:bg-green-50">
                Exportar CSV
            </a>
        </div>
    </form>

    {{-- Tabla --}}
    <div class="hidden md:block bg-white shadow rounded overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-gray-50 text-sm uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Producto</th>
                    <th class="px-4 py-3">Tipo</th>
                    <th class="px-4 py-3">Motivo</th>
                    <th class="px-4 py-3">Cambio</th>
                    <th class="px-4 py-3">Quedó en</th>
                    <th class="px-4 py-3">Registró</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($movements as $m)
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">
                            @if ($m->product)
                                <a href="{{ route('products.movements.index', $m->product) }}" class="text-blue-600 hover:underline">
                                    {{ $m->product->name }}
                                </a>
                            @else
                                <span class="text-gray-400">Producto eliminado</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $typeLabel($m->type) }}</td>
                        <td class="px-4 py-3">
                            {{ $reasonLabel($m) }}
                            @if ($m->reason_type && $m->reason)
                                <span class="block text-xs text-gray-500">{{ $m->reason }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-semibold {{ $m->change >= 0 ? 'text-green-700' : 'text-red-700' }}">
                            {{ $m->change > 0 ? '+' : '' }}{{ $fmt($m->change) }}
                        </td>
                        <td class="px-4 py-3">{{ $fmt($m->quantity_after) }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $m->user?->name ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                            {{ $filtered ? 'Ningún movimiento coincide con los filtros.' : 'Aún no hay movimientos registrados.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Tarjetas (móvil) --}}
    <div class="md:hidden space-y-3">
        @forelse ($movements as $m)
            <div class="bg-white shadow rounded p-4">
                <div class="flex items-center justify-between gap-2">
                    @if ($m->product)
                        <a href="{{ route('products.movements.index', $m->product) }}"
                           class="font-semibold text-blue-600 hover:underline truncate">{{ $m->product->name }}</a>
                    @else
                        <span class="font-semibold text-gray-400">Producto eliminado</span>
                    @endif
                    <span class="font-semibold {{ $m->change >= 0 ? 'text-green-700' : 'text-red-700' }}">
                        {{ $m->change > 0 ? '+' : '' }}{{ $fmt($m->change) }}
                    </span>
                </div>
                <p class="text-sm text-gray-500 mt-1">
                    {{ $typeLabel($m->type) }} · {{ $reasonLabel($m) }} · {{ $m->created_at->format('d/m/Y H:i') }}
                    · Quedó en {{ $fmt($m->quantity_after) }}
                    @if ($m->user) · {{ $m->user->name }} @endif
                </p>
                @if ($m->reason_type && $m->reason)
                    <p class="text-sm text-gray-600 mt-1">{{ $m->reason }}</p>
                @endif
            </div>
        @empty
            <div class="bg-white shadow rounded p-8 text-center text-gray-500">
                {{ $filtered ? 'Ningún movimiento coincide con los filtros.' : 'Aún no hay movimientos registrados.' }}
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $movements->links() }}</div>
@endsection