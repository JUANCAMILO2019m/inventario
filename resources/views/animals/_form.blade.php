@csrf

@php
    $isEdit = isset($animal);
    $currentType = old('type', $animal->type ?? 'individual');
    $quantityLocked = $isEdit && $animal->isLot() && $animal->hasHeadMovements();
    $input = 'w-full rounded border border-gray-300 px-3 py-2';
@endphp

<div class="mb-6">
    <label class="block text-sm font-medium mb-1">Foto</label>

    @if ($isEdit && $animal->photo)
        <div class="flex items-center gap-4 mb-3">
            <img src="{{ $animal->photo_url }}" alt="{{ $animal->name }}"
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
    <div>
        <label class="block text-sm font-medium mb-1">Tipo *</label>
        <select name="type" id="animal-type" class="{{ $input }}" {{ $isEdit ? 'disabled' : '' }} required>
            <option value="individual" @selected($currentType === 'individual')>Individual</option>
            <option value="lot" @selected($currentType === 'lot')>Lote</option>
        </select>
        @if ($isEdit)
            <p class="text-xs text-gray-500 mt-1">El tipo no se puede cambiar después de crear el registro.</p>
        @endif
        @error('type') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1"><span id="name-label">Nombre / Identificación</span> *</label>
        <input type="text" name="name" value="{{ old('name', $animal->name ?? '') }}"
                class="{{ $input }}" required>
        @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1"><span id="code-label">Código / Arete</span> (opcional)</label>
        <input type="text" name="code" value="{{ old('code', $animal->code ?? '') }}" class="{{ $input }}">
        @error('code') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Especie *</label>
        <input type="text" name="species" value="{{ old('species', $animal->species ?? '') }}"
                placeholder="Bovino, porcino, aves..." class="{{ $input }}" required>
        @error('species') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Raza</label>
        <input type="text" name="breed" value="{{ old('breed', $animal->breed ?? '') }}" class="{{ $input }}">
        @error('breed') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Sexo</label>
        <select name="sex" class="{{ $input }}">
            <option value="">— No aplica —</option>
            <option value="male" @selected(old('sex', $animal->sex ?? '') === 'male')>Macho</option>
            <option value="female" @selected(old('sex', $animal->sex ?? '') === 'female')>Hembra</option>
            <option value="mixed" @selected(old('sex', $animal->sex ?? '') === 'mixed')>Mixto (lote)</option>
        </select>
        @error('sex') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- Solo individuales --}}
    <div data-for="individual">
        <label class="block text-sm font-medium mb-1">Fecha de nacimiento</label>
        <input type="date" name="birth_date"
                value="{{ old('birth_date', $isEdit && $animal->birth_date ? $animal->birth_date->format('Y-m-d') : '') }}"
                class="{{ $input }}">
        @error('birth_date') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    {{-- Solo lotes --}}
    <div data-for="lot">
        <label class="block text-sm font-medium mb-1">
            {{ $quantityLocked ? 'Cabezas actuales' : 'Cantidad inicial (cabezas) *' }}
        </label>
        <input type="number" min="1" name="quantity"
                value="{{ old('quantity', $animal->quantity ?? 1) }}"
                class="{{ $input }} {{ $quantityLocked ? 'bg-gray-100' : '' }}"
                {!! $quantityLocked ? 'disabled data-locked="1"' : 'required' !!}>
        @if ($quantityLocked)
            <p class="text-xs text-gray-500 mt-1">Cambia con bajas, ventas e ingresos, desde la ficha del lote.</p>
        @endif
        @error('quantity') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Fecha de ingreso</label>
        <input type="date" name="entry_date"
                value="{{ old('entry_date', $isEdit && $animal->entry_date ? $animal->entry_date->format('Y-m-d') : '') }}"
                class="{{ $input }}">
        @error('entry_date') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Peso promedio inicial por cabeza (kg)</label>
        <input type="number" step="0.01" min="0" name="initial_weight"
                value="{{ old('initial_weight', $animal->initial_weight ?? '') }}" class="{{ $input }}">
        @error('initial_weight') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Origen / Proveedor</label>
        <input type="text" name="supplier" value="{{ old('supplier', $animal->supplier ?? '') }}"
                class="{{ $input }}">
        @error('supplier') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Costo total de compra</label>
        <input type="number" step="0.01" min="0" name="purchase_cost"
                value="{{ old('purchase_cost', $animal->purchase_cost ?? '') }}" class="{{ $input }}">
        @error('purchase_cost') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Estado *</label>
        <select name="status" class="{{ $input }}" required>
            <option value="active" @selected(old('status', $animal->status ?? 'active') === 'active')>Activo</option>
            <option value="sold" @selected(old('status', $animal->status ?? '') === 'sold')>Vendido</option>
            <option value="dead" @selected(old('status', $animal->status ?? '') === 'dead')>Baja</option>
        </select>
        @error('status') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Descripción</label>
        <textarea name="description" rows="3" class="{{ $input }}">{{ old('description', $animal->description ?? '') }}</textarea>
        @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-6 flex gap-3">
    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded">Guardar</button>
    <a href="{{ route('animals.index') }}" class="px-5 py-2 rounded border border-gray-300 hover:bg-gray-50">Cancelar</a>
</div>

<script>
    (function () {
        const typeSelect = document.getElementById('animal-type');

        function apply() {
            const type = typeSelect.value;

            document.querySelectorAll('[data-for]').forEach(section => {
                const show = section.dataset.for === type;
                section.classList.toggle('hidden', !show);
                section.querySelectorAll('input, select, textarea').forEach(el => {
                    el.disabled = !show || el.dataset.locked === '1';
                });
            });

            document.getElementById('name-label').textContent =
                type === 'lot' ? 'Nombre del lote' : 'Nombre / Identificación';
            document.getElementById('code-label').textContent =
                type === 'lot' ? 'Código de lote' : 'Código / Arete';
        }

        typeSelect.addEventListener('change', apply);
        apply();
    })();
</script>