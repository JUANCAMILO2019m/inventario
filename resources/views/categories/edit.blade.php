@extends('layouts.app')

@section('title', 'Editar categoría')

@section('content')
    <h1 class="text-2xl font-bold mb-4">Editar: {{ $category->name }}</h1>

    <form action="{{ route('categories.update', $category) }}" method="POST" class="bg-white shadow rounded p-6 max-w-md">
        @method('PUT')
        @include('categories._form')
    </form>
@endsection