<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="icon" href="{{ asset('img/Amelia.png') }}" type="image/png">

        <title>{{ config('app.name', 'GUTO') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    @php
        $role = auth()->user()?->role;
        $themeClass = match ($role) {
            'juridico' => 'theme-juridico',
            'mkt' => 'theme-mkt',
            'rh' => 'theme-rh',
            default => 'theme-default',
        };
    @endphp
    <body class="font-sans antialiased {{ $themeClass }}">
        <div
            x-data="{ profileOpen: false, profileTab: 'profile' }"
            x-effect="document.body.classList.toggle('overflow-hidden', profileOpen)"
            @open-profile-modal.window="profileOpen = true; profileTab = 'profile'"
            @close-profile-modal.window="profileOpen = false"
            @keydown.escape.window="profileOpen = false"
        >
            <div class="min-h-screen bg-white dark:bg-gray-900">
                @include('layouts.navigation')

                <!-- Page Heading -->
                @isset($header)
                    <header class="bg-white dark:bg-gray-800 shadow">
                        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main>
                    {{ $slot }}
                </main>
            </div>

            @auth
                @include('profile.modal')
            @endauth
        </div>
    </body>
</html>
