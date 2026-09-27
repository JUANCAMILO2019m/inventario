<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Inventario')</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 text-gray-800">
    <nav class="bg-white shadow">
        <div class="max-w-5xl mx-auto px-4 py-3">
            <div class="flex flex-wrap items-center justify-between gap-y-2">
                <a href="{{ route('dashboard') }}" class="text-lg font-bold whitespace-nowrap">📦 Mi Inventario</a>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm">
                    @auth
                    <a href="{{ route('dashboard') }}" class="hover:underline">Dashboard</a>
                    <a href="{{ route('products.index') }}" class="hover:underline">Productos</a>
                    <a href="{{ route('categories.index') }}" class="hover:underline">Categorías</a>
                    <a href="{{ route('movements.index') }}" class="hover:underline">Historial</a>

                    @if (auth()->user()->isSuperAdmin())
                        <a href="{{ route('users.index') }}" class="hover:underline">Usuarios</a>
                    @endif

                    <a href="{{ route('profile.edit') }}" class="hover:underline">{{ auth()->user()->name }}</a>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button class="text-red-600 hover:underline">Salir</button>
                    </form>
                @endauth
                </div>
            </div>
        </div>
    </nav>

    <main class="max-w-5xl mx-auto px-4 py-6">
        @if (session('success'))
            <div class="mb-4 rounded bg-green-100 border border-green-300 text-green-800 px-4 py-2">
                {{ session('success') }}
            </div>
        @endif

        @yield('content')
    </main>
</body>
</html>
