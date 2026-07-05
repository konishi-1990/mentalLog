@php
    $f = $filters ?? [];
    $stressClass = fn (int $v) => $v >= 7 ? 'text-red-600 font-semibold' : ($v <= 3 ? 'text-green-600' : 'text-gray-700');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800">ログ一覧</h2>
            <a href="{{ route('logs.create') }}"
               class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700">ログを書く</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            {{-- 絞り込み --}}
            <form method="GET" action="{{ route('logs.index') }}"
                  class="bg-white rounded-lg border border-gray-200 p-5">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">期間（開始）</label>
                        <input type="date" name="from" value="{{ $f['from'] ?? '' }}"
                               class="w-full rounded-md border-gray-300 text-sm">
                        @error('from') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">期間（終了）</label>
                        <input type="date" name="to" value="{{ $f['to'] ?? '' }}"
                               class="w-full rounded-md border-gray-300 text-sm">
                        @error('to') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                    </div>
                    @if ($isAdmin ?? false)
                        <div class="md:col-span-2">
                            <label class="block text-xs text-gray-500 mb-1">ユーザ</label>
                            <select name="user_id" class="w-full rounded-md border-gray-300 text-sm">
                                <option value="">全ユーザ</option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->id }}" @selected(($f['user_id'] ?? null) == $u->id)>{{ $u->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                    @foreach (['stress' => 'ストレス', 'stamina' => '体力', 'mental' => 'メンタル余裕'] as $key => $label)
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">{{ $label }}（min〜max）</label>
                            <div class="flex items-center gap-2">
                                <input type="number" min="0" max="10" name="{{ $key }}_min" value="{{ $f[$key.'_min'] ?? '' }}"
                                       class="w-full rounded-md border-gray-300 text-sm" placeholder="0">
                                <span class="text-gray-400">〜</span>
                                <input type="number" min="0" max="10" name="{{ $key }}_max" value="{{ $f[$key.'_max'] ?? '' }}"
                                       class="w-full rounded-md border-gray-300 text-sm" placeholder="10">
                            </div>
                            @error($key.'_min') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                            @error($key.'_max') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 flex items-center gap-2">
                    <button type="submit" class="px-4 py-1.5 text-sm bg-gray-800 text-white rounded-md hover:bg-gray-700">検索</button>
                    <a href="{{ route('logs.index') }}" class="px-4 py-1.5 text-sm text-gray-500 hover:underline">クリア</a>
                </div>
            </form>

            {{-- 一覧 --}}
            @if ($logs->isEmpty())
                <div class="bg-white rounded-lg border border-gray-200 p-10 text-center text-gray-500">
                    条件に一致するログがありません。<a href="{{ route('logs.create') }}" class="text-indigo-600 hover:underline">今日のログを書きましょう</a>。
                </div>
            @else
                <div class="bg-white rounded-lg border border-gray-200 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500">
                            <tr>
                                <th class="px-4 py-3 text-left">日付</th>
                                @if ($isAdmin ?? false)<th class="px-4 py-3 text-left">ユーザ</th>@endif
                                <th class="px-4 py-3 text-center">ストレス</th>
                                <th class="px-4 py-3 text-center">体力</th>
                                <th class="px-4 py-3 text-center">余裕</th>
                                <th class="px-4 py-3 text-left">まとめ</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($logs as $log)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">{{ $log->logged_on->format('Y-m-d') }}</td>
                                    @if ($isAdmin ?? false)<td class="px-4 py-3 text-gray-600">{{ $log->user?->name }}</td>@endif
                                    <td class="px-4 py-3 text-center {{ $stressClass($log->stress) }}">{{ $log->stress }}</td>
                                    <td class="px-4 py-3 text-center">{{ $log->stamina }}</td>
                                    <td class="px-4 py-3 text-center">{{ $log->mental_capacity }}</td>
                                    <td class="px-4 py-3 text-gray-600 truncate max-w-xs">{{ $log->summary_text }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ route('logs.show', $log) }}" class="text-indigo-600 hover:underline">詳細</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div>{{ $logs->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
