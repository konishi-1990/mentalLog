<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('code') | MentalLog</title>
    @vite(['resources/css/app.css'])
</head>
<body class="font-sans antialiased">
    <div class="min-h-screen flex items-center justify-center bg-gray-50 px-4">
        <div class="text-center">
            <p class="text-6xl font-bold text-indigo-600">@yield('code')</p>
            <h1 class="mt-4 text-xl font-semibold text-gray-800">@yield('title')</h1>
            <p class="mt-2 text-sm text-gray-500">@yield('message')</p>
            <a href="{{ url('/dashboard') }}"
               class="inline-block mt-6 px-5 py-2 bg-indigo-600 text-white text-sm rounded-md hover:bg-indigo-700">
                ダッシュボードへ戻る
            </a>
        </div>
    </div>
</body>
</html>
