@extends('layouts.app')

@section('title', 'Actividad')

@section('content')
    @php
        $labels = [
            'name' => 'Nombre', 'email' => 'Correo', 'role' => 'Rol', 'password' => 'Contraseña',
            'sku' => 'SKU', 'price' => 'Precio', 'min_stock' => 'Stock mínimo', 'unit' => 'Unidad',
            'location' => 'Ubicación', 'notes' => 'Notas', 'category_id' => 'Categoría (id)', 'photo' => 'Foto',
            'status' => 'Estado', 'species' => 'Especie', 'breed' => 'Raza', 'sex' => 'Sexo',
            'birth_date' => 'Nacimiento', 'description' => 'Descripción', 'supplier' => 'Proveedor',
            'purchase_cost' => 'Costo de compra', 'entry_date' => 'Ingreso', 'initial_weight' => 'Peso inicial',
            'initial_quantity' => 'Cantidad inicial', 'code' => 'Código', 'type' => 'Tipo',
            'animal_id' => 'Animal (id)', 'recorded_at' => 'Fecha', 'title' => 'Título', 'heads' => 'Cabezas',
            'amount' => 'Valor', 'amount_paid' => 'Pagado', 'payment_status' => 'Estado de pago',
            'weight' => 'Peso', 'weight_type' => 'Tipo de peso', 'total_weight' => 'Peso total',
            'price_mode' => 'Modo de precio', 'unit_price' => 'Precio unitario',
            'product_id' => 'Producto (id)', 'product_quantity' => 'Cantidad usada', 'next_due_date' => 'Próximo control',
            'batch_id' => 'Faena', 'quantity' => 'Cantidad',
        ];

        $eventLabel = ['created' => 'Creó', 'updated' => 'Modificó', 'deleted' => 'Eliminó'];
        $eventColor = [
            'created' => 'bg-green-100 text-green-700',
            'updated' => 'bg-blue-100 text-blue-700',
            'deleted' => 'bg-red-100 text-red-700',
        ];

        $show = function ($v) {
            if (is_bool($v)) return $v ? 'sí' : 'no';
            if ($v === null || $v === '') return '—';
            if (is_array($v)) return json_encode($v, JSON_UNESCAPED_UNICODE);
            return \Illuminate\Support\Str::limit((string) $v, 60);
        };

        $input = 'rounded border border-gray-300 px-3 py-2';
    @endphp

    <h1 class="text-2xl font-bold mb-1">Actividad</h1>
    <p class="text-gray-500 mb-4">
        Quién creó, modificó o eliminó productos, categorías, animales, usuarios y registros de animales.
        Las cantidades de inventario se consultan en el historial de movimientos.
    </p>

    <form method="GET" action="{{ route('audit.index') }}"
          class="bg-white shadow rounded p-4 mb-4 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[160px]">
            <label class="block text-sm font-medium mb-1">Buscar</label>
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Nombre del elemento"
                   class="w-full {{ $input }}">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Usuario</label>
            <select name="user" class="{{ $input }}">
                <option value="">Todos</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}" @selected((string) request('user') === (string) $u->id)>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Acción</label>
            <select name="event" class="{{ $input }}">
                <option value="">Todas</option>
                @foreach ($eventLabel as $key => $label)
                    <option value="{{ $key }}" @selected(request('event') === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Tipo</label>
            <select name="type" class="{{ $input }}">
                <option value="">Todos</option>
                @foreach ($types as $class => $label)
                    <option value="{{ $class }}" @selected(request('type') === $class)>{{ $label }}</option>
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

        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Filtrar</button>
            @if (request()->hasAny(['q', 'user', 'event', 'type', 'from', 'to']))
                <a href="{{ route('audit.index') }}" class="px-4 py-2 rounded border border-gray-300 hover:bg-gray-50">Limpiar</a>
            @endif
        </div>
    </form>

    <div class="bg-white shadow rounded overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Usuario</th>
                    <th class="px-4 py-3">Acción</th>
                    <th class="px-4 py-3">Elemento</th>
                    <th class="px-4 py-3">Detalle</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($logs as $log)
                    @php $changes = $log->changes ?? []; @endphp
                    <tr class="align-top">
                        <td class="px-4 py-3 whitespace-nowrap">{{ $log->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">
                            {{ $log->user_name ?? 'Sistema' }}
                            @if ($log->ip_address)
                                <span class="block text-xs text-gray-400">{{ $log->ip_address }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs px-2 py-1 rounded {{ $eventColor[$log->event] ?? 'bg-gray-100 text-gray-700' }}">
                                {{ $eventLabel[$log->event] ?? $log->event }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="text-xs text-gray-500">{{ $log->typeLabel() }}</span>
                            <span class="block font-medium">{{ $log->label ?? '—' }}</span>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-600">
                            @if ($log->event === 'updated')
                                @foreach ($changes as $field => $pair)
                                    <div>
                                        <span class="font-medium">{{ $labels[$field] ?? $field }}:</span>
                                        {{ $show($pair[0] ?? null) }} → {{ $show($pair[1] ?? null) }}
                                    </div>
                                @endforeach
                            @else
                                @foreach (array_slice($changes, 0, 6, true) as $field => $value)
                                    <div>
                                        <span class="font-medium">{{ $labels[$field] ?? $field }}:</span> {{ $show($value) }}
                                    </div>
                                @endforeach
                                @if (count($changes) > 6)
                                    <div class="text-gray-400">… y {{ count($changes) - 6 }} campos más</div>
                                @endif
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                            Aún no hay actividad registrada. Aparecerá a partir de ahora, con cada cambio que se haga.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $logs->links() }}</div>
@endsection