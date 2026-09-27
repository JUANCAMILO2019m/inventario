@extends('layouts.app')

@section('title', 'Usuarios')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Usuarios</h1>
        <a href="{{ route('users.create') }}"
           class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
            + Nuevo usuario
        </a>
    </div>

    {{-- Vista de tabla (pantallas medianas en adelante) --}}
    <div class="hidden md:block bg-white shadow rounded overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-gray-50 text-sm uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3">Correo</th>
                    <th class="px-4 py-3">Rol</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($users as $u)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $u->name }}</td>
                        <td class="px-4 py-3">{{ $u->email }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs px-2 py-1 rounded
                                @if($u->role === 'superadmin') bg-purple-100 text-purple-700
                                @elseif($u->role === 'admin') bg-blue-100 text-blue-700
                                @else bg-gray-100 text-gray-700 @endif">
                                {{ ucfirst($u->role) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <a href="{{ route('users.edit', $u) }}" class="text-blue-600 hover:underline">Editar</a>
                            @if ($u->id !== auth()->id())
                                <form action="{{ route('users.destroy', $u) }}" method="POST" class="inline"
                                      onsubmit="return confirm('¿Eliminar a {{ $u->name }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-red-600 hover:underline ml-3">Eliminar</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Vista de tarjetas (pantallas pequeñas) --}}
    <div class="md:hidden space-y-3">
        @foreach ($users as $u)
            <div class="bg-white shadow rounded p-4">
                <p class="font-semibold">{{ $u->name }}</p>
                <p class="text-sm text-gray-500">{{ $u->email }}</p>
                <span class="text-xs px-2 py-1 rounded inline-block mt-1
                    @if($u->role === 'superadmin') bg-purple-100 text-purple-700
                    @elseif($u->role === 'admin') bg-blue-100 text-blue-700
                    @else bg-gray-100 text-gray-700 @endif">
                    {{ ucfirst($u->role) }}
                </span>
                <div class="flex items-center gap-3 mt-3 pt-3 border-t text-sm">
                    <a href="{{ route('users.edit', $u) }}" class="text-blue-600 hover:underline">Editar</a>
                    @if ($u->id !== auth()->id())
                        <form action="{{ route('users.destroy', $u) }}" method="POST"
                              onsubmit="return confirm('¿Eliminar a {{ $u->name }}?')">
                            @csrf
                            @method('DELETE')
                            <button class="text-red-600 hover:underline">Eliminar</button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-4">{{ $users->links() }}</div>
@endsection