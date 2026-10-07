@php
    $rows = $monthly['months'];
    $totals = $monthly['totals'];
    $hasData = $totals['sales'] > 0 || $totals['cost'] > 0;
    $money = fn ($v) => ($v < 0 ? '−' : '') . '$' . number_format(abs($v), 0);
@endphp

@include('partials.chart-assets')

<div class="bg-white shadow rounded mb-6 p-5">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
        <div>
            <h2 class="font-semibold">Ventas vs costos por mes</h2>
            <p class="text-sm text-gray-500">Compras de animales, alimento y sanidad frente a lo vendido.</p>
        </div>

        <form method="GET" action="{{ route('dashboard') }}">
            <select name="months" onchange="this.form.submit()"
                    class="rounded border border-gray-300 px-3 py-1.5 text-sm">
                @foreach ([6 => 'Últimos 6 meses', 12 => 'Últimos 12 meses', 24 => 'Últimos 24 meses'] as $value => $label)
                    <option value="{{ $value }}" @selected($months === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </div>

    @if ($hasData)
        <div class="grid grid-cols-3 gap-3 mb-4 text-center">
            <div>
                <p class="text-xs text-gray-500">Ventas</p>
                <p class="font-bold text-green-700">{{ $money($totals['sales']) }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Costos</p>
                <p class="font-bold text-red-600">{{ $money($totals['cost']) }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Resultado</p>
                <p class="font-bold {{ $totals['result'] >= 0 ? 'text-green-700' : 'text-red-600' }}">
                    {{ $money($totals['result']) }}
                </p>
            </div>
        </div>

        <div class="relative h-72"><canvas id="chart-monthly"></canvas></div>

        <p class="text-xs text-gray-500 mt-2">
            Los insumos se cuentan cuando se consumen (no cuando se compran) y la compra de animales, en el mes de ingreso.
            El resultado mensual es una referencia, no una utilidad contable.
        </p>

        <script>
            (function () {
                const U = window.ChartUtil;
                const rows = {{ \Illuminate\Support\Js::from($rows) }};

                new Chart(document.getElementById('chart-monthly'), {
                    type: 'bar',
                    data: {
                        labels: rows.map(function (r) { return U.monthLabel(r.month); }),
                        datasets: [
                            {
                                label: 'Ventas',
                                data: rows.map(function (r) { return r.sales; }),
                                backgroundColor: '#86efac',
                                borderColor: '#16a34a',
                                borderWidth: 1,
                                order: 2,
                            },
                            {
                                label: 'Costos',
                                data: rows.map(function (r) { return r.cost; }),
                                backgroundColor: '#fca5a5',
                                borderColor: '#dc2626',
                                borderWidth: 1,
                                order: 2,
                            },
                            {
                                type: 'line',
                                label: 'Resultado',
                                data: rows.map(function (r) { return r.result; }),
                                borderColor: '#2563eb',
                                backgroundColor: '#2563eb',
                                tension: 0.2,
                                pointRadius: 3,
                                order: 1,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        scales: {
                            x: { grid: { display: false } },
                            y: { ticks: { callback: function (v) { return U.moneyShort(v); } } },
                        },
                        plugins: {
                            legend: { position: 'bottom' },
                            tooltip: {
                                callbacks: {
                                    label: function (c) { return c.dataset.label + ': ' + U.money(c.parsed.y); },
                                    afterBody: function (items) {
                                        const r = rows[items[0].dataIndex];
                                        return [
                                            '',
                                            'Detalle de costos:',
                                            ' Compra de animales: ' + U.money(r.purchase),
                                            ' Alimento: ' + U.money(r.feed),
                                            ' Sanidad: ' + U.money(r.health),
                                        ];
                                    },
                                },
                            },
                        },
                    },
                });
            })();
        </script>
    @else
        <p class="text-sm text-gray-500 py-6 text-center">
            Aún no hay ventas ni costos en este período. La gráfica aparece cuando registres ventas, compras de animales
            o consumos de insumos con precio.
        </p>
    @endif
</div>