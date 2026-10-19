@extends('layouts.app')

@section('title', $count->name)

@section('content')
    @php
        $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.');
        $money = fn ($v) => ((float) $v < 0 ? '−' : '') . '$' . number_format(abs((float) $v), 2);
        $total = $items->count();
        $badge = [
            'open'      => 'bg-amber-100 text-amber-700',
            'applied'   => 'bg-green-100 text-green-700',
            'cancelled' => 'bg-gray-100 text-gray-600',
        ][$count->status];
        $stateLabel = [
            'uncounted' => ['Sin contar', 'bg-gray-100 text-gray-600'],
            'adjusted'  => ['Ajustado', 'bg-green-100 text-green-700'],
            'same'      => ['Sin diferencia', 'bg-blue-100 text-blue-700'],
            'skipped'   => ['No aplicado', 'bg-amber-100 text-amber-700'],
            'pending'   => ['Contado', 'bg-blue-100 text-blue-700'],
        ];
        $input = 'rounded border border-gray-300 px-3 py-2';
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
            <h1 class="text-2xl font-bold">
                {{ $count->name }}
                <span class="ml-2 text-sm px-2 py-1 rounded align-middle {{ $badge }}">{{ $count->statusLabel() }}</span>
            </h1>
            <p class="text-gray-500">
                {{ $count->category?->name ?? 'Todos los productos' }}
                · Abierto el {{ $count->created_at->format('d/m/Y H:i') }}
                @if ($count->user) por {{ $count->user->name }} @endif
                @if ($count->blind) · Conteo ciego @endif
            </p>
            @if ($count->closer)
                <p class="text-sm text-gray-500">
                    {{ $count->status === 'applied' ? 'Aplicado' : 'Cancelado' }}
                    el {{ ($count->applied_at ?? $count->cancelled_at)?->format('d/m/Y H:i') }} por {{ $count->closer->name }}
                </p>
            @endif
            @if ($count->notes)
                <p class="text-sm text-gray-600 mt-1">{{ $count->notes }}</p>
            @endif
        </div>
        <div class="flex items-center gap-3 whitespace-nowrap">
            <a href="{{ route('counts.export', $count) }}"
                class="px-4 py-2 rounded border border-green-600 text-green-700 hover:bg-green-50 text-sm">Exportar CSV</a>
            <a href="{{ route('counts.index') }}" class="text-gray-600 hover:underline">← Volver</a>
        </div>
    </div>

    @if ($count->isOpen())
        @php $canEdit = auth()->user()->canModify(); @endphp

        <div class="bg-white shadow rounded p-4 mb-4">
            <div class="flex items-center justify-between text-sm mb-2">
                <span class="font-medium">Progreso: <span id="progress-text">{{ $counted }} de {{ $total }}</span> contados</span>
                <span class="text-gray-500">Lo que dejes en blanco no se modifica.</span>
            </div>
            <div class="h-2 bg-gray-100 rounded">
                <div id="progress-bar" class="h-2 bg-blue-500 rounded" style="width: {{ $total ? round($counted / $total * 100) : 0 }}%"></div>
            </div>
        </div>

        @if ($canEdit)
            <form action="{{ route('counts.save', $count) }}" method="POST" id="sheet">
                @csrf

                @if ($errors->any())
                    <div class="mb-3 rounded bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error) <li>{{ $error }}</li> @endforeach
                        </ul>
                    </div>
                @endif
        @endif

        <div class="flex flex-wrap items-center gap-3 mb-3">
            <input type="text" id="filter" placeholder="Buscar producto o categoría" class="flex-1 min-w-[200px] {{ $input }}">
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" id="only-pending"> Solo los sin contar
            </label>
        </div>

        <div class="bg-white shadow rounded overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Producto</th>
                        <th class="px-4 py-3">Unidad</th>
                        @unless ($count->blind) <th class="px-4 py-3">Sistema al iniciar</th> @endunless
                        <th class="px-4 py-3">Contado</th>
                    </tr>
                </thead>
                <tbody class="divide-y" id="rows">
                    @foreach ($items as $item)
                        <tr data-name="{{ \Illuminate\Support\Str::lower($item->product_name . ' ' . $item->category_name) }}"
                            data-counted="{{ $item->counted_qty !== null ? 1 : 0 }}">
                            <td class="px-4 py-2">
                                <span class="font-medium">{{ $item->product_name }}</span>
                                <span class="block text-xs text-gray-500">{{ $item->category_name ?? 'Sin categoría' }}</span>
                            </td>
                            <td class="px-4 py-2">{{ $item->unit }}</td>
                            @unless ($count->blind)
                                <td class="px-4 py-2">{{ $fmt($item->system_qty) }}</td>
                            @endunless
                            <td class="px-4 py-2">
                                @if ($canEdit)
                                    <input type="number" step="0.001" min="0" inputmode="decimal" placeholder="—"
                                            name="items[{{ $item->id }}][counted]"
                                            value="{{ old('items.' . $item->id . '.counted', $item->counted_qty !== null ? $fmt($item->counted_qty) : '') }}"
                                            class="count-input w-32 rounded border border-gray-300 px-2 py-1">
                                @else
                                    {{ $item->counted_qty !== null ? $fmt($item->counted_qty) : '—' }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if ($canEdit)
                <div class="mt-4 flex flex-wrap gap-3">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded">Guardar avance</button>
                    <button type="submit" name="go" value="review"
                            class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded">Guardar y revisar diferencias</button>
                </div>
            </form>

            <form action="{{ route('counts.cancel', $count) }}" method="POST" class="mt-3"
                    onsubmit="return confirm('¿Cancelar este conteo? Se descarta todo lo contado y no se ajusta nada.')">
                @csrf
                <button class="text-red-600 hover:underline text-sm">Cancelar conteo</button>
            </form>

            <p class="text-xs text-gray-500 mt-3">
                Un conteo muy largo puede superar el límite de campos que acepta el servidor (alrededor de 900 productos).
                En ese caso, cuenta por categorías.
            </p>
        @endif

        <script>
            (function () {
                const rows = [...document.querySelectorAll('#rows tr')];
                const filter = document.getElementById('filter');
                const only = document.getElementById('only-pending');
                const text = document.getElementById('progress-text');
                const bar = document.getElementById('progress-bar');

                function apply() {
                    const q = filter.value.trim().toLowerCase();
                    rows.forEach(function (r) {
                        const matches = !q || r.dataset.name.indexOf(q) !== -1;
                        const pending = !only.checked || r.dataset.counted === '0';
                        r.classList.toggle('hidden', !(matches && pending));
                    });
                }

                function progress() {
                    const done = rows.filter(function (r) { return r.dataset.counted === '1'; }).length;
                    text.textContent = done + ' de ' + rows.length;
                    bar.style.width = (rows.length ? Math.round(done / rows.length * 100) : 0) + '%';
                }

                document.querySelectorAll('.count-input').forEach(function (input) {
                    input.addEventListener('input', function () {
                        input.closest('tr').dataset.counted = input.value !== '' ? '1' : '0';
                        progress();
                        if (only.checked) apply();
                    });
                });

                filter.addEventListener('input', apply);
                only.addEventListener('change', apply);
            })();
        </script>
    @else
        {{-- Conteo cerrado: reporte --}}
        @if ($summary)
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
                <div class="bg-white shadow rounded p-4">
                    <p class="text-sm text-gray-500">Productos ajustados</p>
                    <p class="text-2xl font-bold">{{ $summary['adjusted'] }}</p>
                </div>
                <div class="bg-white shadow rounded p-4">
                    <p class="text-sm text-gray-500">Faltantes</p>
                    <p class="text-2xl font-bold {{ $summary['short'] < 0 ? 'text-red-600' : '' }}">{{ $money($summary['short']) }}</p>
                </div>
                <div class="bg-white shadow rounded p-4">
                    <p class="text-sm text-gray-500">Sobrantes</p>
                    <p class="text-2xl font-bold {{ $summary['surplus'] > 0 ? 'text-green-700' : '' }}">{{ $money($summary['surplus']) }}</p>
                </div>
                <div class="bg-white shadow rounded p-4">
                    <p class="text-sm text-gray-500">Neto</p>
                    @php $net = $summary['short'] + $summary['surplus']; @endphp
                    <p class="text-2xl font-bold {{ $net < 0 ? 'text-red-600' : 'text-green-700' }}">{{ $money($net) }}</p>
                </div>
            </div>

            <p class="text-sm text-gray-600 mb-3">
                {{ $summary['skipped'] }} con diferencia que no se aplicó · {{ $summary['uncounted'] }} sin contar.
                Valores al costo que tenían los productos cuando empezó el conteo.
            </p>

            @if ($summary['missing_cost'] > 0)
                <div class="mb-3 rounded bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-2">
                    {{ $summary['missing_cost'] }} producto(s) ajustados no tenían precio: no suman valor y los totales están subestimados.
                </div>
            @endif
        @else
            <div class="mb-3 rounded bg-gray-50 border border-gray-200 text-gray-600 text-sm px-4 py-2">
                Este conteo se canceló: no se hizo ningún ajuste. Se conserva lo contado como referencia.
            </div>
        @endif

        <div class="bg-white shadow rounded overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Producto</th>
                        <th class="px-4 py-3">Sistema al iniciar</th>
                        <th class="px-4 py-3">Contado</th>
                        <th class="px-4 py-3">Ajuste aplicado</th>
                        <th class="px-4 py-3">Valor</th>
                        <th class="px-4 py-3">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($items as $item)
                        @php
                            $state = $item->state($count->status);
                            $adjusted = $state === 'adjusted';
                        @endphp
                        <tr>
                            <td class="px-4 py-2">
                                <span class="font-medium">{{ $item->product_name }}</span>
                                <span class="block text-xs text-gray-500">{{ $item->category_name ?? 'Sin categoría' }}</span>
                            </td>
                            <td class="px-4 py-2">{{ $fmt($item->system_qty) }} {{ $item->unit }}</td>
                            <td class="px-4 py-2">{{ $item->counted_qty !== null ? $fmt($item->counted_qty) . ' ' . $item->unit : '—' }}</td>
                            <td class="px-4 py-2 font-semibold {{ $adjusted ? ($item->applied_change < 0 ? 'text-red-700' : 'text-green-700') : 'text-gray-400' }}">
                                {{ $adjusted ? ($item->applied_change > 0 ? '+' : '') . $fmt($item->applied_change) : '—' }}
                            </td>
                            <td class="px-4 py-2">
                                {{ ($adjusted && $item->unit_cost !== null) ? $money($item->applied_change * $item->unit_cost) : '—' }}
                            </td>
                            <td class="px-4 py-2">
                                <span class="text-xs px-2 py-1 rounded {{ $stateLabel[$state][1] }}">{{ $stateLabel[$state][0] }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection