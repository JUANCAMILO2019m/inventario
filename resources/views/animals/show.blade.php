@extends('layouts.app')

@section('title', $animal->name)

@section('content')
    @php
        $money = fn ($value) => '$' . number_format((float) $value, 2);

        $statusLabel = match ($animal->status) {
            'active' => 'Activo',
            'sold' => 'Vendido',
            default => 'Baja',
        };

        $typeLabels = [
            'feeding'   => 'Alimentación',
            'vaccine'   => 'Vacuna/Desparasitación',
            'weight'    => 'Pesaje',
            'treatment' => 'Tratamiento',
            'mortality' => 'Baja (mortalidad)',
            'sale'      => 'Venta',
            'entry'     => 'Ingreso de animales',
            'consumption' => 'Consumo propio',
        ];

        $weightTypeLabel = fn ($t) => match ($t) {
            'scale' => ' (báscula)',
            'estimated' => ' (estimado)',
            default => '',
        };

        $detail = function ($r) use ($weightTypeLabel) {
            $title = $r->title ? ' · ' . $r->title : '';

            switch ($r->type) {
                case 'weight':
                    return number_format($r->weight, 2) . ' kg' . $weightTypeLabel($r->weight_type);
                case 'mortality':
                    return '−' . $r->heads . ' cabezas' . $title;
                case 'entry':
                    return '+' . $r->heads . ' cabezas' . $title
                        . ($r->amount !== null ? ' · $' . number_format($r->amount, 2) : '');
                case 'sale':
                    $parts = ['−' . $r->heads . ($r->heads == 1 ? ' cabeza' : ' cabezas')];
                    if ($r->total_weight !== null) {
                        $parts[] = number_format($r->total_weight, 2) . ' kg' . $weightTypeLabel($r->weight_type);
                    }
                    if ($r->price_mode === 'per_kg') {
                        $parts[] = '$' . number_format($r->unit_price, 2) . '/kg';
                    } elseif ($r->price_mode === 'per_head') {
                        $parts[] = '$' . number_format($r->unit_price, 2) . '/cabeza';
                    }
                    if ($r->title) {
                        $parts[] = $r->title;
                    }
                    if ($r->amount !== null) {
                        $parts[] = 'Total $' . number_format($r->amount, 2);
                    }
                    if ($r->batch_id) {
                        $parts[] = 'Faena';
                    }
                    return implode(' · ', $parts);
                case 'consumption':
                return '−' . $r->heads . ' cabezas · sin venta';
                default:
                    return $r->title ?? '—';
            }
        };
    @endphp

    <div class="flex items-start justify-between gap-3 mb-4">
        <div class="flex items-center gap-4">
            @if ($animal->photo)
                <img src="{{ $animal->photo_url }}" alt="{{ $animal->name }}"
                        class="w-20 h-20 object-cover rounded border">
            @else
                <div class="w-20 h-20 bg-gray-100 rounded border flex items-center justify-center text-3xl">🐾</div>
            @endif
            <div>
                <h1 class="text-2xl font-bold">{{ $animal->name }}</h1>
                <p class="text-gray-500">
                    {{ $animal->species }}{{ $animal->breed ? ' · ' . $animal->breed : '' }}
                    · {{ $animal->isLot() ? 'Lote' : 'Individual' }}
                    @if ($animal->code) · {{ $animal->code }} @endif
                </p>
            </div>
        </div>
        <div class="flex items-center gap-3 whitespace-nowrap">
            @can('modify-inventory')
                <a href="{{ route('animals.edit', $animal) }}" class="text-blue-600 hover:underline">Editar</a>
            @endcan
            <a href="{{ route('animals.index') }}" class="text-gray-600 hover:underline">← Volver</a>
        </div>
    </div>

    {{-- Cabezas --}}
    @if ($animal->isLot())
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Cabezas actuales</p>
                <p class="text-2xl font-bold">{{ $animal->quantity }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $statusLabel }}</p>
            </div>
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Total ingresadas</p>
                <p class="text-2xl font-bold">{{ $stats['entered'] }}</p>
                <p class="text-xs text-gray-500 mt-1">
                    Inicial {{ (int) $animal->initial_quantity }} + ingresos {{ $stats['entered'] - (int) $animal->initial_quantity }}
                </p>
            </div>
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Bajas</p>
                <p class="text-2xl font-bold {{ $stats['deaths'] > 0 ? 'text-red-600' : '' }}">{{ $stats['deaths'] }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $stats['mortality_pct'] }}% del total ingresado</p>
            </div>
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Vendidas</p>
                <p class="text-2xl font-bold">{{ $stats['sold_heads'] }}</p>
                @if ($stats['consumed_heads'] > 0)
                    <p class="text-xs text-gray-500 mt-1">+ {{ $stats['consumed_heads'] }} de consumo propio</p>
                @endif
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Estado</p>
                <p class="font-semibold">{{ $statusLabel }}</p>
            </div>
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Último peso registrado</p>
                <p class="font-semibold">
                    {{ $stats['last_weight'] !== null
                        ? number_format($stats['last_weight'], 2) . ' kg' . $weightTypeLabel($stats['last_weight_type'])
                        : 'Sin registrar' }}
                </p>
            </div>
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Fecha de nacimiento</p>
                <p class="font-semibold">{{ $animal->birth_date?->format('d/m/Y') ?? '—' }}</p>
            </div>
        </div>
    @endif

    {{-- Resultado económico --}}
    <div class="bg-white shadow rounded p-4 mb-6">
        <h2 class="font-semibold mb-3">Resultado económico</h2>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <p class="text-sm text-gray-500">Costos totales</p>
                <p class="text-xl font-bold">{{ $money($stats['total_cost']) }}</p>
                <p class="text-xs text-gray-500 mt-1">
                    Compra {{ $money($stats['purchase']) }} · Alimento {{ $money($stats['feed_cost']) }} · Sanidad {{ $money($stats['health_cost']) }}
                    @if ($animal->isLot() && $stats['cost_per_head'] !== null)
                        · {{ $money($stats['cost_per_head']) }} por cabeza
                    @endif
                </p>
            </div>

            <div>
                <p class="text-sm text-gray-500">Ventas</p>
                <p class="text-xl font-bold">{{ $money($stats['revenue']) }}</p>
                <p class="text-xs mt-1">
                    <span class="text-gray-500">Cobrado {{ $money($stats['collected']) }}</span>
                    @if ($stats['receivable'] > 0)
                        · <span class="text-red-600 font-semibold">Por cobrar {{ $money($stats['receivable']) }}</span>
                    @endif
                </p>
            </div>

            <div>
                <p class="text-sm text-gray-500">{{ $stats['closed'] ? 'Resultado final' : 'Resultado parcial' }}</p>
                <p class="text-xl font-bold {{ $stats['margin'] >= 0 ? 'text-green-700' : 'text-red-600' }}">
                    {{ $stats['margin'] < 0 ? '−' : '' }}{{ $money(abs($stats['margin'])) }}
                </p>
                <p class="text-xs text-gray-500 mt-1">
                    @if ($stats['margin_pct'] !== null) {{ $stats['margin_pct'] }}% sobre costos @endif
                    @unless ($stats['closed']) · aún hay animales sin vender @endunless
                </p>
            </div>

            <div>
                <p class="text-sm text-gray-500">Costo por kg ganado</p>
                <p class="text-xl font-bold">
                    {{ $stats['cost_per_kg_gain'] !== null ? $money($stats['cost_per_kg_gain']) : '—' }}
                </p>
                <p class="text-xs text-gray-500 mt-1">Alimento y sanidad</p>
            </div>
        </div>

        <h3 class="font-semibold mt-5 mb-2">Indicadores de producción</h3>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
            <div>
                <p class="text-sm text-gray-500">Peso inicial → último</p>
                <p class="text-lg font-bold">
                    {{ $animal->initial_weight !== null ? number_format($animal->initial_weight, 2) . ' kg' : '—' }}
                    →
                    {{ $stats['last_weight'] !== null ? number_format($stats['last_weight'], 2) . ' kg' : '—' }}
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Ganancia por cabeza</p>
                <p class="text-lg font-bold">
                    {{ $stats['weight_gain'] !== null ? ($stats['weight_gain'] >= 0 ? '+' : '') . number_format($stats['weight_gain'], 2) . ' kg' : '—' }}
                </p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Ganancia diaria</p>
                <p class="text-lg font-bold">{{ $stats['gdp_g'] !== null ? number_format($stats['gdp_g']) . ' g/día' : '—' }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500">Conversión alimenticia</p>
                <p class="text-lg font-bold">{{ $stats['fcr'] !== null ? $stats['fcr'] . ' : 1' : '—' }}</p>
                <p class="text-xs text-gray-500">kg de alimento por kg ganado</p>
            </div>
        </div>

        @if (count($stats['warnings']))
            <ul class="mt-4 rounded bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-2 list-disc list-inside">
                @foreach ($stats['warnings'] as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($animal->description)
        <div class="bg-white shadow rounded p-4 mb-6">
            <p class="text-sm text-gray-500 mb-1">Descripción</p>
            <p>{{ $animal->description }}</p>
        </div>
    @endif

    @can('modify-inventory')
        <div class="bg-white shadow rounded p-4 mb-6">
            <h2 class="font-semibold mb-3">Registrar evento</h2>

            @if ($errors->any())
                <div class="mb-3 rounded bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form action="{{ route('animals.records.store', $animal) }}" method="POST" id="record-form">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Tipo *</label>
                        <select name="type" id="record-type" class="w-full rounded border border-gray-300 px-3 py-2" required>
                            <option value="feeding" @selected(old('type') === 'feeding')>Alimentación</option>
                            <option value="vaccine" @selected(old('type') === 'vaccine')>Vacuna / Desparasitación</option>
                            <option value="weight" @selected(old('type') === 'weight')>
                                {{ $animal->isLot() ? 'Pesaje (promedio por cabeza)' : 'Pesaje' }}
                            </option>
                            <option value="treatment" @selected(old('type') === 'treatment')>Tratamiento</option>
                            <optgroup label="Movimientos">
                                <option value="sale" @selected(old('type') === 'sale')>Venta</option>
                                @if ($animal->isLot())
                                    <option value="mortality" @selected(old('type') === 'mortality')>Baja (mortalidad)</option>
                                    <option value="entry" @selected(old('type') === 'entry')>Ingreso de animales</option>
                                @endif
                            </optgroup>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Fecha *</label>
                        <input type="date" name="recorded_at" value="{{ old('recorded_at', date('Y-m-d')) }}"
                                class="w-full rounded border border-gray-300 px-3 py-2" required>
                    </div>

                    <div class="field-title">
                        <label class="block text-sm font-medium mb-1" id="title-label">Título / Nombre</label>
                        <input type="text" name="title" id="title-input" value="{{ old('title') }}"
                                class="w-full rounded border border-gray-300 px-3 py-2">
                    </div>

                    <div class="field-weight hidden">
                        <label class="block text-sm font-medium mb-1">Peso (kg) *</label>
                        <input type="number" step="0.01" min="0" name="weight" value="{{ old('weight') }}"
                                class="w-full rounded border border-gray-300 px-3 py-2">
                    </div>

                    <div class="field-weighttype hidden">
                        <label class="block text-sm font-medium mb-1">Tipo de peso</label>
                        <select name="weight_type" class="w-full rounded border border-gray-300 px-3 py-2">
                            <option value="scale" @selected(old('weight_type', 'scale') === 'scale')>Pesado en báscula</option>
                            <option value="estimated" @selected(old('weight_type') === 'estimated')>Estimado</option>
                        </select>
                    </div>

                    <div class="field-heads hidden">
                        <label class="block text-sm font-medium mb-1">Cabezas *</label>
                        <input type="number" step="1" min="1" name="heads" value="{{ old('heads') }}"
                                class="w-full rounded border border-gray-300 px-3 py-2">
                    </div>

                    <div class="field-saleweight hidden">
                        <label class="block text-sm font-medium mb-1">Peso total vendido (kg)</label>
                        <input type="number" step="0.01" min="0" name="total_weight" value="{{ old('total_weight') }}"
                                class="w-full rounded border border-gray-300 px-3 py-2">
                    </div>

                    <div class="field-pricemode hidden">
                        <label class="block text-sm font-medium mb-1">Cómo se cobra</label>
                        <select name="price_mode" id="price-mode" class="w-full rounded border border-gray-300 px-3 py-2">
                            <option value="">Valor total directo</option>
                            <option value="per_kg" @selected(old('price_mode') === 'per_kg')>Por kilo en pie</option>
                            <option value="per_head" @selected(old('price_mode') === 'per_head')>Por cabeza</option>
                        </select>
                    </div>

                    <div class="field-unitprice hidden">
                        <label class="block text-sm font-medium mb-1" id="unitprice-label">Precio por kilo</label>
                        <input type="number" step="0.01" min="0" name="unit_price" value="{{ old('unit_price') }}"
                                class="w-full rounded border border-gray-300 px-3 py-2">
                    </div>

                    <div class="field-amount hidden">
                        <label class="block text-sm font-medium mb-1" id="amount-label">Valor</label>
                        <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}"
                                class="w-full rounded border border-gray-300 px-3 py-2">
                    </div>

                    <div class="field-payment hidden">
                        <label class="block text-sm font-medium mb-1">Estado de pago</label>
                        <select name="payment_status" id="payment-status" class="w-full rounded border border-gray-300 px-3 py-2">
                            <option value="paid" @selected(old('payment_status', 'paid') === 'paid')>Pagado completo</option>
                            <option value="pending" @selected(old('payment_status') === 'pending')>Pendiente de pago</option>
                            <option value="partial" @selected(old('payment_status') === 'partial')>Pago parcial (abono)</option>
                        </select>
                    </div>

                    <div class="field-paid hidden">
                        <label class="block text-sm font-medium mb-1">Abono recibido</label>
                        <input type="number" step="0.01" min="0" name="amount_paid" value="{{ old('amount_paid') }}"
                                class="w-full rounded border border-gray-300 px-3 py-2">
                    </div>

                    <div class="field-product">
                        <label class="block text-sm font-medium mb-1">Insumo del inventario</label>
                        <select name="product_id" class="w-full rounded border border-gray-300 px-3 py-2">
                            <option value="">— Ninguno —</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}" @selected(old('product_id') == $p->id)>
                                    {{ $p->name }} (disp: {{ $p->quantity }} {{ $p->unit }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field-product">
                        <label class="block text-sm font-medium mb-1">Cantidad usada</label>
                        <input type="number" step="0.001" min="0" name="product_quantity" value="{{ old('product_quantity') }}"
                                class="w-full rounded border border-gray-300 px-3 py-2">
                    </div>

                    <div class="field-next">
                        <label class="block text-sm font-medium mb-1">Próximo control</label>
                        <input type="date" name="next_due_date" value="{{ old('next_due_date') }}"
                                class="w-full rounded border border-gray-300 px-3 py-2">
                    </div>

                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-1">Notas</label>
                        <input type="text" name="notes" value="{{ old('notes') }}"
                                class="w-full rounded border border-gray-300 px-3 py-2">
                    </div>
                </div>

                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded">
                    Guardar registro
                </button>
            </form>
        </div>

        <script>
            (function () {
                const isLot = @json($animal->isLot());
                const form = document.getElementById('record-form');
                const typeSelect = document.getElementById('record-type');
                const priceMode = document.getElementById('price-mode');
                const payment = document.getElementById('payment-status');
                const field = name => form.querySelector('[name=' + name + ']');

                const config = {
                    feeding:   { show: ['product'], title: ['Detalle', 'Ej: Concentrado de inicio'] },
                    vaccine:   { show: ['product', 'next'], title: ['Vacuna / Producto', 'Ej: Triple viral'] },
                    weight:    { show: ['weight', 'weighttype'], title: null },
                    treatment: { show: ['next'], title: ['Diagnóstico / Tratamiento', 'Ej: Diarrea'] },
                    mortality: { show: ['heads'], title: ['Causa', 'Ej: Enfermedad, accidente'] },
                    entry:     { show: ['heads', 'amount'], title: ['Origen / Proveedor', 'Ej: Granja San José'], amount: 'Costo de la compra' },
                    sale:      { show: ['heads', 'saleweight', 'weighttype', 'pricemode', 'amount', 'payment'], title: ['Comprador', 'Nombre del comprador'], amount: 'Valor total de la venta' },
                };

                const names = ['title', 'weight', 'weighttype', 'heads', 'saleweight', 'pricemode',
                                'unitprice', 'amount', 'payment', 'paid', 'product', 'next'];

                function setVisible(name, visible) {
                    form.querySelectorAll('.field-' + name).forEach(box => {
                        box.classList.toggle('hidden', !visible);
                        box.querySelectorAll('input, select').forEach(el => { el.disabled = !visible; });
                    });
                }

                function recalc() {
                    if (typeSelect.value !== 'sale') return;

                    const price = parseFloat(field('unit_price').value);
                    if (isNaN(price)) return;

                    let total = null;

                    if (priceMode.value === 'per_kg') {
                        const weight = parseFloat(field('total_weight').value);
                        if (!isNaN(weight)) total = weight * price;
                    } else if (priceMode.value === 'per_head') {
                        const heads = isLot ? parseFloat(field('heads').value) : 1;
                        if (!isNaN(heads)) total = heads * price;
                    }

                    if (total !== null) field('amount').value = total.toFixed(2);
                }

                function apply() {
                    const type = typeSelect.value;
                    const cfg = config[type];

                    names.forEach(name => {
                        let visible = name === 'title' ? cfg.title !== null : cfg.show.includes(name);

                        if (name === 'heads' && !isLot) visible = false;
                        if (name === 'unitprice') visible = type === 'sale' && priceMode.value !== '';
                        if (name === 'paid') visible = type === 'sale' && payment.value === 'partial';

                        setVisible(name, visible);
                    });

                    field('weight').required = type === 'weight';
                    field('heads').required = !field('heads').disabled;
                    field('total_weight').required = type === 'sale' && priceMode.value === 'per_kg';
                    field('unit_price').required = !field('unit_price').disabled;
                    field('amount').required = type === 'sale';
                    field('amount_paid').required = !field('amount_paid').disabled;

                    if (cfg.title) {
                        document.getElementById('title-label').textContent = cfg.title[0];
                        document.getElementById('title-input').placeholder = cfg.title[1];
                    }

                    if (cfg.amount) {
                        document.getElementById('amount-label').textContent = cfg.amount;
                    }

                    document.getElementById('unitprice-label').textContent =
                        priceMode.value === 'per_head' ? 'Precio por cabeza' : 'Precio por kilo';

                    recalc();
                }

                typeSelect.addEventListener('change', apply);
                priceMode.addEventListener('change', apply);
                payment.addEventListener('change', apply);

                ['unit_price', 'total_weight', 'heads'].forEach(name => {
                    field(name).addEventListener('input', recalc);
                });

                apply();
            })();
        </script>
    @endcan

    @include('animals._faena_form')

    @include('animals._buyers')

    <h2 class="text-xl font-bold mb-3">Historial</h2>

    {{-- Vista de tabla --}}
    <div class="hidden md:block bg-white shadow rounded overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-gray-50 text-sm uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Tipo</th>
                    <th class="px-4 py-3">Detalle</th>
                    <th class="px-4 py-3">Insumo usado</th>
                    <th class="px-4 py-3">Próximo control</th>
                    <th class="px-4 py-3">Notas</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($records as $r)
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $r->recorded_at->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">{{ $typeLabels[$r->type] ?? $r->type }}</td>
                        <td class="px-4 py-3">
                            {{ $detail($r) }}
                            @include('animals._sale_payment', ['r' => $r, 'animal' => $animal])
                        </td>
                        <td class="px-4 py-3">
                            @if ($r->product)
                                {{ $r->product->name }} ({{ $r->product_quantity }} {{ $r->product->unit }})
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $r->next_due_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $r->notes ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            @can('modify-inventory')
                                <form action="{{ route('animals.records.destroy', [$animal, $r]) }}" method="POST"
                                        onsubmit="return confirm('¿Eliminar este registro? Se revertirá el inventario o las cabezas que haya afectado.')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-red-600 hover:underline text-sm">Eliminar</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">Aún no hay registros para este animal.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Vista de tarjetas --}}
    <div class="md:hidden space-y-3">
        @forelse ($records as $r)
            <div class="bg-white shadow rounded p-4">
                <div class="flex items-center justify-between">
                    <span class="font-semibold">{{ $typeLabels[$r->type] ?? $r->type }}</span>
                    <span class="text-sm text-gray-500">{{ $r->recorded_at->format('d/m/Y') }}</span>
                </div>
                <p class="text-sm mt-1">{{ $detail($r) }}</p>
                @include('animals._sale_payment', ['r' => $r, 'animal' => $animal])
                @if ($r->product)
                    <p class="text-sm text-gray-600">{{ $r->product->name }} ({{ $r->product_quantity }} {{ $r->product->unit }})</p>
                @endif
                @if ($r->next_due_date)
                    <p class="text-sm text-gray-600">Próximo: {{ $r->next_due_date->format('d/m/Y') }}</p>
                @endif
                @if ($r->notes)
                    <p class="text-sm text-gray-600 mt-1">{{ $r->notes }}</p>
                @endif

                @can('modify-inventory')
                    <form action="{{ route('animals.records.destroy', [$animal, $r]) }}" method="POST"
                            class="mt-2 pt-2 border-t"
                            onsubmit="return confirm('¿Eliminar este registro? Se revertirá el inventario o las cabezas que haya afectado.')">
                        @csrf
                        @method('DELETE')
                        <button class="text-red-600 hover:underline text-sm">Eliminar</button>
                    </form>
                @endcan
            </div>
        @empty
            <div class="bg-white shadow rounded p-8 text-center text-gray-500">Aún no hay registros para este animal.</div>
        @endforelse
    </div>

    <div class="mt-4">{{ $records->links() }}</div>
@endsection