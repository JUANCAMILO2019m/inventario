@extends('layouts.app')

@section('title', 'Vencimientos')

@section('content')
    @php
        $money = fn ($v) => '$' . number_format((float) $v, 2);
        $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.');
        $input = 'rounded border border-gray-300 px-3 py-2';
        $selected = request('days', '60');
        $badge = ['expired' => 'bg-red-100 text-red-700', 'soon' => 'bg-amber-100 text-amber-700', 'ok' => 'bg-green-100 text-green-700'];
        $plural = fn ($n) => abs($n) === 1 ? 'día' : 'días';
        $statusText = fn ($r) => $r['status'] === 'expired'
            ? 'Vencido hace ' . abs($r['days']) . ' ' . $plural($r['days'])
            : ($r['days'] === 0 ? 'Vence hoy' : 'Vence en ' . $r['days'] . ' ' . $plural($r['days']));
    @endphp

    <h1 class="text-2xl font-bold mb-1">Vencimientos</h1>
    <p class="text-gray-500 mb-4">Lotes de productos con fecha de vencimiento que siguen en tu inventario.</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Vencidos</p>
            <p class="text-2xl font-bold {{ $summary['expired_count'] ? 'text-red-600' : '' }}">{{ $summary['expired_count'] }}</p>
            <p class="text-xs text-gray-500 mt-1">Valor estimado: {{ $money($summary['expired_value']) }}</p>
        </div>
        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Vencen en los próximos 30 días</p>
            <p class="text-2xl font-bold {{ $summary['soon_count'] ? 'text-amber-600' : '' }}">{{ $summary['soon_count'] }}</p>
            <p class="text-xs text-gray-500 mt-1">Valor estimado: {{ $money($summary['soon_value']) }}</p>
        </div>
    </div>

    <form method="GET" action="{{ route('expirations.index') }}" class="mb-3 flex items-center gap-2">
        <label class="text-sm font-medium">Mostrar lo que vence en</label>
        <select name="days" onchange="this.form.submit()" class="{{ $input }} text-sm">
            @foreach (['15' => '15 días', '30' => '30 días', '60' => '60 días', '90' => '90 días', 'all' => 'cualquier fecha'] as $v => $label)
                <option value="{{ $v }}" @selected($selected === (string) $v)>{{ $label }}</option>
            @endforeach
        </select>
    </form>

    <div class="bg-white shadow rounded overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Producto</th>
                    <th class="px-4 py-3">Lote</th>
                    <th class="px-4 py-3">Cantidad estimada</th>
                    <th class="px-4 py-3">Vence</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Valor</th>
                    <th class="px-4 py-3">Origen</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($rows as $r)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $r['product']->name }}</td>
                        <td class="px-4 py-3">{{ $r['batch']->lot_code ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $fmt($r['quantity']) }} {{ $r['product']->unit }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $r['batch']->expires_on->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs px-2 py-1 rounded {{ $badge[$r['status']] }}">{{ $statusText($r) }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $r['value'] !== null ? $money($r['value']) : '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($r['batch']->item)
                                <a href="{{ route('purchases.show', $r['batch']->item->purchase_id) }}" class="text-blue-600 hover:underline">
                                    Compra #{{ $r['batch']->item->purchase_id }}
                                </a>
                            @else
                                Stock existente
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @can('modify-inventory')
                                @if ($r['status'] === 'expired')
                                    <form action="{{ route('expirations.expire', $r['batch']) }}" method="POST" class="inline"
                                          onsubmit="return confirm('¿Dar de baja {{ $fmt($r['quantity']) }} {{ $r['product']->unit }} de {{ $r['product']->name }} como vencido? Se descuenta del inventario.')">
                                        @csrf
                                        <button class="text-red-600 hover:underline">Dar de baja</button>
                                    </form>
                                @endif
                                <form action="{{ route('expirations.destroy', $r['batch']) }}" method="POST" class="inline"
                                      onsubmit="return confirm('¿Quitar este lote del control de vencimientos? No cambia el inventario.')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-gray-500 hover:underline ml-3">Quitar</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-8 text-center text-gray-500">
                            No hay lotes por vencer en este período. Los vencimientos se registran al comprar o con el formulario de abajo.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="text-xs text-gray-500 mt-2">
        La cantidad es una estimación: se asume que lo que vence primero se consume primero. Al dar de baja un lote vencido,
        la salida queda como "Vencido" y suma al reporte de pérdidas y mermas.
    </p>

    @can('modify-inventory')
        <details class="bg-white shadow rounded mt-6" @if ($errors->any()) open @endif>
            <summary class="cursor-pointer px-4 py-3 font-semibold">
                Registrar vencimiento de stock que ya tengo
                <span class="text-sm font-normal text-gray-500">— no cambia el inventario</span>
            </summary>

            <form action="{{ route('expirations.store') }}" method="POST" class="p-4 border-t flex flex-wrap items-end gap-3">
                @csrf

                <div class="flex-1 min-w-[200px]">
                    <label class="block text-sm font-medium mb-1">Producto *</label>
                    <select name="product_id" required class="w-full {{ $input }}">
                        <option value="">— Elige un producto —</option>
                        @foreach ($products as $p)
                            <option value="{{ $p->id }}" @selected(old('product_id') == $p->id)>{{ $p->name }} ({{ $p->unit }})</option>
                        @endforeach
                    </select>
                    @error('product_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Cantidad con esa fecha *</label>
                    <input type="number" name="quantity" step="0.001" min="0.001" value="{{ old('quantity') }}" required class="w-36 {{ $input }}">
                    @error('quantity') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Vence *</label>
                    <input type="date" name="expires_on" value="{{ old('expires_on') }}" required class="{{ $input }}">
                    @error('expires_on') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Lote (opcional)</label>
                    <input type="text" name="lot_code" maxlength="100" value="{{ old('lot_code') }}" class="w-36 {{ $input }}">
                </div>

                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Registrar</button>
            </form>
        </details>
    @endcan
@endsection