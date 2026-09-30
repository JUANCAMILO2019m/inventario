@extends('layouts.app')

@section('title', 'Inventario')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Productos</h1>
        @can('modify-inventory')
            <a href="{{ route('products.create') }}"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                + Nuevo producto
            </a>
        @endcan
    </div>

    <form method="GET" action="{{ route('products.index') }}"
        class="bg-white shadow rounded p-4 mb-4 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium mb-1">Buscar</label>
            <input type="text" name="q" value="{{ request('q') }}"
                    placeholder="Nombre, SKU o ubicación"
                    class="w-full rounded border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Categoría</label>
            <select name="category"
                    class="rounded border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400">
                <option value="">Todas</option>
                <option value="none" @selected(request('category') === 'none')>Sin categoría</option>
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}" @selected(request('category') == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>

        <label class="flex items-center gap-2 pb-2">
            <input type="checkbox" name="low" value="1" @checked(request()->boolean('low'))>
            <span class="text-sm">Solo stock bajo</span>
        </label>

        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Filtrar</button>
            @if (request()->hasAny(['q', 'category', 'low']))
                <a href="{{ route('products.index') }}"
                    class="px-4 py-2 rounded border border-gray-300 hover:bg-gray-50">Limpiar</a>
            @endif
        </div>
        <a href="{{ route('products.export', request()->query()) }}"
        class="px-4 py-2 rounded border border-green-600 text-green-700 hover:bg-green-50">
            Exportar Excel
        </a>
    </form>

    {{-- Vista de tabla (pantallas medianas en adelante) --}}
<div class="hidden md:block bg-white shadow rounded overflow-x-auto">
    <table class="w-full text-left">
        <thead class="bg-gray-50 text-sm uppercase text-gray-500">
            <tr>
                <th class="px-4 py-3"></th>
                <th class="px-4 py-3">Nombre</th>
                <th class="px-4 py-3">Categoría</th>
                <th class="px-4 py-3">Cantidad</th>
                <th class="px-4 py-3">Ubicación</th>
                <th class="px-4 py-3">Precio</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($products as $p)
                <tr>
                    <td class="px-4 py-3">
                        @if ($p->photo)
                            <img src="{{ Storage::url($p->photo) }}" alt="{{ $p->name }}" class="w-10 h-10 object-cover rounded">
                        @else
                            <div class="w-10 h-10 bg-gray-100 rounded flex items-center justify-center text-gray-300 text-xs">—</div>
                        @endif
                    </td>
                    <td class="px-4 py-3 font-medium">{{ $p->name }}</td>
                    <td class="px-4 py-3">{{ $p->category?->name ?? '—' }}</td>
                    <td class="px-4 py-3">
                        {{ rtrim(rtrim(number_format($p->quantity, 3), '0'), '.') }} {{ $p->unit }}
                        @if ($p->quantity <= $p->min_stock)
                            <span class="ml-1 text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded">Stock bajo</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">{{ $p->location ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $p->price !== null ? '$' . number_format($p->price, 2) : '—' }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <a href="{{ route('products.movements.index', $p) }}" class="text-gray-700 hover:underline mr-3">Movimientos</a>
                        @can('modify-inventory')
                            <a href="{{ route('products.edit', $p) }}" class="text-blue-600 hover:underline">Editar</a>
                            <form action="{{ route('products.destroy', $p) }}" method="POST" class="inline"
                                onsubmit="return confirm('¿Eliminar {{ $p->name }}?')">
                                @csrf
                                @method('DELETE')
                                <button class="text-red-600 hover:underline ml-3">Eliminar</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                        @if (request()->hasAny(['q', 'category', 'low']))
                            Ningún producto coincide con los filtros.
                        @else
                            Aún no hay productos. Crea el primero con el botón de arriba.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Vista de tarjetas (pantallas pequeñas) --}}
<div class="md:hidden space-y-3">
    @forelse ($products as $p)
        <div class="bg-white shadow rounded p-4">
            <div class="flex gap-3">
                @if ($p->photo)
                    <img src="{{ Storage::url($p->photo) }}" alt="{{ $p->name }}" class="w-14 h-14 object-cover rounded flex-shrink-0">
                @else
                    <div class="w-14 h-14 bg-gray-100 rounded flex items-center justify-center text-gray-300 text-xs flex-shrink-0">—</div>
                @endif

                <div class="flex-1 min-w-0">
                    <p class="font-semibold truncate">{{ $p->name }}</p>
                    <p class="text-sm text-gray-500">{{ $p->category?->name ?? 'Sin categoría' }}</p>

                    <div class="flex items-center gap-2 mt-1">
                        <span class="text-sm">Cantidad: <strong>{{ rtrim(rtrim(number_format($p->quantity, 3), '0'), '.') }} {{ $p->unit }}</strong></span>
                        @if ($p->quantity <= $p->min_stock)
                            <span class="text-xs bg-red-100 text-red-700 px-2 py-0.5 rounded">Stock bajo</span>
                        @endif
                    </div>

                    @if ($p->location)
                        <p class="text-sm text-gray-500">📍 {{ $p->location }}</p>
                    @endif

                    @if ($p->price !== null)
                        <p class="text-sm text-gray-700 font-medium mt-1">${{ number_format($p->price, 2) }}</p>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-4 mt-3 pt-3 border-t text-sm">
                <a href="{{ route('products.movements.index', $p) }}" class="text-gray-700 hover:underline">Movimientos</a>
                @can('modify-inventory')
                    <a href="{{ route('products.edit', $p) }}" class="text-blue-600 hover:underline">Editar</a>
                    <form action="{{ route('products.destroy', $p) }}" method="POST"
                        onsubmit="return confirm('¿Eliminar {{ $p->name }}?')">
                        @csrf
                        @method('DELETE')
                        <button class="text-red-600 hover:underline">Eliminar</button>
                    </form>
                @endcan
            </div>
        </div>
    @empty
        <div class="bg-white shadow rounded p-8 text-center text-gray-500">
            @if (request()->hasAny(['q', 'category', 'low']))
                Ningún producto coincide con los filtros.
            @else
                Aún no hay productos. Crea el primero con el botón de arriba.
            @endif
        </div>
    @endforelse
</div>

    <div class="mt-4">{{ $products->links() }}</div>
@endsection