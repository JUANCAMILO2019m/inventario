@extends('layouts.app')

@section('title', 'Predicciones')

@section('content')
    @php
        $money = fn ($v) => ((float) $v < 0 ? '−' : '') . '$' . number_format(abs((float) $v), 0);
        $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.');
        $int = fn ($n) => number_format((float) $n, 0);
        $one = fn ($n) => number_format((float) $n, 1);
        $rel = [
            'high'    => ['Alta', 'bg-green-100 text-green-700'],
            'medium'  => ['Media', 'bg-amber-100 text-amber-700'],
            'low'     => ['Baja', 'bg-red-100 text-red-700'],
            'unknown' => ['Sin evaluar', 'bg-gray-100 text-gray-600'],
        ];
        $methodLabel = ['trend' => 'tendencia', 'avg' => 'promedio reciente'];
        $monthName = fn ($ym) => \Carbon\Carbon::createFromFormat('!Y-m', $ym)->locale('es')->translatedFormat('F Y');
        $input = 'rounded border border-gray-300 px-3 py-2 text-sm';
        $fin = $finance['ready'] ? $finance : null;
        $growthCharts = collect($growth['rows'])->take(12)->pluck('chart')->values()->all();
        $priceCharts = $prices ? collect($prices['rows'])->pluck('chart')->values()->all() : [];
    @endphp

    @include('partials.chart-assets')

    <h1 class="text-2xl font-bold mb-1">Predicciones</h1>
    <p class="text-gray-500 mb-4">
        Estimaciones estadísticas hechas con tus propios registros y con los precios del DANE.
        Son aproximaciones con un margen de error, no certezas: cada una indica qué tan confiable es.
    </p>

    {{-- Resumen --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Resultado proyectado{{ $fin ? ' (' . $fin['horizon'] . ' meses)' : '' }}</p>
            @if ($fin)
                @php $r = $fin['totals']['result']; @endphp
                <p class="text-2xl font-bold {{ $r['value'] >= 0 ? 'text-green-700' : 'text-red-600' }}">{{ $money($r['value']) }}</p>
                <p class="text-xs text-gray-500">Rango probable: {{ $money($r['low']) }} a {{ $money($r['high']) }}</p>
            @else
                <p class="text-2xl font-bold text-gray-400">—</p>
                <p class="text-xs text-gray-500">Faltan datos mensuales</p>
            @endif
        </div>

        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Productos que se agotarían en 30 días</p>
            <p class="text-2xl font-bold {{ $inventory['within30'] ? 'text-red-600' : '' }}">{{ $inventory['within30'] }}</p>
            <p class="text-xs text-gray-500">de {{ $inventory['analyzed'] }} con datos suficientes</p>
        </div>

        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Animales que llegarían a su peso objetivo en 30 días</p>
            <p class="text-2xl font-bold {{ $growth['within30'] ? 'text-green-700' : '' }}">{{ $growth['within30'] }}</p>
            <p class="text-xs text-gray-500">
                {{ $growth['with_goal'] ? 'de ' . $growth['with_goal'] . ' con peso objetivo' : 'Define el peso objetivo en la ficha' }}
            </p>
        </div>

        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Precios con tendencia al alza (4 semanas)</p>
            @if ($prices)
                <p class="text-2xl font-bold {{ $prices['up'] ? 'text-red-600' : '' }}">{{ $prices['up'] }}</p>
                <p class="text-xs text-gray-500">de {{ count($prices['rows']) }} productos con datos suficientes</p>
            @else
                <p class="text-2xl font-bold text-gray-400">—</p>
                <p class="text-xs text-gray-500">Carga los precios del DANE</p>
            @endif
        </div>
    </div>

    <details class="bg-white shadow rounded mb-6">
        <summary class="cursor-pointer px-4 py-3 font-semibold">Cómo se calculan estas predicciones y qué tan confiables son</summary>
        <div class="px-4 pb-4 text-sm text-gray-700 space-y-2">
            <p>
                <strong>Finanzas, consumo de insumos y precios.</strong> Para cada serie se prueban dos modelos
                (una tendencia que se va suavizando y el promedio de los últimos valores) contra datos que ya ocurrieron,
                y se usa el que menos se equivoca. Si la tendencia no mejora al promedio, se usa el promedio.
            </p>
            <p>
                <strong>Crecimiento de animales.</strong> Se ajusta una línea recta a los pesajes de cada animal o lote.
                Supone que el crecimiento es parejo: en la realidad se frena cerca de la madurez, así que las proyecciones largas pueden quedar optimistas.
            </p>
            <p>
                <strong>Rango probable.</strong> Es un intervalo aproximado pensado para cubrir cerca de 8 de cada 10 casos si el comportamiento no cambia.
                Es una aproximación, no una garantía.
            </p>
            <p>
                <strong>Confiabilidad.</strong> En series en el tiempo: <em>Alta</em> si el modelo se equivocó 10% o menos al probarlo con datos pasados,
                <em>Media</em> hasta 25% y <em>Baja</em> si más. En animales: <em>Alta</em> con 5 o más pesajes, buen ajuste y 30 días o más de historia;
                <em>Baja</em> con pocos pesajes o si el último tiene más de 45 días. Estos cortes son un criterio práctico, no un estándar.
            </p>
            <p>
                <strong>Lo que no ven.</strong> Estaciones del año, sequías, brotes de enfermedad, cambios de dieta ni decisiones tuyas como una venta grande.
                Úsalas como una guía para planear, no como una promesa.
            </p>
        </div>
    </details>

    {{-- Finanzas --}}
    <h2 class="text-xl font-bold mb-3">Finanzas: próximos meses</h2>

    @if (!$fin)
        <div class="bg-white shadow rounded p-6 mb-6 text-sm text-gray-600">
            Aún no hay datos suficientes para proyectar. Se necesitan al menos {{ $finance['needed'] }} meses completos con ventas o costos
            y hay {{ $finance['months'] }}. Esta proyección se activa sola cuando tengas más historia.
        </div>
    @else
        <div class="bg-white shadow rounded p-4 mb-6">
            <div class="relative h-72"><canvas id="chart-finance"></canvas></div>

            <div class="flex flex-wrap gap-x-6 gap-y-1 text-xs text-gray-500 mt-3">
                <span>
                    Ventas: modelo {{ $methodLabel[$fin['sales_model']['method']] }}
                    <span class="px-2 py-0.5 rounded {{ $rel[$fin['sales_model']['reliability']][1] }}">{{ $rel[$fin['sales_model']['reliability']][0] }}</span>
                </span>
                <span>
                    Costos: modelo {{ $methodLabel[$fin['cost_model']['method']] }}
                    <span class="px-2 py-0.5 rounded {{ $rel[$fin['cost_model']['reliability']][1] }}">{{ $rel[$fin['cost_model']['reliability']][0] }}</span>
                </span>
                <span>Basado en {{ $fin['n'] }} meses completos.</span>
            </div>

            @if (in_array($fin['sales_model']['reliability'], ['low', 'unknown'], true))
                <p class="mt-3 text-sm rounded bg-amber-50 border border-amber-200 text-amber-800 px-3 py-2">
                    Las ventas dependen de cuándo vendes cada lote, así que son muy irregulares y difíciles de proyectar.
                    Tómalas como una referencia amplia; los costos suelen ser más predecibles.
                </p>
            @endif

            <div class="overflow-x-auto mt-4">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Mes</th>
                            <th class="px-4 py-2">Ventas</th>
                            <th class="px-4 py-2">Costos</th>
                            <th class="px-4 py-2">Resultado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($fin['future'] as $f)
                            <tr>
                                <td class="px-4 py-2 font-medium capitalize">{{ $monthName($f['month']) }}</td>
                                @foreach (['sales', 'cost', 'result'] as $key)
                                    <td class="px-4 py-2">
                                        <span class="font-semibold {{ $key === 'result' ? ($f[$key]['value'] >= 0 ? 'text-green-700' : 'text-red-600') : '' }}">
                                            {{ $money($f[$key]['value']) }}
                                        </span>
                                        <span class="block text-xs text-gray-500">{{ $money($f[$key]['low']) }} a {{ $money($f[$key]['high']) }}</span>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 font-semibold">
                        <tr>
                            <td class="px-4 py-2">Total {{ $fin['horizon'] }} meses</td>
                            @foreach (['sales', 'cost', 'result'] as $key)
                                <td class="px-4 py-2">
                                    {{ $money($fin['totals'][$key]['value']) }}
                                    <span class="block text-xs font-normal text-gray-500">
                                        {{ $money($fin['totals'][$key]['low']) }} a {{ $money($fin['totals'][$key]['high']) }}
                                    </span>
                                </td>
                            @endforeach
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif

    {{-- Animales --}}
    <h2 class="text-xl font-bold mb-3">Animales: peso y fecha estimada de venta</h2>

    @if (empty($growth['rows']))
        <div class="bg-white shadow rounded p-6 mb-6 text-sm text-gray-600">
            Ningún animal activo tiene datos suficientes. Se necesitan al menos 3 registros de peso en 14 días o más
            (el peso inicial con la fecha de ingreso cuenta como uno).
            @if ($growth['skipped'])
                Hay {{ $growth['skipped'] }} animal(es) activo(s) sin datos suficientes.
            @endif
        </div>
    @else
        <div class="bg-white shadow rounded p-4 mb-6">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Animal o lote</th>
                            <th class="px-4 py-2">Peso hoy (est.)</th>
                            <th class="px-4 py-2">Ganancia</th>
                            <th class="px-4 py-2">Peso en {{ $growth['horizon'] }} días</th>
                            <th class="px-4 py-2">Peso objetivo</th>
                            <th class="px-4 py-2">Llegaría</th>
                            <th class="px-4 py-2">Confiabilidad</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($growth['rows'] as $g)
                            @php $a = $g['animal']; $reach = $g['reach']; @endphp
                            <tr class="align-top">
                                <td class="px-4 py-2">
                                    <a href="{{ route('animals.show', $a) }}" class="font-medium text-blue-600 hover:underline">{{ $a->name }}</a>
                                    <span class="block text-xs text-gray-500">
                                        {{ $a->isLot() ? 'Lote' : 'Individual' }} · {{ $g['n'] }} pesos en {{ $g['span'] }} días
                                    </span>
                                </td>
                                <td class="px-4 py-2 font-semibold">{{ $one($g['current']['value']) }} kg</td>
                                <td class="px-4 py-2">
                                    @if ($g['gain_g'] > 0)
                                        {{ $int($g['gain_g']) }} g/día
                                    @else
                                        <span class="text-red-600">Sin crecimiento</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    {{ $one($g['future']['value']) }} kg
                                    <span class="block text-xs text-gray-500">{{ $one($g['future']['low']) }} a {{ $one($g['future']['high']) }}</span>
                                </td>
                                <td class="px-4 py-2">{{ $g['target'] !== null ? $one($g['target']) . ' kg' : '—' }}</td>
                                <td class="px-4 py-2">
                                    @if ($reach === null)
                                        <span class="text-gray-400 text-xs">Sin peso objetivo</span>
                                    @elseif ($reach['status'] === 'done')
                                        <span class="text-green-700 font-semibold">Ya lo alcanzó</span>
                                    @elseif ($reach['status'] === 'never')
                                        <span class="text-red-600">No crece según los datos</span>
                                    @elseif ($reach['status'] === 'far')
                                        Más de 2 años
                                    @else
                                        <span class="font-semibold">{{ $reach['date']->format('d/m/Y') }}</span>
                                        <span class="block text-xs text-gray-500">
                                            @if ($reach['date_early'] && $reach['date_late'])
                                                entre {{ $reach['date_early']->format('d/m') }} y {{ $reach['date_late']->format('d/m/Y') }}
                                            @elseif ($reach['date_early'])
                                                desde {{ $reach['date_early']->format('d/m/Y') }}
                                            @endif
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    <span class="text-xs px-2 py-1 rounded {{ $rel[$g['reliability']][1] }}">{{ $rel[$g['reliability']][0] }}</span>
                                    @if ($g['stale'] > 45)
                                        <span class="block text-xs text-amber-700 mt-1">Último peso hace {{ $g['stale'] }} días</span>
                                    @endif
                                    @if ($g['estimated'] > 0)
                                        <span class="block text-xs text-gray-500 mt-1">{{ $g['estimated'] }} peso(s) estimado(s)</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if (!$growth['with_goal'])
                <p class="mt-3 text-sm rounded bg-blue-50 border border-blue-200 text-blue-800 px-3 py-2">
                    Para ver la fecha estimada de llegar al peso de venta, escribe el <strong>peso objetivo</strong> en la ficha del animal o lote (opción Editar).
                </p>
            @endif

            @if ($growth['skipped'])
                <p class="mt-3 text-xs text-gray-500">
                    {{ $growth['skipped'] }} animal(es) activo(s) no aparecen por falta de pesajes suficientes.
                </p>
            @endif

            <div class="mt-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="font-semibold">Evolución y proyección</h3>
                    <select id="growth-select" class="{{ $input }}">
                        @foreach ($growthCharts as $i => $c)
                            <option value="{{ $i }}">{{ $c['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="relative h-72 mt-2"><canvas id="chart-growth"></canvas></div>
                <p class="text-xs text-gray-500 mt-2">
                    Los puntos rellenos son pesos de báscula y los vacíos, estimados. La línea punteada es la tendencia y la franja, el rango probable.
                    En un lote el peso es el promedio por cabeza.
                </p>
            </div>
        </div>
    @endif

    {{-- Inventario --}}
    <h2 class="text-xl font-bold mb-3">Inventario: consumo y agotamiento</h2>

    @if (empty($inventory['rows']))
        <div class="bg-white shadow rounded p-6 mb-6 text-sm text-gray-600">
            Ningún producto tiene datos suficientes: se necesitan al menos 3 semanas con consumo en el último trimestre.
            @if ($inventory['skipped'])
                Hay {{ $inventory['skipped'] }} producto(s) con consumo insuficiente.
            @endif
        </div>
    @else
        <div class="bg-white shadow rounded mb-6">
            <div class="px-4 py-3 border-b flex flex-wrap items-center justify-between gap-2">
                <p class="text-sm text-gray-600">
                    Gasto estimado en insumos las próximas {{ $inventory['horizon'] }} semanas:
                    <strong>{{ $money($inventory['cost_total']) }}</strong>
                    @if ($inventory['cost_missing'])
                        <span class="text-xs text-amber-700">({{ $inventory['cost_missing'] }} producto(s) sin precio no suman)</span>
                    @endif
                </p>
                <a href="{{ route('reorder.index') }}" class="text-sm text-blue-600 hover:underline">Ver reabastecimiento →</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Producto</th>
                            <th class="px-4 py-2">Disponible</th>
                            <th class="px-4 py-2">Consumo semanal</th>
                            <th class="px-4 py-2">Se agotaría en</th>
                            <th class="px-4 py-2">Gasto {{ $inventory['horizon'] }} sem.</th>
                            <th class="px-4 py-2">Confiabilidad</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach (array_slice($inventory['rows'], 0, 25) as $r)
                            @php $p = $r['product']; @endphp
                            <tr class="align-top">
                                <td class="px-4 py-2">
                                    <a href="{{ route('products.movements.index', $p) }}" class="font-medium hover:underline">{{ $p->name }}</a>
                                    <span class="block text-xs text-gray-500">Entrega: {{ $r['lead'] }} {{ $r['lead'] === 1 ? 'día' : 'días' }}</span>
                                </td>
                                <td class="px-4 py-2">{{ $fmt($r['usable']) }} {{ $r['unit'] }}</td>
                                <td class="px-4 py-2">
                                    {{ $fmt(round($r['now_weekly'], 2)) }} → <span class="font-semibold">{{ $fmt(round($r['next_weekly'], 2)) }}</span> {{ $r['unit'] }}
                                    @if ($r['change'] !== null && abs($r['change']) >= 5)
                                        <span class="block text-xs {{ $r['change'] > 0 ? 'text-red-600' : 'text-green-700' }}">
                                            {{ $r['change'] > 0 ? 'sube' : 'baja' }} {{ number_format(abs($r['change']), 0) }}%
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-2">
                                    @if ($r['status'] === 'out')
                                        <span class="font-semibold text-red-600">Sin stock</span>
                                    @elseif ($r['days'] === null)
                                        <span class="text-gray-400">Sin agotamiento previsible</span>
                                    @else
                                        <span class="font-semibold {{ $r['status'] === 'critical' ? 'text-red-600' : ($r['status'] === 'soon' ? 'text-amber-600' : '') }}">
                                            {{ $r['days'] > 365 ? 'Más de 1 año' : (int) floor($r['days']) . ' ' . ((int) floor($r['days']) === 1 ? 'día' : 'días') }}
                                        </span>
                                        @if ($r['date'] && $r['days'] <= 365)
                                            <span class="block text-xs text-gray-500">
                                                ~{{ $r['date']->format('d/m/Y') }}
                                                @if ($r['days_early'] !== null && $r['days_late'] !== null)
                                                    · entre {{ (int) floor($r['days_early']) }} y {{ (int) floor($r['days_late']) }} días
                                                @elseif ($r['days_early'] !== null)
                                                    · desde {{ (int) floor($r['days_early']) }} días
                                                @endif
                                            </span>
                                        @endif
                                    @endif
                                </td>
                                <td class="px-4 py-2">{{ $r['cost_next'] !== null ? $money($r['cost_next']) : '—' }}</td>
                                <td class="px-4 py-2">
                                    <span class="text-xs px-2 py-1 rounded {{ $rel[$r['reliability']][1] }}">{{ $rel[$r['reliability']][0] }}</span>
                                    <span class="block text-xs text-gray-500 mt-1">{{ $methodLabel[$r['method']] }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="px-4 py-3 text-xs text-gray-500 border-t">
                Se muestran los 25 que primero se agotarían. El consumo cuenta salidas por uso en animales, ventas o entregas a terceros y otras salidas;
                no cuenta pérdidas, vencidos ni ajustes. El stock vencido no se considera disponible.
                @if ($inventory['skipped'])
                    {{ $inventory['skipped'] }} producto(s) con consumo insuficiente no aparecen.
                @endif
            </p>
        </div>
    @endif

    {{-- Mercado --}}
    <h2 class="text-xl font-bold mb-3">Mercado: precios de referencia (DANE)</h2>

    @if (!$prices)
        <div class="bg-white shadow rounded p-6 mb-6 text-sm text-gray-600">
            Aún no hay precios cargados. Se cargan con <code class="bg-gray-100 px-1 rounded">php artisan sipsa:sync</code>.
        </div>
    @elseif (empty($prices['rows']))
        <div class="bg-white shadow rounded p-6 mb-6 text-sm text-gray-600">
            Ningún producto tiene suficiente historia en {{ \App\Models\SipsaPrice::cityLabel($city) }}: se necesitan al menos 20 semanas con precio y datos recientes.
            Prueba otra ciudad o espera a que se acumule más historia.
        </div>
    @else
        <div class="bg-white shadow rounded p-4 mb-6">
            <form method="GET" action="{{ route('forecasts.index') }}" class="flex flex-wrap items-end gap-3 mb-3">
                <div>
                    <label class="block text-sm font-medium mb-1">Ciudad</label>
                    <select name="city" onchange="this.form.submit()" class="{{ $input }}">
                        @foreach ($cities as $c)
                            <option value="{{ $c }}" @selected($c === $city)>{{ \App\Models\SipsaPrice::cityLabel($c) }}</option>
                        @endforeach
                    </select>
                </div>
                <p class="text-sm text-gray-500 pb-2">
                    Tendencia a {{ $prices['horizon'] }} semanas · último dato: {{ $prices['last']?->format('d/m/Y') }}
                </p>
            </form>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Producto</th>
                            <th class="px-4 py-2">Precio hoy</th>
                            <th class="px-4 py-2">En {{ $prices['horizon'] }} semanas</th>
                            <th class="px-4 py-2">Cambio</th>
                            <th class="px-4 py-2">Confiabilidad</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($prices['rows'] as $p)
                            <tr class="align-top">
                                <td class="px-4 py-2 font-medium">{{ $p['name'] }}</td>
                                <td class="px-4 py-2">{{ $money($p['current']) }}</td>
                                <td class="px-4 py-2">
                                    <span class="font-semibold">{{ $money($p['end']) }}</span>
                                    <span class="block text-xs text-gray-500">{{ $money($p['low']) }} a {{ $money($p['high']) }}</span>
                                </td>
                                <td class="px-4 py-2 whitespace-nowrap">
                                    <span class="font-semibold {{ $p['trend'] === 'up' ? 'text-red-600' : ($p['trend'] === 'down' ? 'text-green-700' : 'text-gray-500') }}">
                                        {{ $p['change'] > 0 ? '+' : '' }}{{ $one($p['change']) }}%
                                    </span>
                                    <span class="block text-xs text-gray-500">
                                        {{ $p['trend'] === 'up' ? 'tendencia al alza' : ($p['trend'] === 'down' ? 'tendencia a la baja' : 'estable') }}
                                    </span>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="text-xs px-2 py-1 rounded {{ $rel[$p['reliability']][1] }}">{{ $rel[$p['reliability']][0] }}</span>
                                    <span class="block text-xs text-gray-500 mt-1">{{ $methodLabel[$p['method']] }}</span>
                                </td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                    <a href="{{ route('prices.show', ['code' => $p['code'], 'city' => $city]) }}" class="text-blue-600 hover:underline">Historial →</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h3 class="font-semibold">Precio por kilo y proyección</h3>
                    <select id="price-select" class="{{ $input }}">
                        @foreach ($priceCharts as $i => $c)
                            <option value="{{ $i }}">{{ $c['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="relative h-72 mt-2"><canvas id="chart-price"></canvas></div>
                <p class="text-xs text-gray-500 mt-2">
                    Promedios semanales del precio mayorista. Los precios agrícolas cambian con las estaciones y las cosechas, y con menos de dos años
                    de historia el modelo no puede aprenderlas: la tendencia es una orientación, no un precio garantizado.
                    @if ($prices['skipped'])
                        {{ $prices['skipped'] }} producto(s) sin historia suficiente no aparecen.
                    @endif
                </p>
            </div>
        </div>
    @endif

    <script>
        (function () {
            const U = window.ChartUtil;
            const band = 'rgba(37, 99, 235, 0.12)';
            const bandGreen = 'rgba(22, 163, 74, 0.14)';

            const xAxis = {
                type: 'linear',
                grace: '2%',
                grid: { display: false },
                ticks: { precision: 0, maxTicksLimit: 8, callback: function (v) { return U.dayLabel(v); } },
            };

            const hideLowBand = {
                legend: { position: 'bottom', labels: { filter: function (item) { return item.text !== 'Rango bajo'; } } },
                tooltip: { filter: function (item) { return item.datasetIndex >= 2; } },
            };

            @if ($fin)
            (function () {
                const hist = {{ \Illuminate\Support\Js::from($fin['history']) }};
                const fut = {{ \Illuminate\Support\Js::from($fin['future']) }};
                const labels = hist.map(function (m) { return U.monthLabel(m.month); })
                    .concat(fut.map(function (m) { return U.monthLabel(m.month); }));

                function real(key) {
                    return hist.map(function (m) { return m[key]; }).concat(fut.map(function () { return null; }));
                }

                function proj(key) {
                    return hist.map(function (m, i) { return i === hist.length - 1 ? m[key] : null; })
                        .concat(fut.map(function (f) { return f[key].value; }));
                }

                new Chart(document.getElementById('chart-finance'), {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [
                            { label: 'Ventas (real)', data: real('sales'), borderColor: '#16a34a', backgroundColor: '#16a34a', tension: 0.15, pointRadius: 3 },
                            { label: 'Ventas (proyección)', data: proj('sales'), borderColor: '#16a34a', backgroundColor: '#ffffff', borderDash: [6, 4], tension: 0.15, pointRadius: 4 },
                            { label: 'Costos (real)', data: real('cost'), borderColor: '#dc2626', backgroundColor: '#dc2626', tension: 0.15, pointRadius: 3 },
                            { label: 'Costos (proyección)', data: proj('cost'), borderColor: '#dc2626', backgroundColor: '#ffffff', borderDash: [6, 4], tension: 0.15, pointRadius: 4 },
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
                            tooltip: { callbacks: { label: function (c) { return c.dataset.label + ': ' + U.money(c.parsed.y); } } },
                        },
                    },
                });
            })();
            @endif

            @if (count($growthCharts))
            (function () {
                const items = {{ \Illuminate\Support\Js::from($growthCharts) }};
                const select = document.getElementById('growth-select');
                const canvas = document.getElementById('chart-growth');
                let chart = null;

                function build(index) {
                    const it = items[index];
                    const pt = function (p, key) { return { x: U.toDay(p.date), y: p[key] }; };
                    const line = it.line.map(function (p) { return pt(p, 'value'); });
                    const high = it.line.map(function (p) { return pt(p, 'high'); });
                    const low = it.line.map(function (p) { return pt(p, 'low'); });
                    const hist = it.history.map(function (p) { return { x: U.toDay(p.date), y: p.weight, estimated: p.estimated }; });

                    const datasets = [
                        { label: 'Rango probable', data: high, parsing: false, borderWidth: 0, pointRadius: 0, fill: '+1', backgroundColor: band },
                        { label: 'Rango bajo', data: low, parsing: false, borderWidth: 0, pointRadius: 0, fill: false },
                        { label: 'Tendencia estimada', data: line, parsing: false, borderColor: '#2563eb', borderDash: [6, 4], borderWidth: 2, pointRadius: 0, fill: false },
                        {
                            label: 'Pesajes', data: hist, parsing: false, showLine: false, pointRadius: 5, pointBorderWidth: 2,
                            pointBorderColor: '#2563eb',
                            pointBackgroundColor: function (c) { return (c.raw && c.raw.estimated) ? '#ffffff' : '#2563eb'; },
                        },
                    ];

                    if (it.target) {
                        datasets.push({
                            label: 'Peso objetivo',
                            data: [{ x: line[0].x, y: it.target }, { x: line[line.length - 1].x, y: it.target }],
                            parsing: false, borderColor: '#dc2626', borderDash: [3, 3], borderWidth: 1.5, pointRadius: 0, fill: false,
                        });
                    }

                    if (chart) { chart.destroy(); }

                    chart = new Chart(canvas, {
                        type: 'line',
                        data: { datasets: datasets },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: { x: xAxis, y: { grace: '8%', title: { display: true, text: 'kg por cabeza' } } },
                            plugins: Object.assign({}, hideLowBand, {
                                tooltip: {
                                    filter: hideLowBand.tooltip.filter,
                                    callbacks: {
                                        title: function (items) { return U.fullDate(items[0].raw.x); },
                                        label: function (c) { return c.dataset.label + ': ' + U.num(c.raw.y) + ' kg'; },
                                    },
                                },
                            }),
                        },
                    });
                }

                select.addEventListener('change', function () { build(parseInt(select.value, 10)); });
                build(0);
            })();
            @endif

            @if (count($priceCharts))
            (function () {
                const items = {{ \Illuminate\Support\Js::from($priceCharts) }};
                const select = document.getElementById('price-select');
                const canvas = document.getElementById('chart-price');
                let chart = null;

                function build(index) {
                    const it = items[index];
                    const last = it.history[it.history.length - 1];

                    const fx = [{ x: U.toDay(last.date), v: last.price, lo: last.price, hi: last.price }].concat(
                        it.forecast.map(function (p) { return { x: U.toDay(p.date), v: p.value, lo: p.low, hi: p.high }; })
                    );

                    const hist = it.history.map(function (p) { return { x: U.toDay(p.date), y: p.price }; });

                    if (chart) { chart.destroy(); }

                    chart = new Chart(canvas, {
                        type: 'line',
                        data: {
                            datasets: [
                                { label: 'Rango probable', data: fx.map(function (p) { return { x: p.x, y: p.hi }; }), parsing: false, borderWidth: 0, pointRadius: 0, fill: '+1', backgroundColor: bandGreen },
                                { label: 'Rango bajo', data: fx.map(function (p) { return { x: p.x, y: p.lo }; }), parsing: false, borderWidth: 0, pointRadius: 0, fill: false },
                                { label: 'Proyección', data: fx.map(function (p) { return { x: p.x, y: p.v }; }), parsing: false, borderColor: '#16a34a', borderDash: [6, 4], borderWidth: 2, pointRadius: 3, fill: false },
                                { label: 'Precio real', data: hist, parsing: false, borderColor: '#16a34a', borderWidth: 2, tension: 0.15, pointRadius: 0, fill: false },
                            ],
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                x: xAxis,
                                y: { grace: '8%', ticks: { callback: function (v) { return U.moneyShort(v); } }, title: { display: true, text: 'pesos por kg' } },
                            },
                            plugins: Object.assign({}, hideLowBand, {
                                tooltip: {
                                    filter: hideLowBand.tooltip.filter,
                                    callbacks: {
                                        title: function (items) { return U.fullDate(items[0].raw.x); },
                                        label: function (c) { return c.dataset.label + ': ' + U.money(c.raw.y) + ' por kg'; },
                                    },
                                },
                            }),
                        },
                    });
                }

                select.addEventListener('change', function () { build(parseInt(select.value, 10)); });
                build(0);
            })();
            @endif
        })();
    </script>
@endsection