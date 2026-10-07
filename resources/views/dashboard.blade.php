@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h1 class="text-2xl font-bold mb-6">Dashboard</h1>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white shadow rounded p-5">
            <p class="text-sm text-gray-500">Productos</p>
            <p class="text-2xl sm:text-3xl font-bold">{{ $totalProducts }}</p>
        </div>

        <div class="bg-white shadow rounded p-5">
            <p class="text-sm text-gray-500">Valor del inventario</p>
            <p class="text-2xl sm:text-3xl font-bold break-words">${{ number_format($totalValue, 2) }}</p>
        </div>

        <div class="bg-white shadow rounded p-5">
            <p class="text-sm text-gray-500">Stock bajo</p>
            <p class="text-2xl sm:text-3xl font-bold {{ $lowStockCount > 0 ? 'text-red-600' : '' }}">
                {{ $lowStockCount }}
            </p>
        </div>

        <div class="bg-white shadow rounded p-5">
            <p class="text-sm text-gray-500">Categorías</p>
            <p class="text-2xl sm:text-3xl font-bold">{{ $totalCategories }}</p>
        </div>
    </div>
    
    @include('dashboard._chart')

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Stock bajo -->
        <div class="bg-white shadow rounded">
            <div class="px-5 py-3 border-b flex items-center justify-between">
                <h2 class="font-semibold">Productos con stock bajo</h2>
                <a href="{{ route('products.index', ['low' => 1]) }}" class="text-sm text-blue-600 hover:underline">
                    Ver todos
                </a>
            </div>
            <div class="divide-y">
                @forelse ($lowStock as $p)
                    <div class="px-5 py-3 flex items-center justify-between">
                        <div>
                            <a href="{{ route('products.edit', $p) }}" class="font-medium hover:underline">{{ $p->name }}</a>
                            <p class="text-sm text-gray-500">Mínimo: {{ $p->min_stock }}</p>
                        </div>
                        <span class="text-red-600 font-semibold">{{ rtrim(rtrim(number_format($p->quantity, 3), '0'), '.') }} {{ $p->unit }}</span>
                    </div>
                @empty
                    <p class="px-5 py-6 text-center text-gray-500">Todo el stock está en orden.</p>
                @endforelse
            </div>
        </div>

        <!-- Movimientos recientes -->
        <div class="bg-white shadow rounded">
            <div class="px-5 py-3 border-b">
                <h2 class="font-semibold">Movimientos recientes</h2>
            </div>
            <div class="divide-y">
                @forelse ($recentMovements as $m)
                    <div class="px-5 py-3 flex items-center justify-between">
                        <div>
                            <a href="{{ route('products.movements.index', $m->product) }}" class="font-medium hover:underline">
                                {{ $m->product->name }}
                            </a>
                            <p class="text-sm text-gray-500">
                                {{ match ($m->type) { 'in' => 'Entrada', 'out' => 'Salida', default => 'Ajuste' } }}
                                · {{ $m->created_at->diffForHumans() }}
                            </p>
                        </div>
                        <span class="font-semibold {{ $m->change >= 0 ? 'text-green-700' : 'text-red-700' }}">
                            {{ $m->change > 0 ? '+' : '' }}{{ $m->change }}
                        </span>
                    </div>
                @empty
                    <p class="px-5 py-6 text-center text-gray-500">Aún no hay movimientos registrados.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="bg-white shadow rounded mt-6">
    <div class="px-5 py-3 border-b flex items-center justify-between">
        <h2 class="font-semibold">Próximos controles y vacunas (30 días)</h2>
        <span class="text-sm text-gray-500">{{ $activeAnimals }} animales activos</span>
    </div>
    
    <div class="divide-y">
        @forelse ($upcomingDue as $d)
            @php $overdue = $d->next_due_date->lt(today()); @endphp
            <div class="px-5 py-3 flex items-center justify-between">
                <div>
                    <a href="{{ route('animals.show', $d->animal) }}" class="font-medium hover:underline">
                        {{ $d->animal->name }}
                    </a>
                    <p class="text-sm text-gray-500">
                        {{ $d->title ?? match($d->type) {
                            'vaccine' => 'Vacuna/Desparasitación',
                            'treatment' => 'Tratamiento',
                            default => 'Control',
                        } }}
                    </p>
                </div>
                <span class="text-sm font-semibold {{ $overdue ? 'text-red-600' : 'text-amber-600' }}">
                    {{ $overdue ? 'Vencido · ' : '' }}{{ $d->next_due_date->format('d/m/Y') }}
                </span>
            </div>
        @empty
            <p class="px-5 py-6 text-center text-gray-500">No hay controles pendientes en los próximos 30 días.</p>
        @endforelse
    </div>
</div>
<div class="bg-white shadow rounded mt-6">
    <div class="px-5 py-3 border-b flex items-center justify-between">
        <h2 class="font-semibold">Ventas por cobrar</h2>
        <span class="text-sm font-semibold {{ $receivableTotal > 0 ? 'text-red-600' : 'text-gray-500' }}">
            Total: ${{ number_format($receivableTotal, 2) }}
        </span>
    </div>
    <div class="divide-y">
        @forelse ($receivables->take(8) as $r)
            @php
                $balance = (float) $r->amount - (float) $r->amount_paid;
                $days = (int) abs($r->recorded_at->diffInDays(today()));
            @endphp
            <div class="px-5 py-3 flex items-center justify-between">
                <div>
                    <a href="{{ route('animals.show', $r->animal) }}" class="font-medium hover:underline">
                        {{ $r->animal->name }}
                    </a>
                    <p class="text-sm text-gray-500">
                        {{ $r->title ?: 'Sin comprador' }} · hace {{ $days }} {{ $days === 1 ? 'día' : 'días' }}
                    </p>
                </div>
                <span class="text-sm font-semibold text-red-600">${{ number_format($balance, 2) }}</span>
            </div>
        @empty
            <p class="px-5 py-6 text-center text-gray-500">No hay ventas pendientes de cobro.</p>
        @endforelse
    </div>
</div>
@endsection
