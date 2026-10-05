@if ($r->type === 'sale' && $r->payment_status)
    @php $balance = round((float) $r->amount - (float) $r->amount_paid, 2); @endphp

    <div class="mt-1">
        @if ($r->payment_status === 'paid')
            <span class="text-xs px-2 py-0.5 rounded bg-green-100 text-green-700">Pagado</span>
        @else
            <span class="text-xs px-2 py-0.5 rounded {{ $r->payment_status === 'partial' ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700' }}">
                {{ $r->payment_status === 'partial' ? 'Parcial' : 'Pendiente' }} · saldo ${{ number_format($balance, 2) }}
            </span>

            @can('modify-inventory')
                <form action="{{ route('animals.records.payment', [$animal, $r]) }}" method="POST"
                        class="mt-1 flex items-center gap-2">
                    @csrf
                    <input type="number" step="0.01" min="0.01" max="{{ $balance }}" name="payment"
                            placeholder="Abono" required
                            class="w-28 rounded border border-gray-300 px-2 py-1 text-sm">
                    <button class="text-blue-600 hover:underline text-sm">Abonar</button>
                </form>
            @endcan
        @endif
    </div>
@endif