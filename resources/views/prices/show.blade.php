@extends('layouts.app')

@section('title', $name)

@section('content')
    @php
        $money = fn ($v) => '$' . number_format((float) $v, 0);
        $input = 'rounded border border-gray-300 px-3 py-2 text-sm';
        $cityLabel = \App\Models\SipsaPrice::cityLabel($city);
    @endphp

    @include('partials.chart-assets')

    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
            <h1 class="text-2xl font-bold">{{ $name }}</h1>
            <p class="text-gray-500">Precio mayorista por kilo · {{ $cityLabel }}</p>
        </div>
        <a href="{{ route('prices.index', ['city' => $city]) }}" class="text-gray-600 hover:underline whitespace-nowrap">← Volver</a>
    </div>

    <form method="GET" action="{{ route('prices.show', $code) }}"
          class="bg-white shadow rounded p-4 mb-4 flex flex-wrap items-end gap-3">
        <div>
            <label class="block text-sm font-medium mb-1">Ciudad</label>
            <select name="city" onchange="this.form.submit()" class="{{ $input }}">
                @foreach ($cities as $c)
                    <option value="{{ $c }}" @selected($c === $city)>{{ \App\Models\SipsaPrice::cityLabel($c) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium mb-1">Período</label>
            <select name="days" onchange="this.form.submit()" class="{{ $input }}">
                @foreach ([30 => '30 días', 90 => '90 días', 180 => '6 meses', 365 => '1 año'] as $d => $label)
                    <option value="{{ $d }}" @selected($days === $d)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </form>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Precio actual</p>
            <p class="text-2xl font-bold">{{ $stats['current'] !== null ? $money($stats['current']) : '—' }}</p>
            @if ($stats['date']) <p class="text-xs text-gray-500">{{ $stats['date']->format('d/m/Y') }}</p> @endif
        </div>
        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Mínimo del período</p>
            <p class="text-2xl font-bold text-green-700">{{ $stats['min'] !== null ? $money($stats['min']) : '—' }}</p>
        </div>
        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Máximo del período</p>
            <p class="text-2xl font-bold text-red-600">{{ $stats['max'] !== null ? $money($stats['max']) : '—' }}</p>
        </div>
        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Promedio del período</p>
            <p class="text-2xl font-bold">{{ $stats['avg'] !== null ? $money($stats['avg']) : '—' }}</p>
            @if ($stats['avg'] && $stats['current'])
                @php $vs = ($stats['current'] - $stats['avg']) / $stats['avg'] * 100; @endphp
                <p class="text-xs text-gray-500">
                    Hoy está {{ abs($vs) < 0.05 ? 'igual' : number_format(abs($vs), 1) . '% ' . ($vs > 0 ? 'por encima' : 'por debajo') }}
                </p>
            @endif
        </div>
    </div>

    <div class="bg-white shadow rounded p-4 mb-6">
        <h2 class="font-semibold">Historial de precios</h2>

        @if ($stats['count'] >= 2)
            <div class="relative h-72 mt-2"><canvas id="chart-price"></canvas></div>
            <p class="text-xs text-gray-500 mt-2">{{ $stats['count'] }} fechas con precio en el período.</p>
        @else
            <p class="text-sm text-gray-500 mt-3">No hay suficientes datos en este período para dibujar la gráfica.</p>
        @endif
    </div>

    <div class="bg-white shadow rounded overflow-x-auto">
        <div class="px-4 py-3 border-b">
            <h2 class="font-semibold">Precio en cada ciudad</h2>
            <p class="text-sm text-gray-500">Dato más reciente de las últimas dos semanas, de la más barata a la más cara.</p>
        </div>
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Ciudad</th>
                    <th class="px-4 py-3">Precio por kg</th>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Diferencia con {{ $cityLabel }}</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($byCity as $r)
                    @php
                        $diff = ($mine && $r->city !== $city) ? ($r->price - $mine) / $mine * 100 : null;
                        $old = $r->captured_on->lt($maxAll->copy()->subDays(3));
                    @endphp
                    <tr class="{{ $r->city === $city ? 'bg-blue-50' : '' }}">
                        <td class="px-4 py-2 font-medium">
                            <a href="{{ route('prices.show', ['code' => $code, 'city' => $r->city, 'days' => $days]) }}" class="hover:underline">
                                {{ \App\Models\SipsaPrice::cityLabel($r->city) }}
                            </a>
                        </td>
                        <td class="px-4 py-2 font-semibold">{{ $money($r->price) }}</td>
                        <td class="px-4 py-2 whitespace-nowrap {{ $old ? 'text-amber-700' : '' }}">{{ $r->captured_on->format('d/m/Y') }}</td>
                        <td class="px-4 py-2">
                            @if ($diff === null)
                                <span class="text-gray-400">—</span>
                            @else
                                <span class="font-semibold {{ $diff > 0.05 ? 'text-red-600' : ($diff < -0.05 ? 'text-green-700' : 'text-gray-500') }}">
                                    {{ $diff > 0 ? '+' : '' }}{{ number_format($diff, 1) }}%
                                </span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="text-xs text-gray-500 mt-3">
        Pesos por kilo, promedio mayorista (fuente: DANE · SIPSA). La diferencia compara con la ciudad elegida arriba.
    </p>

    @if ($stats['count'] >= 2)
        <script>
            (function () {
                const U = window.ChartUtil;
                const raw = {{ \Illuminate\Support\Js::from($points) }};
                const pts = raw.map(function (p) { return { x: U.toDay(p.date), y: p.price }; });

                new Chart(document.getElementById('chart-price'), {
                    type: 'line',
                    data: {
                        datasets: [{
                            label: 'Precio por kg',
                            data: pts,
                            parsing: false,
                            borderColor: '#16a34a',
                            backgroundColor: 'rgba(22, 163, 74, 0.10)',
                            fill: true,
                            borderWidth: 2,
                            tension: 0.15,
                            pointRadius: pts.length > 60 ? 0 : 3,
                            pointHoverRadius: 5,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                type: 'linear',
                                grace: '2%',
                                grid: { display: false },
                                ticks: { precision: 0, maxTicksLimit: 8, callback: function (v) { return U.dayLabel(v); } },
                            },
                            y: {
                                grace: '8%',
                                ticks: { callback: function (v) { return U.moneyShort(v); } },
                                title: { display: true, text: 'pesos por kg' },
                            },
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    title: function (items) { return U.fullDate(items[0].raw.x); },
                                    label: function (c) { return U.money(c.raw.y) + ' por kg'; },
                                },
                            },
                        },
                    },
                });
            })();
        </script>
    @endif
@endsection