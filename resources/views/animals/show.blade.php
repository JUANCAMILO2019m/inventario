@extends('layouts.app')

@section('title', $animal->name)

@section('content')
    <div class="flex items-start justify-between mb-4">
        <div>
            <h1 class="text-2xl font-bold">{{ $animal->name }}</h1>
            <p class="text-gray-500">
                {{ $animal->species }}{{ $animal->breed ? ' · ' . $animal->breed : '' }}
                · {{ $animal->type === 'lot' ? 'Lote de ' . $animal->quantity : 'Individual' }}
            </p>
        </div>
        <div class="flex items-center gap-3">
            @can('modify-inventory')
                <a href="{{ route('animals.edit', $animal) }}" class="text-blue-600 hover:underline">Editar</a>
            @endcan
            <a href="{{ route('animals.index') }}" class="text-gray-600 hover:underline">← Volver</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Estado</p>
            <p class="font-semibold">{{ match($animal->status) { 'active' => 'Activo', 'sold' => 'Vendido', default => 'Baja' } }}</p>
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
                            <option value="feeding">Alimentación</option>
                            <option value="vaccine">Vacuna / Desparasitación</option>
                            <option value="weight">Pesaje</option>
                            <option value="treatment">Tratamiento</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Fecha *</label>
                        <input type="date" name="recorded_at" value="{{ old('recorded_at', date('Y-m-d')) }}"
                            class="w-full rounded border border-gray-300 px-3 py-2" required>
                    </div>

                    <div class="field-title">
                        <label class="block text-sm font-medium mb-1">Título / Nombre</label>
                        <input type="text" name="title" value="{{ old('title') }}"
                            placeholder="Ej: Triple viral, Diarrea..."
                            class="w-full rounded border border-gray-300 px-3 py-2">
                    </div>

                    <div class="field-weight hidden">
                        <label class="block text-sm font-medium mb-1">Peso (kg) *</label>
                        <input type="number" step="0.01" min="0" name="weight" value="{{ old('weight') }}"
                            class="w-full rounded border border-gray-300 px-3 py-2">
                    </div>

                    <div class="field-product">
                        <label class="block text-sm font-medium mb-1">Insumo del inventario</label>
                        <select name="product_id" class="w-full rounded border border-gray-300 px-3 py-2">
                            <option value="">— Ninguno —</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}" @selected(old('product_id') == $p->id)>
                                    {{ $p->name }} (disp: {{ $p->quantity }})
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

                function toggleFields() {
                    const type = typeSelect.value;

                    form.querySelectorAll('.field-weight').forEach(el => {
                        el.classList.toggle('hidden', type !== 'weight');
                    });

                    form.querySelectorAll('.field-product').forEach(el => {
                        el.classList.toggle('hidden', !['feeding', 'vaccine'].includes(type));
                    });

                    form.querySelectorAll('.field-next').forEach(el => {
                        el.classList.toggle('hidden', !['vaccine', 'treatment'].includes(type));
                    });

                    form.querySelectorAll('.field-title').forEach(el => {
                        el.classList.toggle('hidden', type === 'weight');
                    });
                }

                typeSelect.addEventListener('change', toggleFields);
                toggleFields();
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
                        <td class="px-4 py-3">
                            {{ match($r->type) {
                                'feeding' => 'Alimentación',
                                'vaccine' => 'Vacuna/Desparasitación',
                                'weight' => 'Pesaje',
                                default => 'Tratamiento',
                            } }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($r->type === 'weight')
                                {{ number_format($r->weight, 2) }} kg
                            @else
                                {{ $r->title ?? '—' }}
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @if ($r->product)
                                {{ $r->product->name }} ({{ $r->product_quantity }})
                            @else
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $r->next_due_date?->format('d/m/Y') ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $r->notes ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">
                            @can('modify-inventory')
                                <form action="{{ route('animals.records.destroy', [$animal, $r]) }}" method="POST"
                                    onsubmit="return confirm('¿Eliminar este registro? Si descontó inventario, se revertirá.')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-red-600 hover:underline text-sm">Eliminar</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-gray-500">Aún no hay registros para este animal.</td>
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
                    <span class="font-semibold">
                        {{ match($r->type) {
                            'feeding' => 'Alimentación',
                            'vaccine' => 'Vacuna/Desparasitación',
                            'weight' => 'Pesaje',
                            default => 'Tratamiento',
                        } }}
                    </span>
                    <span class="text-sm text-gray-500">{{ $r->recorded_at->format('d/m/Y') }}</span>
                </div>
                @if ($r->type === 'weight')
                    <p class="text-sm mt-1">{{ number_format($r->weight, 2) }} kg</p>
                @elseif ($r->title)
                    <p class="text-sm mt-1">{{ $r->title }}</p>
                @endif
                @if ($r->product)
                    <p class="text-sm text-gray-600">{{ $r->product->name }} ({{ $r->product_quantity }})</p>
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
                        onsubmit="return confirm('¿Eliminar este registro? Si descontó inventario, se revertirá.')">
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