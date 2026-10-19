@extends('layouts.app')

@section('title', 'Conteos de inventario')

@section('content')
    @php
        $badge = [
            'open'      => 'bg-amber-100 text-amber-700',
            'applied'   => 'bg-green-100 text-green-700',
            'cancelled' => 'bg-gray-100 text-gray-600',
        ];
    @endphp

    <div class="flex items-center justify-between mb-2">
        <h1 class="text-2xl font-bold">Conteos de inventario</h1>
        @can('modify-inventory')
            @unless ($open)
                <a href="{{ route('counts.create') }}" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">
                    + Nuevo conteo
                </a>
            @endunless
        @endcan
    </div>

    <p class="text-gray-500 mb-4">
        Cuenta lo que hay en la bodega, compáralo con el sistema y ajusta las diferencias de una vez.
        Nada cambia en el inventario hasta que apliques los ajustes.
    </p>

    @if ($open)
        <div class="mb-4 rounded bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 flex flex-wrap items-center justify-between gap-2">
            <span>
                Hay un conteo abierto: <strong>{{ $open->name }}</strong>
                ({{ $openCounted }} de {{ $openTotal }} productos contados).
            </span>
            <a href="{{ route('counts.show', $open) }}" class="font-semibold underline">Continuar</a>
        </div>
    @endif

    <div class="bg-white shadow rounded overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Conteo</th>
                    <th class="px-4 py-3">Alcance</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Contados</th>
                    <th class="px-4 py-3">Abrió</th>
                    <th class="px-4 py-3">Cerró</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($counts as $c)
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $c->created_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">
                            <a href="{{ route('counts.show', $c) }}" class="font-medium text-blue-600 hover:underline">{{ $c->name }}</a>
                            @if ($c->blind) <span class="block text-xs text-gray-500">Conteo ciego</span> @endif
                        </td>
                        <td class="px-4 py-3">{{ $c->category?->name ?? 'Todos los productos' }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs px-2 py-1 rounded {{ $badge[$c->status] }}">{{ $c->statusLabel() }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $c->counted_count }} de {{ $c->items_count }}</td>
                        <td class="px-4 py-3">{{ $c->user?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($c->closer)
                                {{ $c->closer->name }}
                                <span class="block text-xs text-gray-500">{{ ($c->applied_at ?? $c->cancelled_at)?->format('d/m/Y H:i') }}</span>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-gray-500">Aún no hay conteos registrados.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $counts->links() }}</div>
@endsection