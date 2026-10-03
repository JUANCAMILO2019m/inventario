@extends('layouts.app')

@section('title', $animal->name)

@section('content')
    @php
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
            'sale'      => 'Venta parcial',
            'entry'     => 'Ingreso de animales',
        ];

        $detail = function ($r) {
            $title = $r->title ? ' · ' . $r->title : '';
            $amount = $r->amount !== null ? ' · $' . number_format($r->amount, 2) : '';

            return match ($r->type) {
                'weight'    => number_format($r->weight, 2) . ' kg',
                'mortality' => '−' . $r->heads . ' cabezas' . $title,
                'sale'      => '−' . $r->heads . ' cabezas' . $title . $amount,
                'entry'     => '+' . $r->heads . ' cabezas' . $title . $amount,
                default     => $r->title ?? '—',
            };
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

    @if ($stats)
        {{-- Panel de lote --}}
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
                <p class="text-2xl font-bold">{{ $stats['sold'] }}</p>
            </div>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Inversión</p>
                <p class="text-xl font-bold">{{ $stats['invested'] > 0 ? '$' . number_format($stats['invested'], 2) : '—' }}</p>
                @if ($stats['cost_per_head'] !== null)
                    <p class="text-xs text-gray-500 mt-1">${{ number_format($stats['cost_per_head'], 2) }} por cabeza</p>
                @endif
            </div>
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Ventas</p>
                <p class="text-xl font-bold">{{ $stats['revenue'] > 0 ? '$' . number_format($stats['revenue'], 2) : '—' }}</p>
            </div>
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Peso promedio (inicial → último)</p>
                <p class="text-xl font-bold">
                    {{ $animal->initial_weight !== null ? number_format($animal->initial_weight, 2) . ' kg' : '—' }}
                    →
                    {{ $stats['last_weight'] !== null ? number_format($stats['last_weight'], 2) . ' kg' : '—' }}
                </p>
                @if ($stats['weight_gain'] !== null)
                    <p class="text-xs text-gray-500 mt-1">
                        {{ $stats['weight_gain'] >= 0 ? '+' : '' }}{{ number_format($stats['weight_gain'], 2) }} kg por cabeza
                    </p>
                @endif
            </div>
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Ingreso</p>
                <p class="text-xl font-bold">{{ $animal->entry_date?->format('d/m/Y') ?? '—' }}</p>
                <p class="text-xs text-gray-500 mt-1">{{ $animal->supplier ?? 'Sin proveedor' }}</p>
            </div>
        </div>
    @else
        {{-- Ficha individual --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Estado</p>
                <p class="font-semibold">{{ $statusLabel }}</p>
            </div>
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Último peso registrado</p>
                @php $lastWeight = $animal->lastWeight(); @endphp
                <p class="font-semibold">
                    {{ $lastWeight ? number_format($lastWeight->weight, 2) . ' kg' : 'Sin registrar' }}
                </p>
            </div>
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Fecha de nacimiento</p>
                <p class="font-semibold">{{ $animal->birth_date?->format('d/m/Y') ?? '—' }}</p>
            </div>
        </div>
    @endif

    @if ($animal->description)
        <div class="bg-white shadow rounded p-4 mb-6">
            <p class="text-sm text-gray-500 mb-1">Descripción</p>
            <p>{{ $animal->description }}</p>
        </div>
    @endif

    @can('modify-inventory')
        <div class="bg-white shadow rounded p-4 mb-6">
            <h2 class="font-semibold mb-3">Registrar evento</h2>

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
                            @if ($animal->isLot())
                                <optgroup label="Movimientos de cabezas">
                                    <option value="mortality" @selected(old('type') === 'mortality')>Baja (mortalidad)</option>
                                    <option value="sale" @selected(old('type') === 'sale')>Venta parcial</option>
                                    <option value="entry" @selected(old('type') === 'entry')>Ingreso de animales</option>
                                </optgroup>
                            @endif
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
                        <input type="number" step="0.01" min="0" name="weight" data-req="1" value="{{ old('weight') }}"
                                class="w-full rounded border border-gray-300 px-3 py-2">
                    </div>

                    <div class="field-heads hidden">
                        <label class="block text-sm font-medium mb-1">Cabezas *</label>
                        <input type="number" step="1" min="1" name="heads" data-req="1" value="{{ old('heads') }}"
                                class="w-full rounded border border-gray-300 px-3 py-2">
                    </div>

                    <div class="field-amount hidden">
                        <label class="block text-sm font-medium mb-1" id="amount-label">Valor</label>
                        <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}"
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

                @error('weight') <p class="text-red-600 text-sm mb-2">{{ $message }}</p> @enderror
                @error('heads') <p class="text-red-600 text-sm mb-2">{{ $message }}</p> @enderror
                @error('amount') <p class="text-red-600 text-sm mb-2">{{ $message }}</p> @enderror
                @error('product_quantity') <p class="text-red-600 text-sm mb-2">{{ $message }}</p> @enderror
                @error('type') <p class="text-red-600 text-sm mb-2">{{ $message }}</p> @enderror

                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded">
                    Guardar registro
                </button>
            </form>
        </div>

        <script>
            (function () {
                const typeSelect = document.getElementById('record-type');
                const form = document.getElementById('record-form');

                const config = {
                    feeding:   { show: ['product'], title: ['Detalle', 'Ej: Concentrado de inicio'] },
                    vaccine:   { show: ['product', 'next'], title: ['Vacuna / Producto', 'Ej: Triple viral'] },
                    weight:    { show: ['weight'], title: null },
                    treatment: { show: ['next'], title: ['Diagnóstico / Tratamiento', 'Ej: Diarrea'] },
                    mortality: { show: ['heads'], title: ['Causa', 'Ej: Enfermedad, accidente'] },
                    sale:      { show: ['heads', 'amount'], title: ['Comprador', 'Nombre del comprador'], amount: 'Valor de la venta' },
                    entry:     { show: ['heads', 'amount'], title: ['Origen / Proveedor', 'Ej: Granja San José'], amount: 'Costo de la compra' },
                };

                function apply() {
                    const cfg = config[typeSelect.value];

                    ['title', 'weight', 'heads', 'amount', 'product', 'next'].forEach(name => {
                        const visible = name === 'title' ? cfg.title !== null : cfg.show.includes(name);

                        form.querySelectorAll('.field-' + name).forEach(box => {
                            box.classList.toggle('hidden', !visible);
                            box.querySelectorAll('input, select').forEach(el => {
                                el.disabled = !visible;
                                el.required = visible && el.dataset.req === '1';
                            });
                        });
                    });

                    if (cfg.title) {
                        document.getElementById('title-label').textContent = cfg.title[0];
                        document.getElementById('title-input').placeholder = cfg.title[1];
                    }

                    if (cfg.amount) {
                        document.getElementById('amount-label').textContent = cfg.amount;
                    }
                }

                typeSelect.addEventListener('change', apply);
                apply();
            })();
        </script>
    @endcan

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
                        <td class="px-4 py-3">{{ $detail($r) }}</td>
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