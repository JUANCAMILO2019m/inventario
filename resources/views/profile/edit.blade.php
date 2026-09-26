@extends('layouts.app')

@section('title', 'Perfil')

@section('content')
    <h1 class="text-2xl font-bold mb-4">Perfil</h1>

    <div class="space-y-6 max-w-2xl">
        @include('profile.partials.update-profile-information-form')
        @include('profile.partials.update-password-form')
        @include('profile.partials.delete-user-form')
    </div>
@endsection