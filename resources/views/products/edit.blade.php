@extends('layouts.app')

@section('title', 'Editar producto')

@section('content')
    <h1 class="text-2xl font-bold mb-4">Editar: {{ $product->name }}</h1>

    <form action="{{ route('products.update', $product) }}" method="POST" enctype="multipart/form-data" class="bg-white shadow rounded p-6">
        @method('PUT')
        @include('products._form')
    </form>
@endsection