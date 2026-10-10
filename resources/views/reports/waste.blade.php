@extends('layouts.app')

@section('title', 'Pérdidas y mermas')

@section('content')
    @php
        $money = fn ($v) => '$' . number_format((float) $v, 2);
        $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.');
    @endphp

    <h1 class="text-2xl font-bold">Pérdidas y mermas</h1>
    <p class="text-gray-500 mb-4">
        Salidas de inventario registradas como pérdida, daño o vencido, valoradas al costo del día en que ocurrieron.
    </p>

    <form method="GET" action="{{ route('reports.waste') }}"
          class="bg-white shadow rounded p-4 mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-sm font-medium mb-1">Desde</label>
            <input type="date" name="from" value="{{ $from }}" class="rounded border border-gray-300 px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Hasta</label>
            <input type="date" name="to" value="{{ $to }}" class="rounded border border-gray-300 px-3 py-2">
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Ver</button>
    </form>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-4">
        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Valor perdido</p>
            <p class="text-2xl font-bold {{ $wasteValue > 0 ? 'text-red-600' : '' }}">{{ $money($wasteValue) }}</p>
        </div>
        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Movimientos de merma</p>
            <p class="text-2xl font-bold">{{ $waste->count() }}</p>
        </div>
        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Sobre el valor de todas las salidas</p>
            <p class="text-2xl font-bold">{{ $pct !== null ? $pct . '%' : '—' }}</p>
        </div>
    </div>

    @if ($missingCost > 0)
        <div class="mb-4 rounded bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-2">
            {{ $missingCost }} movimiento(s) pertenecen a productos sin precio: no suman valor, así que el total está subestimado.
        </div>
    @endif

    @if ($waste->isEmpty())
        <div class="bg-white shadow rounded p-8 text-center text-gray-500">
            No hay mermas registradas en este período. Para registrarlas, usa un movimiento de salida con motivo
            Pérdida, Daño o Vencido.
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <div class="bg-white shadow rounded overflow-x-auto">
                <h2 class="px-4 py-3 font-semibold border-b">Por motivo</h2>
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Motivo</th>
                            <th class="px-4 py-2">Movimientos</th>
                            <th class="px-4 py-2">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($byReason as $r)
                            <tr>
                                <td class="px-4 py-2 font-medium">{{ $r['label'] }}</td>
                                <td class="px-4 py-2">{{ $r['count'] }}</td>
                                <td class="px-4 py-2">{{ $money($r['value']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="bg-white shadow rounded overflow-x-auto">
                <h2 class="px-4 py-3 font-semibold border-b">Por producto</h2>
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Producto</th>
                            <th class="px-4 py-2">Cantidad</th>
                            <th class="px-4 py-2">Valor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($byProduct as $p)
                            <tr>
                                <td class="px-4 py-2 font-medium">
                                    @if ($p['product'])
                                        <a href="{{ route('products.movements.index', $p['product']) }}" class="hover:underline">{{ $p['name'] }}</a>
                                    @else
                                        {{ $p['name'] }}
                                    @endif
                                </td>
                                <td class="px-4 py-2">{{ $fmt($p['quantity']) }} {{ $p['unit'] }}</td>
                                <td class="px-4 py-2">{{ $money($p['value']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white shadow rounded overflow-x-auto">
            <h2 class="px-4 py-3 font-semibold border-b">
                Detalle {{ $waste->count() > 50 ? '(los 50 más recientes)' : '' }}
            </h2>
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-2">Fecha</th>
                        <th class="px-4 py-2">Producto</th>
                        <th class="px-4 py-2">Motivo</th>
                        <th class="px-4 py-2">Cantidad</th>
                        <th class="px-4 py-2">Valor</th>
                        <th class="px-4 py-2">Registró</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($details as $m)
                        <tr>
                            <td class="px-4 py-2 whitespace-nowrap">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2">{{ $m->product?->name ?? 'Producto eliminado' }}</td>
                            <td class="px-4 py-2">
                                {{ \App\Models\StockMovement::reasonLabel($m->reason_type) }}
                                @if ($m->reason)
                                    <span class="block text-xs text-gray-500">{{ $m->reason }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">{{ $fmt(abs($m->change)) }} {{ $m->product?->unit }}</td>
                            <td class="px-4 py-2">{{ $m->value() !== null ? $money($m->value()) : '—' }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $m->user?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection