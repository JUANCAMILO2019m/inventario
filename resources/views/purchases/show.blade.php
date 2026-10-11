@extends('layouts.app')

@section('title', 'Compra #' . $purchase->id)

@section('content')
    @php
        $money = fn ($v) => '$' . number_format((float) $v, 2);
        $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.');
    @endphp

    <div class="flex items-start justify-between gap-3 mb-4">
        <div>
            <h1 class="text-2xl font-bold">
                Compra #{{ $purchase->id }}
                @if ($purchase->isVoided())
                    <span class="ml-2 text-sm px-2 py-1 rounded bg-gray-200 text-gray-600 align-middle">Anulada</span>
                @endif
            </h1>
            <p class="text-gray-500">
                {{ $purchase->purchased_at->format('d/m/Y') }}
                · {{ $purchase->supplier?->name ?? 'Sin proveedor' }}
                @if ($purchase->invoice_number) · Factura {{ $purchase->invoice_number }} @endif
                @if ($purchase->user) · Registró {{ $purchase->user->name }} @endif
            </p>
            @if ($purchase->notes)
                <p class="text-sm text-gray-600 mt-1">{{ $purchase->notes }}</p>
            @endif
        </div>
        <div class="flex items-center gap-3 whitespace-nowrap">
            @can('modify-inventory')
                @unless ($purchase->isVoided())
                    <form action="{{ route('purchases.cancel', $purchase) }}" method="POST"
                          onsubmit="return confirm('¿Anular esta compra? Se restará lo comprado del stock y no se puede deshacer.')">
                        @csrf
                        <button class="text-red-600 hover:underline">Anular compra</button>
                    </form>
                @endunless
            @endcan
            <a href="{{ route('purchases.index') }}" class="text-gray-600 hover:underline">← Volver</a>
        </div>
    </div>

    <div class="bg-white shadow rounded overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                <tr>
                    <th class="px-4 py-3">Producto</th>
                    <th class="px-4 py-3">Cantidad</th>
                    <th class="px-4 py-3">Total</th>
                    <th class="px-4 py-3">Costo por unidad</th>
                    <th class="px-4 py-3">Vs. compra anterior</th>
                    <th class="px-4 py-3">Costo promedio</th>
                    <th class="px-4 py-3">Vence</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($purchase->items as $item)
                    @php
                        $prev = $previous[$item->id] ?? null;
                        $var = ($prev && $prev > 0) ? ($item->unit_cost - $prev) / $prev * 100 : null;
                    @endphp
                    <tr>
                        <td class="px-4 py-3 font-medium">
                            {{ $item->product_name }}
                            @if (!$item->product) <span class="block text-xs text-gray-400">Producto eliminado</span> @endif
                        </td>
                        <td class="px-4 py-3">
                            {{ $fmt($item->quantity) }} {{ $item->unit }}
                            @if ($item->packages !== null)
                                <span class="block text-xs text-gray-500">
                                    {{ $fmt($item->packages) }} × {{ $item->package_name }} de {{ $fmt($item->package_size) }} {{ $item->unit }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $money($item->line_total) }}</td>
                        <td class="px-4 py-3">{{ $money($item->unit_cost) }} / {{ $item->unit }}</td>
                        <td class="px-4 py-3">
                            @if ($var === null)
                                <span class="text-gray-400">—</span>
                            @else
                                <span class="{{ $var > 0 ? 'text-red-600' : 'text-green-700' }} font-semibold">
                                    {{ $var > 0 ? '+' : '' }}{{ number_format($var, 1) }}%
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            {{ $item->avg_before !== null ? $money($item->avg_before) : 'sin precio' }}
                            → <span class="font-medium">{{ $money($item->avg_after) }}</span>
                        </td>
                        <td class="px-4 py-3">
                            @if ($item->batch)
                                {{ $item->batch->expires_on->format('d/m/Y') }}
                                @if ($item->batch->lot_code) <span class="block text-xs text-gray-500">{{ $item->batch->lot_code }}</span> @endif
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot class="bg-gray-50 font-semibold">
                <tr>
                    <td class="px-4 py-3" colspan="2">Total</td>
                    <td class="px-4 py-3" colspan="5">{{ $money($purchase->total) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
@endsection