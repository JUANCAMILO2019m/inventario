@extends('layouts.app')

@section('title', $supplier ? 'Editar proveedor' : 'Nuevo proveedor')

@section('content')
    @php
        $input = 'w-full rounded border border-gray-300 px-3 py-2';
        $fields = [
            ['name', 'Nombre *', 'text', true],
            ['contact_name', 'Persona de contacto', 'text', false],
            ['phone', 'Teléfono', 'text', false],
            ['email', 'Correo', 'email', false],
            ['tax_id', 'NIT / Documento', 'text', false],
        ];
    @endphp

    <h1 class="text-2xl font-bold mb-4">{{ $supplier ? 'Editar: ' . $supplier->name : 'Nuevo proveedor' }}</h1>

    <form action="{{ $supplier ? route('suppliers.update', $supplier) : route('suppliers.store') }}" method="POST"
          class="bg-white shadow rounded p-6 max-w-2xl">
        @csrf
        @if ($supplier) @method('PUT') @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach ($fields as [$name, $label, $type, $required])
                <div class="{{ $name === 'name' ? 'md:col-span-2' : '' }}">
                    <label class="block text-sm font-medium mb-1">{{ $label }}</label>
                    <input type="{{ $type }}" name="{{ $name }}" value="{{ old($name, $supplier->{$name} ?? '') }}"
                           class="{{ $input }}" {{ $required ? 'required' : '' }}>
                    @error($name) <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
                </div>
            @endforeach

            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">Notas</label>
                <textarea name="notes" rows="3" class="{{ $input }}">{{ old('notes', $supplier->notes ?? '') }}</textarea>
                @error('notes') <p class="text-red-600 text-sm mt-1">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="mt-6 flex gap-3">
            <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded">Guardar</button>
            <a href="{{ route('suppliers.index') }}" class="px-5 py-2 rounded border border-gray-300 hover:bg-gray-50">Cancelar</a>
        </div>
    </form>
@endsection