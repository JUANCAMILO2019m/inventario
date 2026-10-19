@extends('layouts.app')

@section('title', 'Revisar conteo')

@section('content')
    @php
        $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.');
        $money = fn ($v) => ((float) $v < 0 ? '−' : '') . '$' . number_format(abs((float) $v), 2);
        $net = $shortValue + $surplusValue;
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
        <div>
            <h1 class="text-2xl font-bold">Revisar: {{ $count->name }}</h1>
            <p class="text-gray-500">
                Se compara lo contado con el stock <strong>actual</strong> de cada producto. Marca las diferencias que quieras ajustar.
            </p>
        </div>
        <a href="{{ route('counts.show', $count) }}" class="text-gray-600 hover:underline whitespace-nowrap">← Volver a la hoja de conteo</a>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Con diferencia</p>
            <p class="text-2xl font-bold">{{ $rows->count() }}</p>
        </div>
        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Faltantes</p>
            <p class="text-2xl font-bold {{ $shortValue < 0 ? 'text-red-600' : '' }}">{{ $money($shortValue) }}</p>
        </div>
        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Sobrantes</p>
            <p class="text-2xl font-bold {{ $surplusValue > 0 ? 'text-green-700' : '' }}">{{ $money($surplusValue) }}</p>
        </div>
        <div class="bg-white shadow rounded p-4">
            <p class="text-sm text-gray-500">Neto</p>
            <p class="text-2xl font-bold {{ $net < 0 ? 'text-red-600' : 'text-green-700' }}">{{ $money($net) }}</p>
        </div>
    </div>

    @if ($missingCost > 0)
        <div class="mb-3 rounded bg-amber-50 border border-amber-200 text-amber-800 text-sm px-4 py-2">
            {{ $missingCost }} producto(s) con diferencia no tienen precio: no suman valor y los totales están subestimados.
        </div>
    @endif

    @if ($errors->any() || session('error'))
        <div class="mb-3 rounded bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2">
            {{ session('error') ?? $errors->first() }}
        </div>
    @endif

    @if ($rows->isEmpty())
        <div class="bg-white shadow rounded p-8 text-center text-gray-500 mb-4">
            No hay diferencias entre lo contado y el sistema. Puedes volver a la hoja y seguir contando, o cancelar el conteo.
        </div>
    @else
        <form action="{{ route('counts.apply', $count) }}" method="POST"
              onsubmit="return confirm('Se registrará un ajuste por cada diferencia marcada con el motivo Conteo físico. ¿Aplicar?')">
            @csrf

            <div class="bg-white shadow rounded overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-3 py-3">
                                <input type="checkbox" id="toggle-all" title="Marcar todas">
                            </th>
                            <th class="px-4 py-3">Producto</th>
                            <th class="px-4 py-3">Sistema ahora</th>
                            <th class="px-4 py-3">Contado</th>
                            <th class="px-4 py-3">Diferencia</th>
                            <th class="px-4 py-3">Valor</th>
                            <th class="px-4 py-3">Aviso</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($rows as $r)
                            @php $it = $r['item']; @endphp
                            <tr class="{{ $r['changed'] ? 'bg-amber-50' : '' }}">
                                <td class="px-3 py-2">
                                    <input type="checkbox" class="pick" name="include[]" value="{{ $it->id }}" @checked(!$r['changed'])>
                                </td>
                                <td class="px-4 py-2">
                                    <span class="font-medium">{{ $it->product_name }}</span>
                                    <span class="block text-xs text-gray-500">{{ $it->category_name ?? 'Sin categoría' }}</span>
                                </td>
                                <td class="px-4 py-2">{{ $fmt($r['current']) }} {{ $it->unit }}</td>
                                <td class="px-4 py-2">{{ $fmt($it->counted_qty) }} {{ $it->unit }}</td>
                                <td class="px-4 py-2 font-semibold {{ $r['diff'] < 0 ? 'text-red-700' : 'text-green-700' }}">
                                    {{ $r['diff'] > 0 ? '+' : '' }}{{ $fmt($r['diff']) }}
                                </td>
                                <td class="px-4 py-2">{{ $r['value'] !== null ? $money($r['value']) : '—' }}</td>
                                <td class="px-4 py-2 text-xs text-amber-700">
                                    @if ($r['changed'])
                                        El stock cambió desde que empezó el conteo (de {{ $fmt($it->system_qty) }} a {{ $fmt($r['current']) }}).
                                        Verifica que lo contado ya lo refleje antes de ajustar.
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-5 py-2 rounded">
                    Aplicar ajustes seleccionados
                </button>
                <span class="text-sm text-gray-500">
                    Las diferencias sin marcar no se ajustan. Al aplicar, el conteo se cierra.
                </span>
            </div>
        </form>

        <script>
            document.getElementById('toggle-all').addEventListener('change', function () {
                document.querySelectorAll('.pick').forEach(function (box) { box.checked = this.checked; }, this);
            });
        </script>
    @endif

    @if ($same->isNotEmpty())
        <details class="bg-white shadow rounded mt-4">
            <summary class="cursor-pointer px-4 py-3 font-semibold">Sin diferencia ({{ $same->count() }})</summary>
            <ul class="px-4 pb-3 text-sm text-gray-600 columns-1 md:columns-2">
                @foreach ($same as $it)
                    <li>{{ $it->product_name }}: {{ $fmt($it->counted_qty) }} {{ $it->unit }}</li>
                @endforeach
            </ul>
        </details>
    @endif

    @if ($uncounted->isNotEmpty())
        <details class="bg-white shadow rounded mt-4">
            <summary class="cursor-pointer px-4 py-3 font-semibold">Sin contar ({{ $uncounted->count() }}) — no se modifican</summary>
            <ul class="px-4 pb-3 text-sm text-gray-600 columns-1 md:columns-2">
                @foreach ($uncounted as $it)
                    <li>{{ $it->product_name }}</li>
                @endforeach
            </ul>
        </details>
    @endif

    @if ($missing->isNotEmpty())
        <div class="mt-4 rounded bg-gray-50 border border-gray-200 text-gray-600 text-sm px-4 py-2">
            {{ $missing->count() }} producto(s) contados ya no existen y no se pueden ajustar.
        </div>
    @endif
@endsection