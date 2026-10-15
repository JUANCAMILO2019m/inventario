@php $money = fn ($v) => '$' . number_format((float) $v, 2); @endphp

<div class="bg-white shadow rounded mb-6">
    <div class="px-4 py-3 border-b flex flex-wrap items-center justify-between gap-2">
        <div>
            <h2 class="font-semibold">Otros gastos</h2>
            <p class="text-sm text-gray-500">
                Mano de obra, veterinario, transporte y demás gastos asignados a este {{ $animal->isLot() ? 'lote' : 'animal' }}.
            </p>
        </div>
        <div class="flex items-center gap-3 text-sm">
            <span class="font-semibold">Total: {{ $money($stats['other_costs']) }}</span>
            @can('modify-inventory')
                <a href="{{ route('expenses.create', ['animal_id' => $animal->id, 'back' => 'animal']) }}"
                   class="text-blue-600 hover:underline">+ Registrar gasto</a>
            @endcan
            @if ($expensesCount > 5)
                <a href="{{ route('expenses.index', ['animal' => $animal->id]) }}"
                   class="text-blue-600 hover:underline">Ver los {{ $expensesCount }}</a>
            @endif
        </div>
    </div>

    @if ($expenses->isEmpty())
        <p class="px-4 py-6 text-center text-sm text-gray-500">Aún no hay gastos asignados.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                    <tr>
                        <th class="px-4 py-2">Fecha</th>
                        <th class="px-4 py-2">Categoría</th>
                        <th class="px-4 py-2">Descripción</th>
                        <th class="px-4 py-2">Proveedor</th>
                        <th class="px-4 py-2">Valor</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach ($expenses as $e)
                        <tr>
                            <td class="px-4 py-2 whitespace-nowrap">{{ $e->spent_at->format('d/m/Y') }}</td>
                            <td class="px-4 py-2">{{ \App\Models\Expense::categoryLabel($e->category) }}</td>
                            <td class="px-4 py-2">{{ $e->description ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $e->supplier?->name ?? '—' }}</td>
                            <td class="px-4 py-2 font-medium">{{ $money($e->amount) }}</td>
                            <td class="px-4 py-2 text-right">
                                @can('modify-inventory')
                                    <a href="{{ route('expenses.edit', $e) }}" class="text-blue-600 hover:underline">Editar</a>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>