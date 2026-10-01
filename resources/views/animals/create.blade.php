@extends('layouts.app')

@section('title', 'Nuevo animal')

@section('content')
    <h1 class="text-2xl font-bold mb-4">Nuevo animal</h1>

    <form action="{{ route('animals.store') }}" method="POST" enctype="multipart/form-data" class="bg-white shadow rounded p-6">
        @include('animals._form')
    </form>
@endsection