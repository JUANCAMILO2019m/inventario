@php
    $fmtQty = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.');
    $restockBadge = [
        'out'      => ['Agotado', 'text-red-600'],
        'critical' => ['Comprar ya', 'text-red-600'],
        'soon'     => ['Comprar pronto', 'text-amber-600'],
    ];
@endphp

<div class="bg-white shadow rounded mt-6">
    <div class="px-5 py-3 border-b flex items-center justify-between">
        <h2 class="font-semibold">Productos por agotarse</h2>
        <a href="{{ route('reorder.index') }}" class="text-sm text-blue-600 hover:underline">
            Ver todos ({{ $restockTotal }})
        </a>
    </div>
    <div class="divide-y">
        @forelse ($restock as $r)
            @php $p = $r['product']; $sg = $r['suggest']; @endphp
            <div class="px-5 py-3 flex items-center justify-between gap-3">
                <div>
                    <a href="{{ route('products.movements.index', $p) }}" class="font-medium hover:underline">{{ $p->name }}</a>
                    <p class="text-sm text-gray-500">
                        @if ($r['status'] === 'out')
                            Sin stock
                        @else
                            Alcance: {{ (int) floor($r['days_cover']) }} {{ (int) floor($r['days_cover']) === 1 ? 'día' : 'días' }}
                        @endif
                        @if ($sg)
                            · comprar
                            {{ $sg['mode'] === 'package'
                                ? $sg['packages'] . ' × ' . $p->package_name
                                : $fmtQty($sg['qty']) . ' ' . $p->unit }}
                        @endif
                    </p>
                </div>
                <span class="text-sm font-semibold whitespace-nowrap {{ $restockBadge[$r['status']][1] }}">
                    {{ $restockBadge[$r['status']][0] }}
                </span>
            </div>
        @empty
            <p class="px-5 py-6 text-center text-gray-500">
                Ningún producto con consumo está por agotarse.
            </p>
        @endforelse
    </div>
</div>