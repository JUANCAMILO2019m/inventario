@extends('layouts.app')

@section('title', 'Nueva categoría')

@section('content')
    <h1 class="text-2xl font-bold mb-4">Nueva categoría</h1>

    <form action="{{ route('categories.store') }}" method="POST" class="bg-white shadow rounded p-6 max-w-md">
        @include('categories._form')
    </form>
@endsection