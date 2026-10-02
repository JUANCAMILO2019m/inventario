@extends('layouts.app')

@section('title', 'Animales')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Animales</h1>
        @can('modify-inventory')
            <a href="{{ route('animals.create') }}"
                class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                + Nuevo animal
            </a>
        @endcan
    </div>

    <form method="GET" action="{{ route('animals.index') }}"
            class="bg-white shadow rounded p-4 mb-4 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium mb-1">Buscar</label>
            <input type="text" name="q" value="{{ request('q') }}"
                    placeholder="Nombre, código o especie"
                    class="w-full rounded border border-gray-300 px-3 py-2">
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Tipo</label>
            <select name="type" class="rounded border border-gray-300 px-3 py-2">
                <option value="">Todos</option>
                <option value="individual" @selected(request('type') === 'individual')>Individual</option>
                <option value="lot" @selected(request('type') === 'lot')>Lote</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium mb-1">Estado</label>
            <select name="status" class="rounded border border-gray-300 px-3 py-2">
                <option value="">Todos</option>
                <option value="active" @selected(request('status') === 'active')>Activo</option>
                <option value="sold" @selected(request('status') === 'sold')>Vendido</option>
                <option value="dead" @selected(request('status') === 'dead')>Baja</option>
            </select>
        </div>

        <div class="flex gap-2">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Filtrar</button>
            @if (request()->hasAny(['q', 'type', 'status']))
                <a href="{{ route('animals.index') }}"
                    class="px-4 py-2 rounded border border-gray-300 hover:bg-gray-50">Limpiar</a>
            @endif
        </div>
        <a href="{{ route('animals.export', request()->query()) }}"
        class="px-4 py-2 rounded border border-green-600 text-green-700 hover:bg-green-50">
            Exportar Excel
        </a>
    </form>

    {{-- Vista de tabla --}}
    <div class="hidden md:block bg-white shadow rounded overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-gray-50 text-sm uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3"></th>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3">Tipo</th>
                    <th class="px-4 py-3">Especie</th>
                    <th class="px-4 py-3">Cantidad</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($animals as $a)
                    <tr>
                        <td class="px-4 py-3">
                            @if ($a->photo)
                                <img src="{{ $a->photo_url }}" alt="{{ $a->name }}" class="w-10 h-10 object-cover rounded">
                            @else
                                <div class="w-10 h-10 bg-gray-100 rounded flex items-center justify-center text-gray-300 text-xs">🐾</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 font-medium">
                            <a href="{{ route('animals.show', $a) }}" class="hover:underline">{{ $a->name }}</a>
                            @if ($a->code)
                                <span class="text-gray-400 text-sm block">{{ $a->code }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $a->type === 'lot' ? 'Lote' : 'Individual' }}</td>
                        <td class="px-4 py-3">{{ $a->species }}</td>
                        <td class="px-4 py-3">{{ $a->quantity }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs px-2 py-1 rounded
                                @if($a->status === 'active') bg-green-100 text-green-700
                                @elseif($a->status === 'sold') bg-blue-100 text-blue-700
                                @else bg-gray-100 text-gray-700 @endif">
                                {{ match($a->status) { 'active' => 'Activo', 'sold' => 'Vendido', default => 'Baja' } }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('animals.show', $a) }}" class="text-gray-700 hover:underline mr-3">Ver</a>
                            @can('modify-inventory')
                                <a href="{{ route('animals.edit', $a) }}" class="text-blue-600 hover:underline">Editar</a>
                                <form action="{{ route('animals.destroy', $a) }}" method="POST" class="inline"
                                        onsubmit="return confirm('¿Eliminar {{ $a->name }}?')">
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
                            @if (request()->hasAny(['q', 'type', 'status']))
                                Ningún animal coincide con los filtros.
                            @else
                                Aún no hay animales registrados.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Vista de tarjetas --}}
    <div class="md:hidden space-y-3">
        @forelse ($animals as $a)
            <div class="bg-white shadow rounded p-4">
                <div class="flex gap-3">
                    @if ($a->photo)
                        <img src="{{ $a->photo_url }}" alt="{{ $a->name }}" class="w-14 h-14 object-cover rounded flex-shrink-0">
                    @else
                        <div class="w-14 h-14 bg-gray-100 rounded flex items-center justify-center text-gray-300 flex-shrink-0">🐾</div>
                    @endif
                    <div class="flex-1 min-w-0">
                        <a href="{{ route('animals.show', $a) }}" class="font-semibold hover:underline">{{ $a->name }}</a>
                        <p class="text-sm text-gray-500">{{ $a->species }} · {{ $a->type === 'lot' ? 'Lote' : 'Individual' }}</p>
                        <p class="text-sm">Cantidad: <strong>{{ $a->quantity }}</strong></p>
                        <span class="text-xs px-2 py-1 rounded inline-block mt-1
                            @if($a->status === 'active') bg-green-100 text-green-700
                            @elseif($a->status === 'sold') bg-blue-100 text-blue-700
                            @else bg-gray-100 text-gray-700 @endif">
                            {{ match($a->status) { 'active' => 'Activo', 'sold' => 'Vendido', default => 'Baja' } }}
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-4 mt-3 pt-3 border-t text-sm">
                    <a href="{{ route('animals.show', $a) }}" class="text-gray-700 hover:underline">Ver</a>
                    @can('modify-inventory')
                        <a href="{{ route('animals.edit', $a) }}" class="text-blue-600 hover:underline">Editar</a>
                        <form action="{{ route('animals.destroy', $a) }}" method="POST"
                                onsubmit="return confirm('¿Eliminar {{ $a->name }}?')">
                            @csrf
                            @method('DELETE')
                            <button class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    @endcan
                </div>
            </div>
        @empty
            <div class="bg-white shadow rounded p-8 text-center text-gray-500">
                @if (request()->hasAny(['q', 'type', 'status']))
                    Ningún animal coincide con los filtros.
                @else
                    Aún no hay animales registrados.
                @endif
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $animals->links() }}</div>
@endsection