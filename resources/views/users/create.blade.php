@extends('layouts.app')

@section('title', 'Nuevo usuario')

@section('content')
    <h1 class="text-2xl font-bold mb-4">Nuevo usuario</h1>

    <form action="{{ route('users.store') }}" method="POST" class="bg-white shadow rounded p-6 max-w-2xl">
        @include('users._form')
    </form>
@endsection