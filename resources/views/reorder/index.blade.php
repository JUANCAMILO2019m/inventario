@extends('layouts.app')

@section('title', 'Reabastecimiento')

@section('content')
    @php
        $money = fn ($v) => '$' . number_format((float) $v, 2);
        $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.');
        $fmtRate = fn ($n) => rtrim(rtrim(number_format((float) $n, $n < 1 ? 3 : 2, '.', ''), '0'), '.');
        $input = 'rounded border border-gray-300 px-3 py-2 text-sm';
        $urgent = ['out', 'critical', 'soon'];
        $canBuy = auth()->user()->canModify();
        $badge = [
            'out'      => ['Agotado', 'bg-red-100 text-red-700'],
            'critical' => ['Comprar ya', 'bg-red-100 text-red-700'],
            'soon'     => ['Comprar pronto', 'bg-amber-100 text-amber-700'],
            'ok'       => ['Cubierto', 'bg-green-100 text-green-700'],
            'idle'     => ['Sin consumo', 'bg-gray-100 text-gray-600'],
        ];
        $now = $counts->get('out', 0) + $counts->get('critical', 0);
        $soon = $counts->get('soon', 0);
        $enough = $counts->get('ok', 0);
    @endphp

    <h1 class="text-2xl font-bold mb-1">Reabastecimiento</h1>
    <p class="text-gray-500 mb-4">
        Cuántos días te dura cada producto según tu consumo real, y cuánto conviene comprar.
    </p>

    <form method="GET" action="{{ route('reorder.index') }}"
          class="bg-white shadow rounded p-4 mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-sm font-medium mb-1">Calcular el consumo con los últimos</label>
            <select name="window" class="{{ $input }}">
                @foreach ([14, 30, 60, 90] as $d)
                    <option value="{{ $d }}" @selected($window === $d)>{{ $d }} días</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Que me alcance, tras recibir la compra, para</label>
            <select name="cover" class="{{ $input }}">
                @foreach ([15, 30, 45, 60, 90] as $d)
                    <option value="{{ $d }}" @selected($cover === $d)>{{ $d }} días</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Categoría</label>
            <select name="category" class="{{ $input }}">
                <option value="">Todas</option>
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}" @selected($categoryId === $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>

        <label class="flex items-center gap-2 pb-2 text-sm">
            <input type="checkbox" name="all" value="1" @checked($includeIdle)>
            Incluir productos sin consumo
        </label>

        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Calcular</button>
    </form>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Comprar ya o agotados</p>
            <p class="text-2xl font-bold {{ $now ? 'text-red-600' : '' }}">{{ $now }}</p>
        </div>
        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Comprar pronto</p>
            <p class="text-2xl font-bold {{ $soon ? 'text-amber-600' : '' }}">{{ $soon }}</p>
        </div>
        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Cubiertos</p>
            <p class="text-2xl font-bold">{{ $enough }}</p>
        </div>
        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Costo estimado de lo urgente</p>
            <p class="text-2xl font-bold">{{ $money($urgentCost) }}</p>
            @if ($urgentMissingCost > 0)
                <p class="text-xs text-amber-700 mt-1">{{ $urgentMissingCost }} producto(s) sin precio no suman.</p>
            @endif
        </div>
    </div>

    @if ($canBuy)
        <div class="flex flex-wrap items-center gap-3 mb-3">
            <button type="button" id="build-purchase"
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded text-sm">
                Armar compra con lo seleccionado
            </button>
            <span id="selected-info" class="text-sm text-gray-600"></span>
        </div>
    @endif

    <div class="bg-white shadow rounded overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    @if ($canBuy) <th class="px-3 py-3"></th> @endif
                    <th class="px-4 py-3">Producto</th>
                    <th class="px-4 py-3">Disponible</th>
                    <th class="px-4 py-3">Consumo diario</th>
                    <th class="px-4 py-3">Alcance</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Comprar</th>
                    <th class="px-4 py-3">Costo estimado</th>
                    <th class="px-4 py-3">Proveedor habitual</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($rows as $r)
                    @php
                        $p = $r['product'];
                        $sg = $r['suggest'];
                        $isUrgent = in_array($r['status'], $urgent, true);
                        $cover = $r['days_cover'];
                        $orderIn = $r['order_in_days'] !== null ? (int) floor($r['order_in_days']) : null;
                        $priceBase = ($r['last_cost'] !== null && $sg)
                            ? round($r['last_cost'] * ($sg['mode'] === 'package' ? (float) $p->package_size : 1), 2)
                            : null;
                    @endphp
                    <tr class="align-top">
                        @if ($canBuy)
                            <td class="px-3 py-3">
                                @if ($sg)
                                    <input type="checkbox" class="reorder-check" @checked($isUrgent)
                                           data-product="{{ $p->id }}"
                                           data-mode="{{ $sg['mode'] }}"
                                           data-qty="{{ $sg['mode'] === 'package' ? $sg['packages'] : $sg['qty'] }}"
                                           data-price="{{ $priceBase }}"
                                           data-cost="{{ $r['est_cost'] }}"
                                           data-supplier="{{ $r['supplier']['id'] ?? '' }}">
                                @endif
                            </td>
                        @endif

                        <td class="px-4 py-3">
                            <a href="{{ route('products.movements.index', $p) }}" class="font-medium hover:underline">{{ $p->name }}</a>
                            <span class="block text-xs text-gray-500">{{ $p->category?->name ?? 'Sin categoría' }}</span>
                        </td>

                        <td class="px-4 py-3">
                            {{ $fmt($r['usable']) }} {{ $p->unit }}
                            @if ($p->packagesLabel() && $r['expired_qty'] <= 0)
                                <span class="block text-xs text-gray-500">{{ $p->packagesLabel() }}</span>
                            @endif
                            @if ($r['expired_qty'] > 0)
                                <span class="block text-xs text-red-600">
                                    + {{ $fmt($r['expired_qty']) }} {{ $p->unit }} vencido (no cuenta)
                                </span>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            @if ($r['rate'] > 0)
                                {{ $fmtRate($r['rate']) }} {{ $p->unit }}/día
                                <span class="block text-xs text-gray-500">
                                    en {{ $r['observed_days'] }} {{ $r['observed_days'] === 1 ? 'día' : 'días' }}
                                    · {{ $r['outs_count'] }} {{ $r['outs_count'] === 1 ? 'salida' : 'salidas' }}
                                </span>
                                @if ($r['confidence'] === 'low')
                                    <span class="inline-block mt-1 text-xs px-2 py-0.5 rounded bg-amber-100 text-amber-700">
                                        Pocos datos
                                    </span>
                                @endif
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            @if ($r['status'] === 'out')
                                <span class="font-semibold text-red-600">Sin stock</span>
                            @elseif ($cover === null)
                                —
                            @elseif ($cover > 365)
                                <span class="font-semibold">+365 días</span>
                            @else
                                <span class="font-semibold">
                                    {{ (int) floor($cover) }} {{ (int) floor($cover) === 1 ? 'día' : 'días' }}
                                </span>
                                <span class="block text-xs text-gray-500">
                                    Se agota ~{{ today()->addDays((int) floor($cover))->format('d/m/Y') }}
                                    @if ($orderIn !== null && $orderIn > 0)
                                        · pedir antes del {{ today()->addDays($orderIn)->format('d/m/Y') }}
                                    @else
                                        · pedir ya
                                    @endif
                                </span>
                            @endif
                            <span class="block text-xs text-gray-400">Entrega: {{ $r['lead'] }} {{ $r['lead'] === 1 ? 'día' : 'días' }}</span>
                        </td>

                        <td class="px-4 py-3">
                            <span class="text-xs px-2 py-1 rounded {{ $badge[$r['status']][1] }}">{{ $badge[$r['status']][0] }}</span>
                        </td>

                        <td class="px-4 py-3">
                            @if ($sg)
                                @if ($sg['mode'] === 'package')
                                    <span class="font-semibold">{{ $sg['packages'] }} × {{ $p->package_name }}</span>
                                    <span class="block text-xs text-gray-500">
                                        = {{ $fmt($sg['qty']) }} {{ $p->unit }}
                                    </span>
                                @else
                                    <span class="font-semibold">{{ $fmt($sg['qty']) }} {{ $p->unit }}</span>
                                @endif
                            @else
                                <span class="text-gray-400">—</span>
                            @endif

                            @if ($r['waste_total'] > 0.0005)
                                <a href="{{ route('expirations.index') }}"
                                   class="block text-xs text-amber-700 hover:underline mt-1">
                                    ⚠ ~{{ $fmt($r['waste_total']) }} {{ $p->unit }} vencerán antes de usarse
                                </a>
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            {{ $r['est_cost'] !== null ? $money($r['est_cost']) : '—' }}
                            @if ($r['est_cost'] !== null && $r['last_cost'] !== null)
                                <span class="block text-xs text-gray-500">a {{ $money($r['last_cost']) }} / {{ $p->unit }}</span>
                            @endif
                        </td>

                        <td class="px-4 py-3">{{ $r['supplier']['name'] ?? '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $canBuy ? 9 : 8 }}" class="px-4 py-8 text-center text-gray-500">
                            No hay productos con consumo en los últimos {{ $window }} días. El cálculo aparece cuando registres
                            salidas por uso en animales u otras salidas.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="text-xs text-gray-500 mt-3">
        Es una estimación con el promedio de tu consumo reciente: no anticipa que tus animales coman más al crecer
        ni menos tras una venta. Revísala después de movimientos grandes y deja un margen en los días de cobertura.
    </p>

    @if ($canBuy)
        <script>
            (function () {
                const boxes = [...document.querySelectorAll('.reorder-check')];
                const btn = document.getElementById('build-purchase');
                const info = document.getElementById('selected-info');
                const base = @json(route('purchases.create'));
                const money = n => '$' + (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

                function update() {
                    const checked = boxes.filter(b => b.checked);
                    const cost = checked.reduce((sum, b) => sum + (parseFloat(b.dataset.cost) || 0), 0);

                    info.textContent = checked.length + ' seleccionado(s)' + (cost > 0 ? ' · costo estimado ' + money(cost) : '');
                    btn.disabled = checked.length === 0;
                    btn.classList.toggle('opacity-50', checked.length === 0);
                }

                btn.addEventListener('click', function () {
                    const checked = boxes.filter(b => b.checked).slice(0, 50);
                    if (!checked.length) return;

                    const params = new URLSearchParams();

                    checked.forEach((b, i) => {
                        params.set('prefill[' + i + '][product_id]', b.dataset.product);
                        params.set('prefill[' + i + '][mode]', b.dataset.mode);
                        params.set('prefill[' + i + '][qty]', b.dataset.qty);
                        if (b.dataset.price) params.set('prefill[' + i + '][price]', b.dataset.price);
                    });

                    // Si todos tienen el mismo proveedor habitual, se deja elegido
                    const suppliers = new Set(checked.map(b => b.dataset.supplier || ''));
                    const only = [...suppliers][0];
                    if (suppliers.size === 1 && only !== '') params.set('supplier_id', only);

                    window.location.href = base + '?' + params.toString();
                });

                boxes.forEach(b => b.addEventListener('change', update));
                update();
            })();
        </script>
    @endif
@endsection