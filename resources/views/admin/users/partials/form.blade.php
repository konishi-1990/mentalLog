@php $user = $user ?? null; @endphp

<div class="bg-white rounded-lg border border-gray-200 p-6 space-y-4 max-w-lg">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">名前</label>
        <input type="text" name="name" value="{{ old('name', $user?->name) }}"
               class="w-full rounded-md border-gray-300 text-sm">
        @error('name') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">メールアドレス</label>
        <input type="email" name="email" value="{{ old('email', $user?->email) }}"
               class="w-full rounded-md border-gray-300 text-sm">
        @error('email') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">
            パスワード @if ($user) <span class="text-xs text-gray-400">（変更する場合のみ）</span> @endif
        </label>
        <input type="password" name="password" class="w-full rounded-md border-gray-300 text-sm">
        @error('password') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">パスワード（確認）</label>
        <input type="password" name="password_confirmation" class="w-full rounded-md border-gray-300 text-sm">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">ロール</label>
        <select name="role_id" class="w-full rounded-md border-gray-300 text-sm">
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected(old('role_id', $user?->role_id) == $role->id)>{{ $role->name }}</option>
            @endforeach
        </select>
        @error('role_id') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
    </div>
    <div>
        <label class="inline-flex items-center gap-2 text-sm text-gray-700">
            <input type="checkbox" name="is_active" value="1"
                   {{ old('is_active', $user?->is_active ?? true) ? 'checked' : '' }}
                   class="rounded border-gray-300 text-indigo-600">
            有効
        </label>
    </div>

    <div class="flex items-center gap-3 pt-2">
        <button class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700">保存</button>
        <a href="{{ route('admin.users.index') }}" class="text-sm text-gray-500 hover:underline">キャンセル</a>
    </div>
</div>
