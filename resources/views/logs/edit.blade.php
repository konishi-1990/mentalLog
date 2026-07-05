<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">ログを編集（{{ $log->logged_on->format('Y-m-d') }}）</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('logs.update', $log) }}">
                @csrf
                @method('PUT')
                @include('logs.partials.form')
            </form>
        </div>
    </div>
</x-app-layout>
