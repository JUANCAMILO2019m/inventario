@csrf

@php
    $field = 'w-full rounded border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400';
@endphp

<div class="mb-6">
    <label class="block text-sm font-medium mb-1">Foto</label>

    @if (!empty($product) && $product->photo)
        <div class="flex items-center gap-4 mb-3">
            <img src="{{ Storage::url($product->photo) }}" alt="{{ $product->name }}"
                class="w-24 h-24 object-cover rounded border">
            <label class="flex items-center gap-2 text-sm text-red-600">
                <input type="checkbox" name="remove_photo" value="1">
                Quitar foto actual
            </label>
        </div>
    @endif

    <input type="file" name="photo" accept="image/*"
        class="block w-full text-sm border border-gray-300 rounded px-3 py-2">
    <p class="text-xs text-gray-500 mt-1">JPG, PNG o similar. Máximo 2MB.</p>
    @error('photo') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Nombre *</label>
        <input type="text" name="name" value="{{ old('name', $product->name ?? '') }}" class="{{ $field }}" required>
        @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <div class="flex items-center justify-between mb-1">
            <label class="block text-sm font-medium">Categoría</label>
            <button type="button" onclick="toggleNewCategory()" class="text-sm text-blue-600 hover:underline">
                + Nueva
            </button>
        </div>

        <select name="category_id" id="category_id" class="{{ $field }}">
            <option value="">— Sin categoría —</option>
            @foreach ($categories as $c)
                <option value="{{ $c->id }}"
                    @selected(old('category_id', $product->category_id ?? '') == $c->id)>
                    {{ $c->name }}
                </option>
            @endforeach
        </select>
        @error('category_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror

            <div id="new-category-box" class="hidden mt-2 flex gap-2">
                <input type="text" id="new-category-name" placeholder="Nombre de la categoría"
                    class="flex-1 rounded border border-gray-300 px-3 py-2 text-sm">
                <button type="button" onclick="saveNewCategory()"
                        class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-2 rounded text-sm">
                    Guardar
                </button>
                <button type="button" onclick="toggleNewCategory()"
                        class="px-3 py-2 rounded border border-gray-300 text-sm hover:bg-gray-50">
                    Cancelar
                </button>
            </div>
            <p id="new-category-error" class="text-red-600 text-sm mt-1 hidden"></p>
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">SKU / Código</label>
        <input type="text" name="sku" value="{{ old('sku', $product->sku ?? '') }}" class="{{ $field }}">
        @error('sku') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Cantidad *</label>
        <input type="number" min="0" name="quantity" value="{{ old('quantity', $product->quantity ?? 0) }}" class="{{ $field }}" required>
        @error('quantity') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Stock mínimo *</label>
        <input type="number" min="0" name="min_stock" value="{{ old('min_stock', $product->min_stock ?? 0) }}" class="{{ $field }}" required>
        @error('min_stock') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Ubicación</label>
        <input type="text" name="location" value="{{ old('location', $product->location ?? '') }}" class="{{ $field }}">
        @error('location') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Precio</label>
        <input type="number" step="0.01" min="0" name="price" value="{{ old('price', $product->price ?? '') }}" class="{{ $field }}">
        @error('price') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Notas</label>
        <textarea name="notes" rows="3" class="{{ $field }}">{{ old('notes', $product->notes ?? '') }}</textarea>
        @error('notes') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-6 flex gap-3">
    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded">Guardar</button>
    <a href="{{ route('products.index') }}" class="px-5 py-2 rounded border border-gray-300 hover:bg-gray-50">Cancelar</a>
</div>

<script>
    function toggleNewCategory() {
        const box = document.getElementById('new-category-box');
        const error = document.getElementById('new-category-error');
        box.classList.toggle('hidden');
        error.classList.add('hidden');
        if (!box.classList.contains('hidden')) {
            document.getElementById('new-category-name').focus();
        }
    }

    async function saveNewCategory() {
        const nameInput = document.getElementById('new-category-name');
        const errorBox = document.getElementById('new-category-error');
        const name = nameInput.value.trim();

        errorBox.classList.add('hidden');

        if (!name) {
            errorBox.textContent = 'Escribe un nombre.';
            errorBox.classList.remove('hidden');
            return;
        }

        try {
            const response = await fetch('{{ route('categories.store') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ name }),
            });

            const data = await response.json();

            if (!response.ok) {
                const message = data.errors?.name?.[0] || 'No se pudo crear la categoría.';
                errorBox.textContent = message;
                errorBox.classList.remove('hidden');
                return;
            }

            const select = document.getElementById('category_id');
            const option = document.createElement('option');
            option.value = data.id;
            option.textContent = data.name;
            option.selected = true;
            select.appendChild(option);

            nameInput.value = '';
            toggleNewCategory();
        } catch (e) {
            errorBox.textContent = 'Error de conexión. Intenta de nuevo.';
            errorBox.classList.remove('hidden');
        }
    }
</script>