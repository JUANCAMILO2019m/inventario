@extends('layouts.app')

@section('title', 'Registrar compra')

@section('content')
    @php
        $input = 'w-full rounded border border-gray-300 px-3 py-2';
        $productData = $products->mapWithKeys(fn ($p) => [$p->id => [
            'unit'         => $p->unit,
            'package_name' => $p->package_name,
            'package_size' => $p->package_size !== null ? (float) $p->package_size : null,
            'price'        => $p->price !== null ? (float) $p->price : null,
            'quantity'     => (float) $p->quantity,
        ]]);
    @endphp

    <h1 class="text-2xl font-bold mb-4">Registrar compra</h1>

    @if ($errors->any())
        <div class="mb-4 rounded bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-2">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('purchases.store') }}" method="POST" id="purchase-form" class="space-y-6">
        @csrf

        <div class="bg-white shadow rounded p-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="md:col-span-2">
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-sm font-medium">Proveedor</label>
                        <button type="button" id="sup-toggle" class="text-sm text-blue-600 hover:underline">+ Nuevo</button>
                    </div>
                    <select name="supplier_id" id="supplier-id" class="{{ $input }}">
                        <option value="">— Sin proveedor —</option>
                        @foreach ($suppliers as $s)
                            <option value="{{ $s->id }}" @selected((string) old('supplier_id', $prefillSupplier ?? '') === (string) $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>

                    <div id="sup-box" class="hidden mt-2 flex flex-wrap gap-2">
                        <input type="text" id="sup-name" placeholder="Nombre del proveedor" maxlength="255"
                                class="flex-1 min-w-[160px] rounded border border-gray-300 px-3 py-2 text-sm">
                        <input type="text" id="sup-phone" placeholder="Teléfono (opcional)" maxlength="50"
                                class="w-44 rounded border border-gray-300 px-3 py-2 text-sm">
                        <button type="button" id="sup-save" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-sm">Guardar</button>
                    </div>
                    <p id="sup-error" class="text-red-600 text-sm mt-1 hidden"></p>
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">Fecha de compra *</label>
                    <input type="date" name="purchased_at" value="{{ old('purchased_at', date('Y-m-d')) }}"
                            max="{{ date('Y-m-d') }}" required class="{{ $input }}">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">N.° de factura</label>
                    <input type="text" name="invoice_number" value="{{ old('invoice_number') }}" maxlength="100" class="{{ $input }}">
                </div>

                <div class="md:col-span-4">
                    <label class="block text-sm font-medium mb-1">Notas</label>
                    <input type="text" name="notes" value="{{ old('notes') }}" maxlength="1000" class="{{ $input }}">
                </div>
            </div>
        </div>

        <div class="bg-white shadow rounded p-4">
            <h2 class="font-semibold mb-1">Productos comprados</h2>
            <p class="text-xs text-gray-500 mb-3">
                Puedes escribir el precio unitario o el total de la línea: el otro se calcula solo.
                El costo del producto se actualiza con el promedio ponderado de lo que ya tenías y lo que compras.
            </p>

            <div id="items" class="space-y-3"></div>

            <button type="button" id="add-item" class="mt-3 text-blue-600 hover:underline text-sm">+ Agregar producto</button>
        </div>

        <div class="flex items-center justify-between">
            <p class="text-lg">Total de la compra: <span id="grand-total" class="font-bold">$0.00</span></p>
            <div class="flex gap-3">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded">Registrar compra</button>
                <a href="{{ route('purchases.index') }}" class="px-5 py-2 rounded border border-gray-300 hover:bg-gray-50">Cancelar</a>
            </div>
        </div>
    </form>

    <script>
        (function () {
            const products = @json($products->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values());
            const meta = @json($productData);
            const initial = @json(old('items', $prefill ?? []));

            const itemsBox = document.getElementById('items');
            const num = v => { const n = parseFloat(v); return isNaN(n) ? 0 : n; };
            const money = n => '$' + (Number(n) || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const qtyFmt = n => (Number(n) || 0).toLocaleString('en-US', { maximumFractionDigits: 3 });
            const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
            const field = (row, name) => row.querySelector('[data-field="' + name + '"]');
            const box = 'w-full rounded border border-gray-300 px-3 py-2';

            function refreshMode(row, preferred) {
                const m = meta[field(row, 'product_id').value];
                const modeSel = field(row, 'mode');
                const hasPkg = !!(m && m.package_size && m.package_name);
                const unit = m ? m.unit : '';

                modeSel.innerHTML = '';
                if (hasPkg) {
                    modeSel.add(new Option('Por empaque (' + m.package_name + ' de ' + qtyFmt(m.package_size) + ' ' + unit + ')', 'package'));
                }
                modeSel.add(new Option('Por unidad (' + (unit || '—') + ')', 'unit'));

                const available = hasPkg ? ['package', 'unit'] : ['unit'];
                modeSel.value = available.includes(preferred) ? preferred : available[0];

                const isPkg = modeSel.value === 'package';
                row.querySelector('.qty-label').textContent = isPkg
                    ? 'Cantidad de ' + m.package_name + ' *'
                    : 'Cantidad' + (unit ? ' (' + unit + ')' : '') + ' *';
                row.querySelector('.price-label').textContent = isPkg
                    ? 'Precio por ' + m.package_name
                    : 'Precio por ' + (unit || 'unidad');
            }

            function sync(row, source) {
                if (source) row.dataset.driver = source;

                const qty = num(field(row, 'qty').value);
                const price = field(row, 'price');
                const total = field(row, 'total');

                if (row.dataset.driver === 'total') {
                    price.value = (qty > 0 && total.value !== '') ? (num(total.value) / qty).toFixed(2) : '';
                } else if (price.value !== '') {
                    total.value = (qty * num(price.value)).toFixed(2);
                }
            }

            function refresh() {
                const rows = [...itemsBox.querySelectorAll('.item-row')];
                let grand = 0;

                rows.forEach((row, idx) => {
                    row.querySelectorAll('[data-field]').forEach(el => {
                        el.name = 'items[' + idx + '][' + el.dataset.field + ']';
                    });

                    const m = meta[field(row, 'product_id').value];
                    const isPkg = field(row, 'mode').value === 'package';
                    const qty = num(field(row, 'qty').value);
                    const total = num(field(row, 'total').value);
                    const hint = row.querySelector('.hint');

                    grand += total;

                    if (m && qty > 0) {
                        const base = isPkg ? qty * m.package_size : qty;
                        const unitCost = base > 0 ? total / base : 0;
                        let text = '= ' + qtyFmt(base) + ' ' + m.unit + ' · costo por ' + m.unit + ': ' + money(unitCost);

                        if (m.price !== null && m.quantity > 0 && base > 0) {
                            const avg = (m.quantity * m.price + base * unitCost) / (m.quantity + base);
                            text += ' · promedio actual ' + money(m.price) + ' → nuevo ≈ ' + money(avg);
                        }

                        hint.textContent = text;
                    } else {
                        hint.textContent = '';
                    }

                    row.querySelector('.remove').classList.toggle('invisible', rows.length === 1);
                });

                document.getElementById('grand-total').textContent = money(grand);
            }

            function addRow(data) {
                data = data || {};

                const row = document.createElement('div');
                row.className = 'item-row rounded border border-gray-200 p-3';
                row.dataset.driver = (data.total && !data.price) ? 'total' : 'price';

                row.innerHTML = `
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                        <div class="md:col-span-4">
                            <label class="block text-xs font-medium mb-1">Producto *</label>
                            <select data-field="product_id" required class="${box}"></select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium mb-1">Comprado por</label>
                            <select data-field="mode" class="${box}"></select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium mb-1 qty-label">Cantidad *</label>
                            <input type="number" data-field="qty" step="0.001" min="0.001" required class="${box}">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium mb-1 price-label">Precio unitario</label>
                            <input type="number" data-field="price" step="0.01" min="0" class="${box}">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium mb-1">Total de la línea *</label>
                            <input type="number" data-field="total" step="0.01" min="0" required class="${box}">
                        </div>
                        <div class="md:col-span-3">
                            <label class="block text-xs font-medium mb-1">Vence (opcional)</label>
                            <input type="date" data-field="expires_on" class="${box}">
                        </div>
                        <div class="md:col-span-3">
                            <label class="block text-xs font-medium mb-1">Lote / referencia (opcional)</label>
                            <input type="text" data-field="lot_code" maxlength="100" class="${box}">
                        </div>
                        <div class="md:col-span-5 text-sm text-gray-600 hint self-end pb-2"></div>
                        <div class="md:col-span-1 self-end pb-2 text-right">
                            <button type="button" class="remove text-red-600 hover:underline text-sm">Quitar</button>
                        </div>
                    </div>
                `;

                const select = field(row, 'product_id');
                select.innerHTML = '<option value="">— Elige un producto —</option>'
                    + products.map(p => '<option value="' + p.id + '">' + esc(p.name) + '</option>').join('');

                if (data.product_id) select.value = data.product_id;

                ['qty', 'price', 'total', 'expires_on', 'lot_code'].forEach(name => {
                    if (data[name] !== undefined && data[name] !== null) field(row, name).value = data[name];
                });

                select.addEventListener('change', () => { refreshMode(row, null); sync(row); refresh(); });
                field(row, 'mode').addEventListener('change', () => { refreshMode(row, field(row, 'mode').value); sync(row); refresh(); });
                field(row, 'qty').addEventListener('input', () => { sync(row); refresh(); });
                field(row, 'price').addEventListener('input', () => { sync(row, 'price'); refresh(); });
                field(row, 'total').addEventListener('input', () => { sync(row, 'total'); refresh(); });
                row.querySelector('.remove').addEventListener('click', () => { row.remove(); refresh(); });

                itemsBox.appendChild(row);
                refreshMode(row, data.mode || null);
                sync(row);
            }

            document.getElementById('add-item').addEventListener('click', () => { addRow({}); refresh(); });

            (Array.isArray(initial) && initial.length ? initial : [{}]).forEach(addRow);
            refresh();

            // Proveedor nuevo sin salir del formulario
            const supBox = document.getElementById('sup-box');
            const supError = document.getElementById('sup-error');

            document.getElementById('sup-toggle').addEventListener('click', () => {
                supBox.classList.toggle('hidden');
                supError.classList.add('hidden');
                if (!supBox.classList.contains('hidden')) document.getElementById('sup-name').focus();
            });

            document.getElementById('sup-save').addEventListener('click', async () => {
                const name = document.getElementById('sup-name').value.trim();
                supError.classList.add('hidden');

                if (!name) {
                    supError.textContent = 'Escribe el nombre del proveedor.';
                    supError.classList.remove('hidden');
                    return;
                }

                try {
                    const response = await fetch('{{ route('suppliers.store') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ name: name, phone: document.getElementById('sup-phone').value.trim() || null }),
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        supError.textContent = (data.errors && data.errors.name && data.errors.name[0]) || 'No se pudo crear el proveedor.';
                        supError.classList.remove('hidden');
                        return;
                    }

                    const select = document.getElementById('supplier-id');
                    const option = new Option(data.name, data.id, true, true);
                    select.add(option);
                    select.value = data.id;

                    document.getElementById('sup-name').value = '';
                    document.getElementById('sup-phone').value = '';
                    supBox.classList.add('hidden');
                } catch (e) {
                    supError.textContent = 'Error de conexión. Intenta de nuevo.';
                    supError.classList.remove('hidden');
                }
            });
        })();
    </script>
@endsection