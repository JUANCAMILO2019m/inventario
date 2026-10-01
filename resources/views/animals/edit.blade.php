@extends('layouts.app')

@section('title', 'Editar animal')

@section('content')
    <h1 class="text-2xl font-bold mb-4">Editar: {{ $animal->name }}</h1>

    <form action="{{ route('animals.update', $animal) }}" method="POST" enctype="multipart/form-data" class="bg-white shadow rounded p-6">
        @method('PUT')
        @include('animals._form')
    </form>
@endsection