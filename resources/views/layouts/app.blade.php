@php
    $user = auth()->user();
    $all = ['admin', 'operator', 'customer'];
    $staff = ['admin', 'operator'];
    $unread = $user->unreadNotifications()->count();

    // Setiap menu punya nama route. Kalau route-nya belum ada, tampil abu-abu "Segera".
    $menu = [
        ['label' => 'Dashboard',  'route' => 'dashboard',        'match' => 'dashboard',    'roles' => $all],
        ['label' => 'Shipments',  'route' => 'shipments.index',  'match' => 'shipments.*',  'roles' => $all],
        ['label' => 'Tracking',   'route' => 'tracking.index',   'match' => 'tracking.*',   'roles' => $all],
        ['label' => 'Exceptions', 'route' => 'exceptions.index', 'match' => 'exceptions.*', 'roles' => $staff],
        ['label' => 'Carriers',   'route' => 'carriers.index',   'match' => 'carriers.*',   'roles' => ['admin']],
        ['label' => 'Locations',  'route' => 'locations.index',  'match' => 'locations.*',  'roles' => ['admin']],
        ['label' => 'Reports',    'route' => 'reports.index',    'match' => 'reports.*',    'roles' => $staff],
        ['label' => 'Users',      'route' => 'users.index',      'match' => 'users.*',      'roles' => ['admin']],
        ['label' => 'Audit Log',  'route' => 'audit-logs.index', 'match' => 'audit-logs.*', 'roles' => ['admin']],
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'CargoTrace') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <style>[x-cloak] { display: none !important; }</style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-100 text-gray-800">
<div x-data="{ open: false }" class="min-h-screen md:flex">

    {{-- Lapisan gelap saat sidebar dibuka di layar kecil --}}
    <div x-show="open" x-cloak @click="open = false" class="fixed inset-0 z-30 bg-black/50 md:hidden"></div>

    {{-- Sidebar --}}
    <aside :class="open ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-40 w-64 flex flex-col bg-slate-900 text-slate-200 transform transition-transform duration-200 md:static md:translate-x-0">
        <div class="h-16 flex items-center px-6 text-lg font-semibold text-white border-b border-slate-800">
            CargoTrace
        </div>

        <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
            @foreach ($menu as $item)
                @continue(! in_array($user->role, $item['roles'], true))

                @if (Route::has($item['route']))
                    <a href="{{ route($item['route']) }}"
                       class="block px-3 py-2 rounded-md text-sm {{ request()->routeIs($item['match']) ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800' }}">
                        {{ $item['label'] }}
                    </a>
                @else
                    <span class="flex items-center justify-between px-3 py-2 rounded-md text-sm text-slate-500 cursor-not-allowed">
                        {{ $item['label'] }}
                        <span class="text-[10px] uppercase tracking-wide">Segera</span>
                    </span>
                @endif
            @endforeach
        </nav>

        <div class="p-4 border-t border-slate-800 text-xs text-slate-400">
            Masuk sebagai <span class="text-slate-200">{{ ucfirst($user->role) }}</span>
        </div>
    </aside>

    {{-- Area konten --}}
    <div class="flex-1 min-w-0 flex flex-col">
        <header class="h-16 bg-white shadow-sm flex items-center justify-between px-4 sm:px-6">
            <div class="flex items-center gap-3">
                <button @click="open = true" class="md:hidden p-2 rounded-md text-gray-500 hover:bg-gray-100" aria-label="Buka menu">☰</button>
                @isset($header)
                    {{ $header }}
                @endisset
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('notifications.index') }}" class="relative p-2 text-gray-500 hover:text-gray-800" aria-label="Notifikasi">
                    🔔
                    @if ($unread > 0)
                        <span class="absolute -top-0.5 -right-0.5 min-w-[1.1rem] h-[1.1rem] px-1 rounded-full bg-red-600 text-white text-[10px] leading-[1.1rem] text-center">
                            {{ $unread > 99 ? '99+' : $unread }}
                        </span>
                    @endif
                </a>

                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center text-sm text-gray-600 hover:text-gray-800">
                            {{ $user->name }} <span class="ms-1">▾</span>
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">Profile</x-dropdown-link>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')"
                                onclick="event.preventDefault(); this.closest('form').submit();">
                                Log Out
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>
        </header>

        <main class="flex-1 p-4 sm:p-6">
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>