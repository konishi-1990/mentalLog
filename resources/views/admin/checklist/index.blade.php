<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">チェックリスト管理</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
            @foreach ($categories as $category)
                <section class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
                    <h3 class="font-semibold text-gray-800">{{ $category->name }}</h3>

                    {{-- 既存選択肢 --}}
                    <div class="divide-y divide-gray-100">
                        @foreach ($category->options as $option)
                            <form method="POST" action="{{ route('admin.checklist.update', $option) }}"
                                  class="py-3 flex flex-wrap items-center gap-3 {{ $option->is_active ? '' : 'opacity-50' }}">
                                @csrf @method('PUT')
                                <input type="hidden" name="category_id" value="{{ $category->id }}">
                                <input type="text" name="label" value="{{ $option->label }}"
                                       class="flex-1 min-w-48 rounded-md border-gray-300 text-sm">
                                <label class="inline-flex items-center gap-1 text-xs text-gray-600">
                                    <input type="checkbox" name="requires_text" value="1" {{ $option->requires_text ? 'checked' : '' }}
                                           class="rounded border-gray-300"> テキスト要
                                </label>
                                <label class="inline-flex items-center gap-1 text-xs text-gray-600">
                                    <input type="checkbox" name="is_none" value="1" {{ $option->is_none ? 'checked' : '' }}
                                           class="rounded border-gray-300"> 特になし
                                </label>
                                <label class="inline-flex items-center gap-1 text-xs text-gray-600">
                                    <input type="checkbox" name="is_active" value="1" {{ $option->is_active ? 'checked' : '' }}
                                           class="rounded border-gray-300"> 有効
                                </label>
                                <button class="px-3 py-1.5 text-xs bg-indigo-600 text-white rounded-md hover:bg-indigo-700">保存</button>
                        </form>
                        @endforeach
                    </div>

                    {{-- 追加 --}}
                    <form method="POST" action="{{ route('admin.checklist.store') }}"
                          class="flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4">
                        @csrf
                        <input type="hidden" name="category_id" value="{{ $category->id }}">
                        <input type="text" name="label" placeholder="新しい選択肢"
                               class="flex-1 min-w-48 rounded-md border-gray-300 text-sm">
                        <label class="inline-flex items-center gap-1 text-xs text-gray-600">
                            <input type="checkbox" name="requires_text" value="1" class="rounded border-gray-300"> テキスト要
                        </label>
                        <label class="inline-flex items-center gap-1 text-xs text-gray-600">
                            <input type="checkbox" name="is_none" value="1" class="rounded border-gray-300"> 特になし
                        </label>
                        <button class="px-3 py-1.5 text-xs bg-gray-800 text-white rounded-md hover:bg-gray-700">追加</button>
                    </form>
                </section>
            @endforeach
        </div>
    </div>
</x-app-layout>
