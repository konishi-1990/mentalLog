<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800">ユーザ管理</h2>
            <a href="{{ route('admin.users.create') }}"
               class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700">新規登録</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg border border-gray-200 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-left">名前</th>
                            <th class="px-4 py-3 text-left">メール</th>
                            <th class="px-4 py-3 text-left">ロール</th>
                            <th class="px-4 py-3 text-center">状態</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($users as $user)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">{{ $user->name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $user->email }}</td>
                                <td class="px-4 py-3">{{ $user->role?->name }}</td>
                                <td class="px-4 py-3 text-center">
                                    @if ($user->is_active)
                                        <span class="text-green-600">有効</span>
                                    @else
                                        <span class="text-gray-400">無効</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right space-x-3">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="text-indigo-600 hover:underline">編集</a>
                                    <a href="{{ route('logs.index', ['user_id' => $user->id]) }}" class="text-gray-500 hover:underline">ログ</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-4">{{ $users->links() }}</div>
        </div>
    </div>
</x-app-layout>
