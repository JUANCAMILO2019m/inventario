@extends('layouts.app')

@section('title', 'Nuevo conteo')

@section('content')
    @php $input = 'w-full rounded border border-gray-300 px-3 py-2'; @endphp

    <h1 class="text-2xl font-bold mb-4">Nuevo conteo de inventario</h1>

    <form action="{{ route('counts.store') }}" method="POST" class="bg-white shadow rounded p-6 max-w-2xl">
        @csrf

        <div class="space-y-4">
            <div>
                <label class="block text-sm font-medium mb-1">Nombre *</label>
                <input type="text" name="name" maxlength="255" required
                       value="{{ old('name', 'Conteo del ' . now()->format('d/m/Y')) }}" class="{{ $input }}">
                @error('name') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium mb-1">Alcance</label>
                <select name="category_id" class="{{ $input }}">
                    <option value="">Todos los productos</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->id }}" @selected((string) old('category_id') === (string) $c->id)>{{ $c->name }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">
                    Si tienes muchos productos, cuenta por categorías: es más cómodo y evita hojas muy largas.
                </p>
                @error('category_id') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>

            <label class="flex items-start gap-2">
                <input type="checkbox" name="include_zero" value="1" class="mt-1" @checked(!old() || old('include_zero'))>
                <span>
                    <span class="font-medium">Incluir productos con stock en 0</span>
                    <span class="block text-xs text-gray-500">Útil para confirmar que de verdad no queda nada.</span>
                </span>
            </label>

            <label class="flex items-start gap-2">
                <input type="checkbox" name="blind" value="1" class="mt-1" @checked(old('blind'))>
                <span>
                    <span class="font-medium">Conteo ciego</span>
                    <span class="block text-xs text-gray-500">
                        Oculta la cantidad del sistema mientras cuentas, para no influirte. La verás en la revisión.
                    </span>
                </span>
            </label>

            <div>
                <label class="block text-sm font-medium mb-1">Notas</label>
                <textarea name="notes" rows="2" maxlength="1000" class="{{ $input }}">{{ old('notes') }}</textarea>
                @error('notes') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <p class="text-sm text-gray-600 mt-4">
            Al empezar, el sistema guarda una foto de las cantidades actuales. Podrás cargar lo contado poco a poco,
            salir y volver. Nada cambia en el inventario hasta que apliques los ajustes.
        </p>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded">Empezar conteo</button>
            <a href="{{ route('counts.index') }}" class="px-5 py-2 rounded border border-gray-300 hover:bg-gray-50">Cancelar</a>
        </div>
    </form>
@endsection