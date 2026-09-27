@extends('layouts.app')

@section('title', 'Categorías')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Categorías</h1>
        @can('modify-inventory')
            <a href="{{ route('categories.create') }}"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                + Nueva categoría
            </a>
        @endcan
    </div>

    {{-- Vista de tabla --}}
<div class="hidden md:block bg-white shadow rounded overflow-x-auto">
    <table class="w-full text-left">
        <thead class="bg-gray-50 text-sm uppercase text-gray-500">
            <tr>
                <th class="px-4 py-3">Nombre</th>
                <th class="px-4 py-3">Productos</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y">
            @forelse ($categories as $c)
                <tr>
                    <td class="px-4 py-3 font-medium">{{ $c->name }}</td>
                    <td class="px-4 py-3">{{ $c->products_count }}</td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        @can('modify-inventory')
                            <a href="{{ route('categories.edit', $c) }}" class="text-blue-600 hover:underline">Editar</a>
                            <form action="{{ route('categories.destroy', $c) }}" method="POST" class="inline"
                                onsubmit="return confirm('¿Eliminar {{ $c->name }}? Sus {{ $c->products_count }} productos quedarán sin categoría.')">
                                @csrf
                                @method('DELETE')
                                <button class="text-red-600 hover:underline ml-3">Eliminar</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="px-4 py-8 text-center text-gray-500">Aún no hay categorías.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Vista de tarjetas --}}
<div class="md:hidden space-y-3">
    @forelse ($categories as $c)
        <div class="bg-white shadow rounded p-4 flex items-center justify-between">
            <div>
                <p class="font-semibold">{{ $c->name }}</p>
                <p class="text-sm text-gray-500">{{ $c->products_count }} producto(s)</p>
            </div>
           <div class="flex items-center gap-3 text-sm">
                @can('modify-inventory')
                    <a href="{{ route('categories.edit', $c) }}" class="text-blue-600 hover:underline">Editar</a>
                    <form action="{{ route('categories.destroy', $c) }}" method="POST"
                        onsubmit="return confirm('¿Eliminar a {{ $c->name }}? Sus {{ $c->products_count }} productos quedarán sin categoría.')">
                        @csrf
                        @method('DELETE')
                        <button class="text-red-600 hover:underline">Eliminar</button>
                    </form>
                @endcan
            </div>
        </div>
    @empty
        <div class="bg-white shadow rounded p-8 text-center text-gray-500">Aún no hay categorías.</div>
    @endforelse
</div>
@endsection