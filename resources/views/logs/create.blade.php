<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">ログを書く</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('logs.store') }}">
                @csrf
                @include('logs.partials.form')
            </form>
        </div>
    </div>
</x-app-layout>
