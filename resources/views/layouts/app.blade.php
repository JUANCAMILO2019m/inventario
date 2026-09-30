
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'Mi Inventario')</title>

    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">

    <!-- BARRA DE NAVEGACIÓN -->
    <nav class="sticky top-0 z-50 border-b border-emerald-800 bg-emerald-950 text-white shadow-lg">

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            <div class="flex min-h-20 items-center justify-between gap-4">

                <!-- LOGO Y NOMBRE -->
                <a href="{{ route('dashboard') }}"
                    class="group flex shrink-0 items-center gap-3">

                    <div class="flex h-11 w-11 items-center justify-center rounded-xl
                                bg-emerald-500 text-2xl shadow-md
                                transition duration-200 group-hover:bg-emerald-400">
                        🌱
                    </div>

                    <div class="leading-tight">
                        <span class="block text-lg font-extrabold tracking-tight text-white">
                            Mi Inventario
                        </span>

                        <span class="mt-1 block text-xs font-medium text-emerald-300">
                            Gestión agrícola y pecuaria
                        </span>
                    </div>

                </a>

                <!-- NAVEGACIÓN PARA COMPUTADOR -->
                <div class="hidden items-center gap-1 lg:flex">

                    @auth

                        <a href="{{ route('dashboard') }}"
                            class="rounded-lg px-3 py-2.5 text-sm font-medium transition
                            {{ request()->routeIs('dashboard')
                                ? 'bg-emerald-500 text-white shadow-sm'
                                : 'text-emerald-100 hover:bg-emerald-900 hover:text-white' }}">
                            <span class="mr-1.5">⌂</span>
                            Inicio
                        </a>

                        <a href="{{ route('products.index') }}"
                            class="rounded-lg px-3 py-2.5 text-sm font-medium transition
                            {{ request()->routeIs('products.*')
                                ? 'bg-emerald-500 text-white shadow-sm'
                                : 'text-emerald-100 hover:bg-emerald-900 hover:text-white' }}">
                            <span class="mr-1.5">📦</span>
                            Productos
                        </a>

                        <a href="{{ route('categories.index') }}"
                            class="rounded-lg px-3 py-2.5 text-sm font-medium transition
                            {{ request()->routeIs('categories.*')
                                ? 'bg-emerald-500 text-white shadow-sm'
                                : 'text-emerald-100 hover:bg-emerald-900 hover:text-white' }}">
                            <span class="mr-1.5">▦</span>
                            Categorías
                        </a>

                        <a href="{{ route('movements.index') }}"
                        class="rounded-lg px-3 py-2.5 text-sm font-medium transition
                            {{ request()->routeIs('movements.*')
                                ? 'bg-emerald-500 text-white shadow-sm'
                                : 'text-emerald-100 hover:bg-emerald-900 hover:text-white' }}">
                            <span class="mr-1.5">↔</span>
                            Historial
                        </a>

                        <a href="{{ route('animals.index') }}"
                            class="rounded-lg px-3 py-2.5 text-sm font-medium transition
                            {{ request()->routeIs('animals.*')
                                ? 'bg-emerald-500 text-white shadow-sm'
                                : 'text-emerald-100 hover:bg-emerald-900 hover:text-white' }}">
                            <span class="mr-1.5">🐄</span>
                            Control animal
                        </a>

                        @if (auth()->user()->isSuperAdmin())
                            <a href="{{ route('users.index') }}"
                                class="rounded-lg px-3 py-2.5 text-sm font-medium transition
                                {{ request()->routeIs('users.*')
                                    ? 'bg-emerald-500 text-white shadow-sm'
                                    : 'text-emerald-100 hover:bg-emerald-900 hover:text-white' }}">
                                Usuarios
                            </a>
                        @endif

                    @endauth

                </div>

                <!-- USUARIO Y BOTÓN MÓVIL -->
                <div class="flex shrink-0 items-center gap-2">

                    @auth
                        <!-- Menú de usuario para computador -->
                        <div class="hidden items-center gap-3 border-l border-emerald-800 pl-4 md:flex">

                            <a href="{{ route('profile.edit') }}"
                                class="flex items-center gap-2 rounded-lg px-2 py-2 transition
                                        hover:bg-emerald-900">

                                <div class="flex h-9 w-9 items-center justify-center rounded-full
                                            bg-emerald-700 font-bold text-white ring-2 ring-emerald-500/40">
                                    {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                                </div>

                                <div class="max-w-32">
                                    <span class="block truncate text-sm font-semibold text-white">
                                        {{ auth()->user()->name }}
                                    </span>
                                    <span class="block text-xs text-emerald-300">
                                        Mi cuenta
                                    </span>
                                </div>
                            </a>

                            <form action="{{ route('logout') }}" method="POST">
                                @csrf

                                <button type="submit"
                                        class="rounded-lg border border-red-400/30 px-3 py-2
                                                text-sm font-medium text-red-200 transition
                                                hover:border-red-400 hover:bg-red-500 hover:text-white">
                                    Salir
                                </button>
                            </form>

                        </div>
                    @endauth

                    <!-- Botón del menú móvil -->
                    <button id="menu-toggle"
                            type="button"
                            aria-label="Abrir menú de navegación"
                            aria-controls="mobile-menu"
                            aria-expanded="false"
                            class="flex h-11 w-11 items-center justify-center rounded-xl
                                    border border-emerald-700 bg-emerald-900 text-xl
                                    text-white transition hover:bg-emerald-800
                                    focus:outline-none focus:ring-2 focus:ring-emerald-400
                                    lg:hidden">

                        <span id="menu-icon">☰</span>
                    </button>

                </div>

            </div>

            <!-- MENÚ PARA CELULARES -->
            <div id="mobile-menu"
                class="hidden border-t border-emerald-800 py-4 lg:hidden">

                @auth

                    <div class="mb-4 flex items-center gap-3 rounded-xl bg-emerald-900 p-3">

                        <div class="flex h-10 w-10 items-center justify-center rounded-full
                                    bg-emerald-600 font-bold text-white">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </div>

                        <div class="min-w-0">
                            <p class="truncate font-semibold text-white">
                                {{ auth()->user()->name }}
                            </p>
                            <p class="truncate text-xs text-emerald-300">
                                {{ auth()->user()->email }}
                            </p>
                        </div>

                    </div>

                    <div class="grid gap-1">

                        <a href="{{ route('dashboard') }}"
                            class="rounded-lg px-4 py-3 text-sm font-medium transition
                            {{ request()->routeIs('dashboard')
                                ? 'bg-emerald-500 text-white'
                                : 'text-emerald-100 hover:bg-emerald-900' }}">
                            ⌂ &nbsp; Inicio
                        </a>

                        <a href="{{ route('products.index') }}"
                            class="rounded-lg px-4 py-3 text-sm font-medium transition
                            {{ request()->routeIs('products.*')
                                ? 'bg-emerald-500 text-white'
                                : 'text-emerald-100 hover:bg-emerald-900' }}">
                            📦 &nbsp; Productos
                        </a>

                        <a href="{{ route('categories.index') }}"
                            class="rounded-lg px-4 py-3 text-sm font-medium transition
                            {{ request()->routeIs('categories.*')
                                ? 'bg-emerald-500 text-white'
                                : 'text-emerald-100 hover:bg-emerald-900' }}">
                            ▦ &nbsp; Categorías
                        </a>

                        <a href="{{ route('movements.index') }}"
                            class="rounded-lg px-4 py-3 text-sm font-medium transition
                            {{ request()->routeIs('movements.*')
                                ? 'bg-emerald-500 text-white'
                                : 'text-emerald-100 hover:bg-emerald-900' }}">
                            ↔ &nbsp; Historial
                        </a>

                        <a href="{{ route('animals.index') }}"
                            class="rounded-lg px-4 py-3 text-sm font-medium transition
                            {{ request()->routeIs('animals.*')
                                ? 'bg-emerald-500 text-white'
                                : 'text-emerald-100 hover:bg-emerald-900' }}">
                            🐄 &nbsp; Control animal
                        </a>

                        @if (auth()->user()->isSuperAdmin())
                            <a href="{{ route('users.index') }}"
                                class="rounded-lg px-4 py-3 text-sm font-medium transition
                                {{ request()->routeIs('users.*')
                                    ? 'bg-emerald-500 text-white'
                                    : 'text-emerald-100 hover:bg-emerald-900' }}">
                                👥 &nbsp; Usuarios
                            </a>
                        @endif

                        <a href="{{ route('profile.edit') }}"
                            class="rounded-lg px-4 py-3 text-sm font-medium text-emerald-100
                                    transition hover:bg-emerald-900">
                            ⚙ &nbsp; Mi perfil
                        </a>

                        <form action="{{ route('logout') }}" method="POST" class="mt-2">
                            @csrf

                            <button type="submit"
                                    class="w-full rounded-lg border border-red-400/30 px-4 py-3
                                            text-left text-sm font-semibold text-red-200
                                            transition hover:bg-red-500 hover:text-white">
                                ↪ &nbsp; Cerrar sesión
                            </button>
                        </form>

                    </div>

                @endauth

            </div>

        </div>

    </nav>

    <!-- CONTENIDO DE CADA VISTA -->
    <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">

        @if (session('success'))
            <div class="mb-6 flex items-start gap-3 rounded-xl border border-green-200
                        bg-green-50 px-4 py-3 text-green-800 shadow-sm">

                <span class="text-lg">✓</span>

                <p class="text-sm font-medium">
                    {{ session('success') }}
                </p>

            </div>
        @endif

        @if (session('error'))
            <div class="mb-6 rounded-xl border border-red-200 bg-red-50
                        px-4 py-3 text-sm font-medium text-red-800 shadow-sm">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')

    </main>

    <!-- FUNCIONAMIENTO DEL MENÚ MÓVIL -->
    <script>
        const menuToggle = document.getElementById('menu-toggle');
        const mobileMenu = document.getElementById('mobile-menu');
        const menuIcon = document.getElementById('menu-icon');

        if (menuToggle && mobileMenu && menuIcon) {
            menuToggle.addEventListener('click', function () {
                const isHidden = mobileMenu.classList.toggle('hidden');

                menuToggle.setAttribute('aria-expanded', String(!isHidden));
                menuToggle.setAttribute(
                    'aria-label',
                    isHidden ? 'Abrir menú de navegación' : 'Cerrar menú de navegación'
                );

                menuIcon.textContent = isHidden ? '☰' : '✕';
            });
        }
    </script>

</body>
</html>