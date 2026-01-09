<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="icon" href="{{ asset('img/Amelia.png') }}" type="image/png">

        <title>{{ config('app.name', 'GUTO') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased relative min-h-screen overflow-hidden bg-gray-900">
        
        <div class="fixed inset-0 animate-camaleao -z-30"></div>

        <div class="fixed inset-0 -z-20" style="background-image: url('{{ asset('img/bg.png') }}'); background-repeat: repeat; mix-blend-mode: screen;"></div>

        <div class="fixed inset-0 bg-black/20 backdrop-blur-sm -z-10"></div>

        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 p-4 relative z-10">
            
            <div class="w-full sm:max-w-md bg-white/10 dark:bg-gray-900/20 shadow-[0_0_50px_rgba(0,0,0,0.5)] overflow-hidden sm:rounded-3xl backdrop-blur-xs border border-white/20">
                
                <div class="flex justify-center pt-8 pb-4">
                    <a href="/">
                        <x-application-logo class="w-24 h-24 fill-current text-white bg-white rounded-xl" />
                    </a>
                </div>

                <div class="px-8 py-6">
                    {{ $slot }}
                </div>

            </div>

        </div>
    </body>
</html>