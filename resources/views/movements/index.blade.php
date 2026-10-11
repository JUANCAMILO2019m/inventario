@extends('layouts.app')

@section('title', 'Movimientos: ' . $product->name)

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
    @endphp

    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-2xl font-bold">{{ $product->name }}</h1>
            <p class="text-gray-600">
                Stock actual: <span class="font-semibold">{{ $fmt($product->quantity) }} {{ $product->unit }}</span>
                · Mínimo: {{ $fmt($product->min_stock) }}
            </p>
        </div>
        <a href="{{ route('products.index') }}" class="text-blue-600 hover:underline">← Volver</a>
    </div>

    @can('modify-inventory')
        <form action="{{ route('products.movements.store', $product) }}" method="POST"
              class="bg-white shadow rounded p-4 mb-4 flex flex-wrap items-end gap-3">
            @csrf

            <div>
                <label class="block text-sm font-medium mb-1">Tipo</label>
                <select name="type" id="mv-type" class="rounded border border-gray-300 px-3 py-2">
                    <option value="in" @selected(old('type') === 'in')>Entrada (+)</option>
                    <option value="out" @selected(old('type') === 'out')>Salida (−)</option>
                    <option value="adjust" @selected(old('type') === 'adjust')>Ajuste (fijar cantidad)</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Motivo</label>
                <select name="reason_type" id="mv-reason-type" class="rounded border border-gray-300 px-3 py-2"></select>
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Cantidad ({{ $product->unit }})</label>
                <input type="number" min="0" step="0.001" name="amount" value="{{ old('amount') }}" required
                       class="w-28 rounded border border-gray-300 px-3 py-2">
            </div>

            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium mb-1">Detalle</label>
                <input type="text" name="reason" value="{{ old('reason') }}" placeholder="Opcional"
                       class="w-full rounded border border-gray-300 px-3 py-2">
            </div>

            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Registrar</button>

            <div class="w-full">
                @error('type') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
                @error('reason_type') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
                @error('amount') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
                @error('reason') <p class="text-red-600 text-sm">{{ $message }}</p> @enderror
            </div>
        </form>

        <script>
            (function () {
                const reasons = @json(\App\Models\StockMovement::REASONS);
                const previous = @json(old('reason_type'));
                const typeSelect = document.getElementById('mv-type');
                const reasonSelect = document.getElementById('mv-reason-type');

                function fill() {
                    const list = reasons[typeSelect.value] || {};
                    reasonSelect.innerHTML = '';

                    Object.keys(list).forEach(function (key) {
                        const option = document.createElement('option');
                        option.value = key;
                        option.textContent = list[key];
                        option.selected = key === previous;
                        reasonSelect.appendChild(option);
                    });
                }

                typeSelect.addEventListener('change', fill);
                fill();
            })();
        </script>
    @endcan

    @if ($purchases->isNotEmpty())
        <div class="bg-white shadow rounded mb-4">
            <div class="px-4 py-3 border-b flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-semibold">Compras recientes</h2>
                <span class="text-sm text-gray-500">
                    Costo promedio actual:
                    {{ $product->price !== null ? '$' . number_format((float) $product->price, 2) . ' / ' . $product->unit : 'sin precio' }}
                </span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Fecha</th>
                            <th class="px-4 py-2">Proveedor</th>
                            <th class="px-4 py-2">Cantidad</th>
                            <th class="px-4 py-2">Costo por {{ $product->unit }}</th>
                            <th class="px-4 py-2">Variación</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($purchases as $i => $it)
                            @php
                                $older = $purchases[$i + 1] ?? null;
                                $var = ($older && $older->unit_cost > 0) ? ($it->unit_cost - $older->unit_cost) / $older->unit_cost * 100 : null;
                            @endphp
                            <tr>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    <a href="{{ route('purchases.show', $it->purchase_id) }}" class="text-blue-600 hover:underline">
                                        {{ $it->purchase->purchased_at->format('d/m/Y') }}
                                    </a>
                                </td>
                                <td class="px-4 py-2">{{ $it->purchase->supplier?->name ?? '—' }}</td>
                                <td class="px-4 py-2">{{ $fmt($it->quantity) }} {{ $product->unit }}</td>
                                <td class="px-4 py-2">${{ number_format($it->unit_cost, 2) }}</td>
                                <td class="px-4 py-2">
                                    @if ($var === null)
                                        <span class="text-gray-400">—</span>
                                    @else
                                        <span class="{{ $var > 0 ? 'text-red-600' : 'text-green-700' }} font-semibold">
                                            {{ $var > 0 ? '+' : '' }}{{ number_format($var, 1) }}%
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
    
    {{-- Tabla --}}
    <div class="hidden md:block bg-white shadow rounded overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-gray-50 text-sm uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
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
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">
                            Aún no hay movimientos para este producto.
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
                <div class="flex items-center justify-between">
                    <span class="font-semibold">{{ $typeLabel($m->type) }} · {{ $reasonLabel($m) }}</span>
                    <span class="font-semibold {{ $m->change >= 0 ? 'text-green-700' : 'text-red-700' }}">
                        {{ $m->change > 0 ? '+' : '' }}{{ $fmt($m->change) }}
                    </span>
                </div>
                <p class="text-sm text-gray-500 mt-1">
                    {{ $m->created_at->format('d/m/Y H:i') }} · Quedó en {{ $fmt($m->quantity_after) }}
                    @if ($m->user) · {{ $m->user->name }} @endif
                </p>
                @if ($m->reason_type && $m->reason)
                    <p class="text-sm text-gray-600 mt-1">{{ $m->reason }}</p>
                @endif
            </div>
        @empty
            <div class="bg-white shadow rounded p-8 text-center text-gray-500">
                Aún no hay movimientos para este producto.
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $movements->links() }}</div>
@endsection