@extends('layouts.app')

@section('title', 'Precios de referencia')

@section('content')
    @php
        $money = fn ($v) => '$' . number_format((float) $v, 0);
        $input = 'rounded border border-gray-300 px-3 py-2';
    @endphp

    <h1 class="text-2xl font-bold mb-1">Precios de referencia</h1>
    <p class="text-gray-500 mb-4">
        Precios mayoristas de productos agrícolas en las centrales de abasto, publicados por el DANE (SIPSA).
        Son una referencia del mercado, no el precio de tu proveedor.
    </p>

    @if ($empty ?? false)
        <div class="bg-white shadow rounded p-8 text-center text-gray-600">
            <p class="font-medium mb-1">Aún no hay precios cargados.</p>
            <p class="text-sm">Desde la consola del proyecto ejecuta <code class="bg-gray-100 px-1 rounded">php artisan sipsa:import --download</code> para traerlos desde el DANE.</p>
        </div>
    @else
        <form method="GET" action="{{ route('prices.index') }}"
                class="bg-white shadow rounded p-4 mb-4 flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-sm font-medium mb-1">Ciudad</label>
                <select name="city" onchange="this.form.submit()" class="{{ $input }}">
                    @foreach ($cities as $c)
                        <option value="{{ $c }}" @selected($c === $city)>{{ \App\Models\SipsaPrice::cityLabel($c) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex-1 min-w-[180px]">
                <label class="block text-sm font-medium mb-1">Buscar producto</label>
                <input type="text" name="q" value="{{ request('q') }}" placeholder="Papa, yuca, plátano…" class="w-full {{ $input }}">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">Ordenar por</label>
                <select name="sort" onchange="this.form.submit()" class="{{ $input }}">
                    <option value="name" @selected($sort === 'name')>Nombre</option>
                    <option value="up" @selected($sort === 'up')>Los que más subieron</option>
                    <option value="down" @selected($sort === 'down')>Los que más bajaron</option>
                    <option value="price" @selected($sort === 'price')>Precio, de mayor a menor</option>
                </select>
            </div>
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded">Buscar</button>
        </form>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Productos con precio</p>
                <p class="text-2xl font-bold">{{ $total }}</p>
            </div>
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Subieron (vs. dato anterior)</p>
                <p class="text-2xl font-bold {{ $rising ? 'text-red-600' : '' }}">{{ $rising }}</p>
            </div>
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Bajaron</p>
                <p class="text-2xl font-bold {{ $falling ? 'text-green-700' : '' }}">{{ $falling }}</p>
            </div>
            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Último dato de {{ \App\Models\SipsaPrice::cityLabel($city) }}</p>
                <p class="text-2xl font-bold">{{ $maxDate->format('d/m/Y') }}</p>
            </div>
        </div>

        <div class="bg-white shadow rounded overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Producto</th>
                        <th class="px-4 py-3">Precio por kg</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Vs. dato anterior</th>
                        <th class="px-4 py-3">Vs. hace ~30 días</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse ($items as $i)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $i['name'] }}</td>
                            <td class="px-4 py-3 font-semibold">{{ $money($i['price']) }}</td>
                            <td class="px-4 py-3 whitespace-nowrap {{ $i['stale'] ? 'text-amber-700' : '' }}">
                                {{ $i['date']->format('d/m/Y') }}
                                @if ($i['stale']) <span class="block text-xs">dato antiguo</span> @endif
                            </td>
                            @foreach (['prev_change', 'month_change'] as $key)
                                @php $c = $i[$key]; @endphp
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if ($c === null)
                                        <span class="text-gray-400">—</span>
                                    @else
                                        <span class="font-semibold {{ $c > 0.05 ? 'text-red-600' : ($c < -0.05 ? 'text-green-700' : 'text-gray-500') }}">
                                            {{ $c > 0 ? '+' : '' }}{{ number_format($c, 1) }}%
                                        </span>
                                    @endif
                                    @if ($key === 'prev_change' && $i['prev_date'])
                                        <span class="block text-xs text-gray-400">vs. {{ $i['prev_date']->format('d/m') }}</span>
                                    @endif
                                </td>
                            @endforeach
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('prices.show', ['code' => $i['code'], 'city' => $city]) }}"
                                    class="text-blue-600 hover:underline">Historial →</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">Ningún producto coincide con la búsqueda.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <p class="text-xs text-gray-500 mt-3">
            Precios en pesos por kilo, promedio mayorista por ciudad (fuente: DANE · SIPSA). En rojo lo que subió (más caro de comprar)
            y en verde lo que bajó. «Vs. dato anterior» compara con la captura previa del producto, que puede ser de uno o varios días atrás.
            @if ($import)
                Última carga a la base: {{ $import->created_at->format('d/m/Y H:i') }}.
            @endif
        </p>
    @endif
@endsection