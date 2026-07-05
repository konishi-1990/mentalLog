@php
    // ストレスは高いほど赤、体力/余裕は高いほど緑
    $scoreClass = function (string $field, int $v): string {
        $bad = $field === 'stress' ? $v >= 7 : $v <= 3;
        $good = $field === 'stress' ? $v <= 3 : $v >= 7;
        return $bad ? 'score-badge--high' : ($good ? 'score-badge--low' : 'score-badge--mid');
    };
    $scores = ['stress' => 'ストレス', 'stamina' => '体力', 'mental_capacity' => 'メンタル余裕'];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800">{{ $log->logged_on->format('Y-m-d') }} のログ</h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('logs.edit', $log) }}"
                   class="px-3 py-1.5 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700">編集</a>
                <form method="POST" action="{{ route('logs.destroy', $log) }}"
                      onsubmit="return confirm('このログを削除しますか？');">
                    @csrf @method('DELETE')
                    <button class="px-3 py-1.5 text-sm bg-red-50 text-red-700 border border-red-200 rounded-md hover:bg-red-100">削除</button>
                </form>
                <a href="{{ route('logs.index') }}" class="px-3 py-1.5 text-sm text-gray-600 hover:underline">一覧へ</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            {{-- 数値 --}}
            <section class="bg-white rounded-lg border border-gray-200 p-6">
                <div class="grid grid-cols-3 gap-4 text-center">
                    @foreach ($scores as $field => $label)
                        <div>
                            <div class="text-sm text-gray-500 mb-2">{{ $label }}</div>
                            <span class="score-badge {{ $scoreClass($field, $log->$field) }}">{{ $log->$field }}</span>
                            <span class="text-gray-400 text-sm"> / 10</span>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- ○×項目 --}}
            <section class="bg-white rounded-lg border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-3">ストレス源</h3>
                @php $onValues = $log->checkItemValues->where('is_on', true); @endphp
                @if ($onValues->isEmpty())
                    <p class="text-sm text-gray-400">○の項目はありません。</p>
                @else
                    <ul class="space-y-1">
                        @foreach ($onValues as $v)
                            <li class="text-sm text-gray-700">
                                <span class="font-medium">{{ $v->checkItem->name }}</span>
                                @if ($v->detail_text)
                                    <span class="text-gray-500">— {{ $v->detail_text }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- テキスト --}}
            <section class="bg-white rounded-lg border border-gray-200 p-6 space-y-4">
                <div>
                    <div class="text-sm text-gray-500 mb-1">今日一番きつかったこと</div>
                    <p class="text-gray-800 whitespace-pre-line">{{ $log->hardest_text ?: '—' }}</p>
                </div>
                <div>
                    <div class="text-sm text-gray-500 mb-1">一言まとめ</div>
                    <p class="text-gray-800">{{ $log->summary_text ?: '—' }}</p>
                </div>
            </section>

            {{-- チェック --}}
            <section class="bg-white rounded-lg border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-3">チェック</h3>
                @php $byCat = $log->checklistSelections->groupBy(fn ($s) => $s->option->category->name); @endphp
                @if ($byCat->isEmpty())
                    <p class="text-sm text-gray-400">選択はありません。</p>
                @else
                    <div class="space-y-3">
                        @foreach ($byCat as $catName => $selections)
                            <div>
                                <div class="text-sm text-gray-500 mb-1">{{ $catName }}</div>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($selections as $s)
                                        <span class="inline-block px-2 py-1 text-xs bg-gray-100 text-gray-700 rounded">
                                            {{ $s->option->label }}{{ $s->detail_text ? '：'.$s->detail_text : '' }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
