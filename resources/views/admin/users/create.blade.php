<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">ユーザ新規登録</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.users.store') }}">
                @csrf
                @include('admin.users.partials.form')
            </form>
        </div>
    </div>
</x-app-layout>
