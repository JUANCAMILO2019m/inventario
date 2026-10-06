@if ($animal->isLot() && ($buyers->isNotEmpty() || $faenas->isNotEmpty()))
    @php $money = fn ($value) => '$' . number_format((float) $value, 2); @endphp

    @if ($buyers->isNotEmpty())
        <div class="bg-white shadow rounded mb-6">
            <div class="px-4 py-3 border-b">
                <h2 class="font-semibold">Compradores</h2>
                <p class="text-sm text-gray-500">Todas las ventas del lote, agrupadas por comprador.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Comprador</th>
                            <th class="px-4 py-2">Compras</th>
                            <th class="px-4 py-2">Cabezas</th>
                            <th class="px-4 py-2">Valor</th>
                            <th class="px-4 py-2">Pagado</th>
                            <th class="px-4 py-2">Saldo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($buyers as $b)
                            <tr>
                                <td class="px-4 py-2 font-medium">{{ $b['name'] }}</td>
                                <td class="px-4 py-2">{{ $b['sales'] }}</td>
                                <td class="px-4 py-2">{{ $b['heads'] }}</td>
                                <td class="px-4 py-2">{{ $money($b['amount']) }}</td>
                                <td class="px-4 py-2">{{ $money($b['paid']) }}</td>
                                <td class="px-4 py-2 {{ $b['balance'] > 0 ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                                    {{ $b['balance'] > 0 ? $money($b['balance']) : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 font-semibold">
                        <tr>
                            <td class="px-4 py-2">Total</td>
                            <td class="px-4 py-2">{{ $buyers->sum('sales') }}</td>
                            <td class="px-4 py-2">{{ $buyers->sum('heads') }}</td>
                            <td class="px-4 py-2">{{ $money($buyers->sum('amount')) }}</td>
                            <td class="px-4 py-2">{{ $money($buyers->sum('paid')) }}</td>
                            <td class="px-4 py-2">{{ $money($buyers->sum('balance')) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    @endif

    @if ($faenas->isNotEmpty())
        <div class="bg-white shadow rounded mb-6">
            <div class="px-4 py-3 border-b">
                <h2 class="font-semibold">Faenas registradas</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                        <tr>
                            <th class="px-4 py-2">Fecha</th>
                            <th class="px-4 py-2">Faenadas</th>
                            <th class="px-4 py-2">Vendidas</th>
                            <th class="px-4 py-2">Consumo propio</th>
                            <th class="px-4 py-2">Compradores</th>
                            <th class="px-4 py-2">Valor</th>
                            <th class="px-4 py-2">Cobrado</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach ($faenas as $fa)
                            <tr>
                                <td class="px-4 py-2 whitespace-nowrap">{{ $fa['date']->format('d/m/Y') }}</td>
                                <td class="px-4 py-2 font-medium">{{ $fa['sold'] + $fa['consumed'] }}</td>
                                <td class="px-4 py-2">{{ $fa['sold'] }}</td>
                                <td class="px-4 py-2">{{ $fa['consumed'] }}</td>
                                <td class="px-4 py-2">{{ $fa['buyers'] }}</td>
                                <td class="px-4 py-2">{{ $money($fa['amount']) }}</td>
                                <td class="px-4 py-2">{{ $money($fa['collected']) }}</td>
                                <td class="px-4 py-2 text-right">
                                    @can('modify-inventory')
                                        <form action="{{ route('animals.faenas.destroy', [$animal, $fa['batch_id']]) }}" method="POST"
                                                onsubmit="return confirm('¿Eliminar toda la faena? Se borran sus ventas y las cabezas vuelven al lote.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-red-600 hover:underline">Eliminar faena</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endif