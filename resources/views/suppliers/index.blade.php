@extends('layouts.app')

@section('title', 'Proveedores')

@section('content')
    @php $money = fn ($v) => '$' . number_format((float) $v, 2); @endphp

    <div class="flex items-center justify-between mb-4">
        <h1 class="text-2xl font-bold">Proveedores</h1>
        @can('modify-inventory')
            <a href="{{ route('suppliers.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                + Nuevo proveedor
            </a>
        @endcan
    </div>

    <div class="bg-white shadow rounded overflow-x-auto">
        <table class="w-full text-left">
            <thead class="bg-gray-50 text-sm uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3">Contacto</th>
                    <th class="px-4 py-3">Teléfono</th>
                    <th class="px-4 py-3">NIT</th>
                    <th class="px-4 py-3">Compras</th>
                    <th class="px-4 py-3">Total comprado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($suppliers as $s)
                    <tr>
                        <td class="px-4 py-3 font-medium">
                            {{ $s->name }}
                            @if ($s->email) <span class="block text-xs text-gray-500">{{ $s->email }}</span> @endif
                        </td>
                        <td class="px-4 py-3">{{ $s->contact_name ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $s->phone ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $s->tax_id ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($s->purchases_count)
                                <a href="{{ route('purchases.index', ['supplier' => $s->id]) }}" class="text-blue-600 hover:underline">
                                    {{ $s->purchases_count }}
                                </a>
                            @else
                                0
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $money($s->purchases_total ?? 0) }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            @can('modify-inventory')
                                <a href="{{ route('suppliers.edit', $s) }}" class="text-blue-600 hover:underline">Editar</a>
                                <form action="{{ route('suppliers.destroy', $s) }}" method="POST" class="inline"
                                      onsubmit="return confirm('¿Eliminar a {{ $s->name }}?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-red-600 hover:underline ml-3">Eliminar</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">Aún no hay proveedores registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $suppliers->links() }}</div>
@endsection