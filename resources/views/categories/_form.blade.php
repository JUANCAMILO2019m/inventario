@csrf

<div>
    <label class="block text-sm font-medium mb-1">Nombre *</label>
    <input type="text" name="name" value="{{ old('name', $category->name ?? '') }}"
           class="w-full rounded border border-gray-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400"
           required autofocus>
    @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
</div>

<div class="mt-6 flex gap-3">
    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded">Guardar</button>
    <a href="{{ route('categories.index') }}" class="px-5 py-2 rounded border border-gray-300 hover:bg-gray-50">Cancelar</a>
</div>