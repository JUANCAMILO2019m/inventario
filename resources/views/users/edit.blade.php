@extends('layouts.app')

@section('title', 'Editar usuario')

@section('content')
    <h1 class="text-2xl font-bold mb-4">Editar: {{ $user->name }}</h1>

    <form action="{{ route('users.update', $user) }}" method="POST" class="bg-white shadow rounded p-6 max-w-2xl">
        @method('PUT')
        @include('users._form')
    </form>
@endsection