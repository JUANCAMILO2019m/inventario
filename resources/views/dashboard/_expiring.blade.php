<div class="bg-white shadow rounded mt-6">
    <div class="px-5 py-3 border-b flex items-center justify-between">
        <h2 class="font-semibold">Productos por vencer (30 días)</h2>
        <a href="{{ route('expirations.index') }}" class="text-sm text-blue-600 hover:underline">
            Ver todos ({{ $expiringTotal }})
        </a>
    </div>
    <div class="divide-y">
        @forelse ($expiring as $r)
            @php $expired = $r['status'] === 'expired'; @endphp
            <div class="px-5 py-3 flex items-center justify-between gap-3">
                <div>
                    <a href="{{ route('products.movements.index', $r['product']) }}" class="font-medium hover:underline">
                        {{ $r['product']->name }}
                    </a>
                    <p class="text-sm text-gray-500">
                        {{ rtrim(rtrim(number_format($r['quantity'], 3, '.', ''), '0'), '.') }} {{ $r['product']->unit }}
                        @if ($r['batch']->lot_code) · {{ $r['batch']->lot_code }} @endif
                    </p>
                </div>
                <span class="text-sm font-semibold whitespace-nowrap {{ $expired ? 'text-red-600' : 'text-amber-600' }}">
                    @if ($expired)
                        Vencido · {{ $r['batch']->expires_on->format('d/m/Y') }}
                    @elseif ($r['days'] === 0)
                        Vence hoy
                    @else
                        Vence en {{ $r['days'] }} {{ $r['days'] === 1 ? 'día' : 'días' }}
                    @endif
                </span>
            </div>
        @empty
            <p class="px-5 py-6 text-center text-gray-500">No hay productos por vencer en los próximos 30 días.</p>
        @endforelse
    </div>
</div>