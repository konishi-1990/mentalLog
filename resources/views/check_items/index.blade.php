<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">○×項目の設定</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <p class="text-sm text-gray-500">
                ストレス源のカテゴリを管理します。「コミュニティ」などは複数追加できます。無効化しても過去のログは保持されます。
            </p>

            {{-- 一覧・編集 --}}
            <div class="bg-white rounded-lg border border-gray-200 divide-y divide-gray-100">
                @forelse ($checkItems as $item)
                    <div class="p-4 flex items-center gap-3 {{ $item->is_active ? '' : 'bg-gray-50' }}">
                        <form method="POST" action="{{ route('check-items.update', $item) }}"
                              class="flex items-center gap-3 flex-1">
                            @csrf @method('PUT')
                            <input type="text" name="name" value="{{ $item->name }}"
                                   class="flex-1 rounded-md border-gray-300 text-sm">
                            <label class="inline-flex items-center gap-1 text-sm text-gray-600">
                                <input type="checkbox" name="is_active" value="1" {{ $item->is_active ? 'checked' : '' }}
                                       class="rounded border-gray-300 text-indigo-600">
                                有効
                            </label>
                            <button class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700">保存</button>
                        </form>
                        @error('name')
                            <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @empty
                    <p class="p-6 text-sm text-gray-500">項目がありません。下から追加してください。</p>
                @endforelse
            </div>

            {{-- 追加 --}}
            <form method="POST" action="{{ route('check-items.store') }}"
                  class="bg-white rounded-lg border border-gray-200 p-4 flex items-center gap-3">
                @csrf
                <input type="text" name="name" placeholder="新しい項目名（例：コミュニティA）"
                       class="flex-1 rounded-md border-gray-300 text-sm" value="{{ old('name') }}">
                <button class="px-4 py-1.5 text-sm bg-gray-800 text-white rounded-md hover:bg-gray-700">追加</button>
            </form>

            {{-- 並び替え --}}
            @if ($checkItems->count() > 1)
                <form method="POST" action="{{ route('check-items.reorder') }}"
                      class="bg-white rounded-lg border border-gray-200 p-4">
                    @csrf @method('PUT')
                    <p class="text-sm text-gray-600 mb-3">並び替え（▲▼で入れ替え → 保存）</p>
                    <ul id="reorder-list" class="space-y-2">
                        @foreach ($checkItems as $item)
                            <li class="flex items-center gap-2 border border-gray-200 rounded-md px-3 py-2" data-id="{{ $item->id }}">
                                <span class="flex-1 text-sm">{{ $item->name }}</span>
                                <button type="button" class="text-gray-400 hover:text-gray-700" data-move="up">▲</button>
                                <button type="button" class="text-gray-400 hover:text-gray-700" data-move="down">▼</button>
                                <input type="hidden" name="order[]" value="{{ $item->id }}">
                            </li>
                        @endforeach
                    </ul>
                    <button class="mt-3 px-4 py-1.5 text-sm bg-gray-800 text-white rounded-md hover:bg-gray-700">並び順を保存</button>
                </form>
            @endif
        </div>
    </div>

    <script>
        document.querySelectorAll('#reorder-list [data-move]').forEach(btn => {
            btn.addEventListener('click', () => {
                const li = btn.closest('li');
                if (btn.dataset.move === 'up' && li.previousElementSibling) {
                    li.parentNode.insertBefore(li, li.previousElementSibling);
                } else if (btn.dataset.move === 'down' && li.nextElementSibling) {
                    li.parentNode.insertBefore(li.nextElementSibling, li);
                }
            });
        });
    </script>
</x-app-layout>
