@php
    $weightPoints = $charts['weight']['points'];
    $headsData = $charts['heads'];
    $feedData = $charts['feed'];

    $hasWeight = count($weightPoints) >= 2;
    $hasHeads = $headsData && $headsData['has_movements'];
    $hasFeed = count($feedData['weeks']) >= 1;
@endphp

@include('partials.chart-assets')

<div class="mb-6">
    <h2 class="text-xl font-bold mb-3">Gráficas</h2>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        {{-- Peso --}}
        <div class="bg-white shadow rounded p-4">
            <h3 class="font-semibold">Peso promedio por cabeza</h3>

            @if ($hasWeight)
                <div class="relative h-64 mt-2"><canvas id="chart-weight"></canvas></div>
                <p class="text-xs text-gray-500 mt-2">
                    ▲ peso inicial · ● pesaje · ■ venta (promedio del día). Relleno = báscula, vacío = estimado.
                </p>
            @else
                <p class="text-sm text-gray-500 mt-3">
                    Aún no hay suficientes datos. Registra al menos dos pesos (el peso inicial con la fecha de ingreso
                    cuentan como uno) para ver la evolución.
                </p>
            @endif
        </div>

        {{-- Cabezas (solo lotes) --}}
        @if ($animal->isLot())
            <div class="bg-white shadow rounded p-4">
                <h3 class="font-semibold">Cabezas del lote</h3>

                @if ($hasHeads)
                    <div class="relative h-64 mt-2"><canvas id="chart-heads"></canvas></div>
                    <p class="text-xs text-gray-500 mt-2">
                        Cada escalón es un movimiento. Pasa el cursor para ver qué ocurrió ese día.
                    </p>
                @else
                    <p class="text-sm text-gray-500 mt-3">
                        La gráfica aparece cuando registres una baja, una venta, un consumo propio o un ingreso.
                    </p>
                @endif
            </div>
        @endif

        {{-- Alimento --}}
        <div class="bg-white shadow rounded p-4 {{ $animal->isLot() ? 'lg:col-span-2' : '' }}">
            <h3 class="font-semibold">Consumo y costo de alimento</h3>

            @if ($hasFeed)
                <div class="relative h-64 mt-2"><canvas id="chart-feed"></canvas></div>

                @if ($feedData['unconvertible'])
                    <p class="text-xs text-amber-700 mt-2">
                        Parte del alimento está en unidades distintas a kg, g o lb (por ejemplo, bultos): no aparece en
                        las barras, pero sí en el costo.
                    </p>
                @endif

                @if ($feedData['missing_price'])
                    <p class="text-xs text-amber-700 mt-1">
                        Hay consumos de insumos sin precio: el costo acumulado está subestimado.
                    </p>
                @endif
            @else
                <p class="text-sm text-gray-500 mt-3">
                    Registra alimentaciones con un insumo del inventario para ver el consumo y su costo.
                </p>
            @endif
        </div>
    </div>
</div>

<script>
    (function () {
        const U = window.ChartUtil;

        @if ($hasWeight)
        (function () {
            const raw = {{ \Illuminate\Support\Js::from($weightPoints) }};
            const pts = raw.map(function (p) {
                return { x: U.toDay(p.date), y: p.weight, kind: p.kind, estimated: p.estimated };
            });
            const colors = { initial: '#6b7280', weighing: '#2563eb', sale: '#16a34a' };
            const styles = { initial: 'triangle', weighing: 'circle', sale: 'rect' };
            const kindOf = function (c) { return c.raw ? c.raw.kind : 'weighing'; };

            new Chart(document.getElementById('chart-weight'), {
                type: 'line',
                data: {
                    datasets: [{
                        label: 'Peso promedio por cabeza (kg)',
                        data: pts,
                        parsing: false,
                        borderColor: '#2563eb',
                        borderWidth: 2,
                        tension: 0.15,
                        pointRadius: 5,
                        pointHoverRadius: 7,
                        pointBorderWidth: 2,
                        pointStyle: function (c) { return styles[kindOf(c)]; },
                        pointBorderColor: function (c) { return colors[kindOf(c)]; },
                        pointBackgroundColor: function (c) {
                            return (c.raw && c.raw.estimated) ? '#ffffff' : colors[kindOf(c)];
                        },
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            type: 'linear',
                            grace: '4%',
                            grid: { display: false },
                            ticks: { precision: 0, maxTicksLimit: 8, callback: function (v) { return U.dayLabel(v); } },
                        },
                        y: { grace: '8%', title: { display: true, text: 'kg por cabeza' } },
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                title: function (items) { return U.fullDate(items[0].raw.x); },
                                label: function (c) {
                                    const p = c.raw;
                                    const what = p.kind === 'initial' ? 'Peso inicial'
                                        : p.kind === 'sale' ? 'Venta (promedio)' : 'Pesaje';
                                    const how = p.kind === 'initial' ? '' : (p.estimated ? ' · estimado' : ' · báscula');
                                    return what + ': ' + U.num(p.y) + ' kg' + how;
                                },
                            },
                        },
                    },
                },
            });
        })();
        @endif

        @if ($hasHeads)
        (function () {
            const raw = {{ \Illuminate\Support\Js::from($headsData['points']) }};
            const pts = raw.map(function (p) { return { x: U.toDay(p.date), y: p.heads, events: p.events }; });

            new Chart(document.getElementById('chart-heads'), {
                type: 'line',
                data: {
                    datasets: [{
                        label: 'Cabezas',
                        data: pts,
                        parsing: false,
                        stepped: 'after',
                        borderColor: '#7c3aed',
                        backgroundColor: 'rgba(124, 58, 237, 0.12)',
                        fill: true,
                        borderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            type: 'linear',
                            grace: '3%',
                            grid: { display: false },
                            ticks: { precision: 0, maxTicksLimit: 8, callback: function (v) { return U.dayLabel(v); } },
                        },
                        y: {
                            beginAtZero: true,
                            grace: '8%',
                            ticks: { precision: 0 },
                            title: { display: true, text: 'cabezas' },
                        },
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                title: function (items) { return U.fullDate(items[0].raw.x); },
                                label: function (c) { return c.raw.y + ' cabezas'; },
                                afterLabel: function (c) { return c.raw.events; },
                            },
                        },
                    },
                },
            });
        })();
        @endif

        @if ($hasFeed)
        (function () {
            const weeks = {{ \Illuminate\Support\Js::from($feedData['weeks']) }};

            new Chart(document.getElementById('chart-feed'), {
                type: 'bar',
                data: {
                    labels: weeks.map(function (w) { return U.dayLabel(U.toDay(w.week)); }),
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Alimento (kg)',
                            data: weeks.map(function (w) { return w.kg; }),
                            backgroundColor: '#93c5fd',
                            yAxisID: 'y',
                            order: 2,
                        },
                        {
                            type: 'line',
                            label: 'Costo acumulado',
                            data: weeks.map(function (w) { return w.cumulative; }),
                            borderColor: '#ea580c',
                            backgroundColor: '#ea580c',
                            tension: 0.2,
                            pointRadius: 3,
                            yAxisID: 'y1',
                            order: 1,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 12 } },
                        y: { beginAtZero: true, position: 'left', title: { display: true, text: 'kg por semana' } },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            title: { display: true, text: 'Costo acumulado' },
                            ticks: { callback: function (v) { return U.moneyShort(v); } },
                        },
                    },
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                title: function (items) {
                                    return 'Semana del ' + U.fullDate(U.toDay(weeks[items[0].dataIndex].week));
                                },
                                label: function (c) {
                                    return c.dataset.yAxisID === 'y1'
                                        ? 'Costo acumulado: ' + U.money(c.parsed.y)
                                        : 'Alimento: ' + U.num(c.parsed.y) + ' kg';
                                },
                                afterBody: function (items) {
                                    return ['Costo de la semana: ' + U.money(weeks[items[0].dataIndex].cost)];
                                },
                            },
                        },
                    },
                },
            });
        })();
        @endif
    })();
</script>