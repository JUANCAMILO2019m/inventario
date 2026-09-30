@csrf

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label class="block text-sm font-medium mb-1">Tipo *</label>
        <select name="type" class="w-full rounded border border-gray-300 px-3 py-2" required>
            <option value="individual" @selected(old('type', $animal->type ?? 'individual') === 'individual')>Individual</option>
            <option value="lot" @selected(old('type', $animal->type ?? '') === 'lot')>Lote</option>
        </select>
        @error('type') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Nombre / Identificación *</label>
        <input type="text" name="name" value="{{ old('name', $animal->name ?? '') }}"
               class="w-full rounded border border-gray-300 px-3 py-2" required>
        @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Código / Arete (opcional)</label>
        <input type="text" name="code" value="{{ old('code', $animal->code ?? '') }}"
               class="w-full rounded border border-gray-300 px-3 py-2">
        @error('code') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Especie *</label>
        <input type="text" name="species" value="{{ old('species', $animal->species ?? '') }}"
               placeholder="Bovino, porcino, aves..."
               class="w-full rounded border border-gray-300 px-3 py-2" required>
        @error('species') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Raza</label>
        <input type="text" name="breed" value="{{ old('breed', $animal->breed ?? '') }}"
               class="w-full rounded border border-gray-300 px-3 py-2">
        @error('breed') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Sexo</label>
        <select name="sex" class="w-full rounded border border-gray-300 px-3 py-2">
            <option value="">— No aplica —</option>
            <option value="male" @selected(old('sex', $animal->sex ?? '') === 'male')>Macho</option>
            <option value="female" @selected(old('sex', $animal->sex ?? '') === 'female')>Hembra</option>
            <option value="mixed" @selected(old('sex', $animal->sex ?? '') === 'mixed')>Mixto (lote)</option>
        </select>
        @error('sex') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Fecha de nacimiento</label>
        <input type="date" name="birth_date"
               value="{{ old('birth_date', isset($animal) && $animal->birth_date ? $animal->birth_date->format('Y-m-d') : '') }}"
               class="w-full rounded border border-gray-300 px-3 py-2">
        @error('birth_date') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Cantidad (cabezas) *</label>
        <input type="number" min="1" name="quantity" value="{{ old('quantity', $animal->quantity ?? 1) }}"
               class="w-full rounded border border-gray-300 px-3 py-2" required>
        <p class="text-xs text-gray-500 mt-1">Usa 1 para animales individuales.</p>
        @error('quantity') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div>
        <label class="block text-sm font-medium mb-1">Estado *</label>
        <select name="status" class="w-full rounded border border-gray-300 px-3 py-2" required>
            <option value="active" @selected(old('status', $animal->status ?? 'active') === 'active')>Activo</option>
            <option value="sold" @selected(old('status', $animal->status ?? '') === 'sold')>Vendido</option>
            <option value="dead" @selected(old('status', $animal->status ?? '') === 'dead')>Baja</option>
        </select>
        @error('status') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>

    <div class="md:col-span-2">
        <label class="block text-sm font-medium mb-1">Descripción</label>
        <textarea name="description" rows="3"
                  class="w-full rounded border border-gray-300 px-3 py-2">{{ old('description', $animal->description ?? '') }}</textarea>
        @error('description') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-6 flex gap-3">
    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded">Guardar</button>
    <a href="{{ route('animals.index') }}" class="px-5 py-2 rounded border border-gray-300 hover:bg-gray-50">Cancelar</a>
</div>