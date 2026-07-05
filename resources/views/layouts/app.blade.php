<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'MentalLog') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-50 flex">
            {{-- サイドナビ --}}
            @include('layouts.sidebar')

            {{-- メインエリア --}}
            <div class="flex-1 flex flex-col min-w-0">
                {{-- ページヘッダ --}}
                @isset($header)
                    <header class="bg-white border-b border-gray-200">
                        <div class="max-w-7xl mx-auto py-5 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                {{-- フラッシュメッセージ --}}
                @if (session('status'))
                    <div class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 pt-4">
                        <div class="rounded-md bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">
                            {{ session('status') }}
                        </div>
                    </div>
                @endif

                {{-- コンテンツ --}}
                <main class="flex-1">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
