@extends('layouts.app')

@section('title', 'Nuevo producto')

@section('content')
    <h1 class="text-2xl font-bold mb-4">Nuevo producto</h1>

    <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data" class="bg-white shadow rounded p-6">
        @include('products._form')
    </form>
@endsection