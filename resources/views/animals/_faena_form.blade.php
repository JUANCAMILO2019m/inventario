@can('modify-inventory')
    @if ($animal->isLot() && $animal->quantity > 0)
        @php
            $oldLines = old('faena.lines');
            $initialLines = (is_array($oldLines) && count($oldLines)) ? array_values($oldLines) : [[]];
            $faenaErrors = $errors->getBag('faena');
            $input = 'w-full rounded border border-gray-300 px-3 py-2';
        @endphp

        <details class="bg-white shadow rounded mb-6" @if ($faenaErrors->any()) open @endif>
            <summary class="cursor-pointer px-4 py-3 font-semibold">
                Venta por faena a varios compradores
                <span class="text-sm font-normal text-gray-500">— registra a quién le vendiste cada parte</span>
            </summary>

            <form action="{{ route('animals.faenas.store', $animal) }}" method="POST" id="faena-form" class="p-4 border-t">
                @csrf

                @if ($faenaErrors->any())
                    <div class="mb-3 rounded bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2">
                        <ul class="list-disc list-inside">
                            @foreach ($faenaErrors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <p class="text-sm text-gray-500 mb-3">
                    El lote tiene {{ $animal->quantity }} cabezas disponibles. Cada comprador queda registrado como una venta,
                    y las cabezas que no vendas (por ejemplo, las que te quedas) van en "consumo propio".
                </p>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Fecha de la faena *</label>
                        <input type="date" name="faena[recorded_at]"
                               value="{{ old('faena.recorded_at', date('Y-m-d')) }}" required class="{{ $input }}">
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Cómo se cobra *</label>
                        <select name="faena[price_mode]" id="faena-mode" class="{{ $input }}">
                            <option value="per_head" @selected(old('faena.price_mode', 'per_head') === 'per_head')>Por cabeza (ej: por gallina)</option>
                            <option value="per_kg" @selected(old('faena.price_mode') === 'per_kg')>Por kilo</option>
                            <option value="total" @selected(old('faena.price_mode') === 'total')>Valor total por comprador</option>
                        </select>
                    </div>

                    <div class="faena-weighttype hidden">
                        <label class="block text-sm font-medium mb-1">Tipo de peso</label>
                        <select name="faena[weight_type]" class="{{ $input }}">
                            <option value="scale" @selected(old('faena.weight_type', 'scale') === 'scale')>Pesado en báscula</option>
                            <option value="estimated" @selected(old('faena.weight_type') === 'estimated')>Estimado</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium mb-1">Precio base (opcional)</label>
                        <input type="number" id="faena-base" step="0.01" min="0"
                               placeholder="Se copia a cada comprador" class="{{ $input }}">
                    </div>
                </div>

                <h3 class="font-semibold text-sm mb-2">Compradores</h3>
                <div id="faena-lines" class="space-y-3"></div>

                <button type="button" id="faena-add" class="mt-3 text-blue-600 hover:underline text-sm">
                    + Agregar comprador
                </button>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mt-5">
                    <div>
                        <label class="block text-sm font-medium mb-1">Cabezas de consumo propio</label>
                        <input type="number" id="faena-consumed" name="faena[consumed_heads]" min="0" step="1"
                               value="{{ old('faena.consumed_heads', 0) }}" class="{{ $input }}">
                    </div>
                    <div class="md:col-span-3">
                        <label class="block text-sm font-medium mb-1">Notas</label>
                        <input type="text" name="faena[notes]" value="{{ old('faena.notes') }}" maxlength="500" class="{{ $input }}">
                    </div>
                </div>

                <p id="faena-summary" class="mt-3 text-sm"></p>

                <button type="submit" class="mt-4 bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded">
                    Registrar faena
                </button>
            </form>
        </details>

        <script>
            (function () {
                const available = @json((int) $animal->quantity);
                const initial = @json($initialLines);

                const form = document.getElementById('faena-form');
                const linesBox = document.getElementById('faena-lines');
                const modeSelect = document.getElementById('faena-mode');
                const baseInput = document.getElementById('faena-base');
                const consumedInput = document.getElementById('faena-consumed');
                const summary = document.getElementById('faena-summary');

                const priceLabels = { per_head: 'Precio por cabeza', per_kg: 'Precio por kilo', total: 'Valor' };
                const num = v => { const n = parseFloat(v); return isNaN(n) ? 0 : n; };
                const money = n => '$' + (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const field = (row, name) => row.querySelector('[data-field="' + name + '"]');

                function addRow(data) {
                    data = data || {};

                    const row = document.createElement('div');
                    row.className = 'faena-row rounded border border-gray-200 p-3 flex flex-wrap items-end gap-3';
                    row.innerHTML = `
                        <div class="flex-1 min-w-[160px]">
                            <label class="block text-xs font-medium mb-1">Comprador *</label>
                            <input type="text" data-field="buyer" maxlength="255" required class="w-full rounded border border-gray-300 px-3 py-2">
                        </div>
                        <div class="w-24">
                            <label class="block text-xs font-medium mb-1">Cabezas *</label>
                            <input type="number" data-field="heads" min="1" step="1" required class="w-full rounded border border-gray-300 px-3 py-2">
                        </div>
                        <div class="w-28 col-weight">
                            <label class="block text-xs font-medium mb-1">Peso (kg) *</label>
                            <input type="number" data-field="weight" min="0" step="0.01" class="w-full rounded border border-gray-300 px-3 py-2">
                        </div>
                        <div class="w-36">
                            <label class="block text-xs font-medium mb-1 price-label">Precio</label>
                            <input type="number" data-field="price" min="0" step="0.01" required class="w-full rounded border border-gray-300 px-3 py-2">
                        </div>
                        <div class="w-36">
                            <p class="text-xs font-medium mb-1">Valor</p>
                            <p class="line-value py-2 font-semibold">$0.00</p>
                        </div>
                        <div class="w-40">
                            <label class="block text-xs font-medium mb-1">Pago</label>
                            <select data-field="payment_status" class="w-full rounded border border-gray-300 px-3 py-2">
                                <option value="paid">Pagado completo</option>
                                <option value="pending">Pendiente</option>
                                <option value="partial">Parcial (abono)</option>
                            </select>
                        </div>
                        <div class="w-32 col-paid">
                            <label class="block text-xs font-medium mb-1">Abono</label>
                            <input type="number" data-field="amount_paid" min="0" step="0.01" class="w-full rounded border border-gray-300 px-3 py-2">
                        </div>
                        <button type="button" class="remove-line text-red-600 hover:underline text-sm pb-2">Quitar</button>
                    `;

                    const set = (name, value) => {
                        if (value !== undefined && value !== null) field(row, name).value = value;
                    };

                    set('buyer', data.buyer);
                    set('heads', data.heads);
                    set('weight', data.weight);
                    set('price', data.price);
                    set('payment_status', data.payment_status || 'paid');
                    set('amount_paid', data.amount_paid);

                    const price = field(row, 'price');
                    price.dataset.auto = data.price ? '0' : '1';

                    if (!price.value && baseInput.value) {
                        price.value = baseInput.value;
                    }

                    price.addEventListener('input', () => { price.dataset.auto = '0'; });
                    row.addEventListener('input', refresh);
                    row.addEventListener('change', refresh);
                    row.querySelector('.remove-line').addEventListener('click', () => {
                        row.remove();
                        refresh();
                    });

                    linesBox.appendChild(row);
                }

                function refresh() {
                    const mode = modeSelect.value;
                    const rows = [...linesBox.querySelectorAll('.faena-row')];
                    let sold = 0;
                    let total = 0;

                    rows.forEach((row, idx) => {
                        row.querySelectorAll('[data-field]').forEach(el => {
                            el.name = 'faena[lines][' + idx + '][' + el.dataset.field + ']';
                        });

                        const weight = field(row, 'weight');
                        const paid = field(row, 'amount_paid');
                        const status = field(row, 'payment_status').value;

                        row.querySelector('.col-weight').classList.toggle('hidden', mode !== 'per_kg');
                        weight.disabled = mode !== 'per_kg';
                        weight.required = mode === 'per_kg';

                        row.querySelector('.col-paid').classList.toggle('hidden', status !== 'partial');
                        paid.disabled = status !== 'partial';
                        paid.required = status === 'partial';

                        row.querySelector('.price-label').textContent = priceLabels[mode];

                        const heads = num(field(row, 'heads').value);
                        const price = num(field(row, 'price').value);
                        const value = mode === 'per_head' ? heads * price
                            : mode === 'per_kg' ? num(weight.value) * price
                            : price;

                        row.querySelector('.line-value').textContent = money(value);
                        row.querySelector('.remove-line').classList.toggle('invisible', rows.length === 1);

                        sold += heads;
                        total += value;
                    });

                    form.querySelectorAll('.faena-weighttype').forEach(box => {
                        box.classList.toggle('hidden', mode !== 'per_kg');
                        box.querySelectorAll('select').forEach(s => { s.disabled = mode !== 'per_kg'; });
                    });

                    const consumed = num(consumedInput.value);
                    const used = sold + consumed;
                    const left = available - used;

                    summary.className = 'mt-3 text-sm ' + (left < 0 ? 'text-red-600 font-semibold' : 'text-gray-700');
                    summary.textContent = 'Faenadas: ' + used + ' (' + sold + ' vendidas + ' + consumed + ' de consumo propio) de '
                        + available + ' disponibles'
                        + (left < 0 ? ' — te pasas por ' + (-left) + ' cabezas' : ' · quedarían ' + left + ' en el lote')
                        + ' · Total de la faena: ' + money(total);
                }

                baseInput.addEventListener('input', () => {
                    linesBox.querySelectorAll('[data-field="price"]').forEach(el => {
                        if (el.dataset.auto !== '0') {
                            el.value = baseInput.value;
                            el.dataset.auto = '1';
                        }
                    });
                    refresh();
                });

                modeSelect.addEventListener('change', refresh);
                consumedInput.addEventListener('input', refresh);

                document.getElementById('faena-add').addEventListener('click', () => {
                    if (linesBox.children.length >= 50) return;
                    addRow({});
                    refresh();
                });

                (initial.length ? initial : [{}]).forEach(addRow);
                refresh();
            })();
        </script>
    @endif
@endcan