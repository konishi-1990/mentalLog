<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">ユーザ編集：{{ $user->name }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <form method="POST" action="{{ route('admin.users.update', $user) }}">
                @csrf
                @method('PUT')
                @include('admin.users.partials.form')
            </form>

            <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                  onsubmit="return confirm('このユーザを削除しますか？');" class="max-w-lg">
                @csrf @method('DELETE')
                <button class="text-sm text-red-600 hover:underline">このユーザを削除</button>
            </form>
        </div>
    </div>
</x-app-layout>
