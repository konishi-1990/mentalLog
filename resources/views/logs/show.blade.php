@php
    // 良し悪しの向きは項目ごとに異なる（ストレスと持ち越し感は高いほど悪い）
    $scores = [
        'stress' => ['label' => 'ストレス', 'higher_is_better' => false],
        'stamina' => ['label' => '体力', 'higher_is_better' => true],
        'mental_capacity' => ['label' => 'メンタル余裕', 'higher_is_better' => true],
    ];
    $extraScores = [
        'sleep_quality' => ['label' => '睡眠の質', 'higher_is_better' => true],
        'carryover' => ['label' => '前日からの持ち越し感', 'higher_is_better' => false],
        'controllability' => ['label' => 'コントロール可能度', 'higher_is_better' => true],
    ];

    $scoreClass = function (bool $higherIsBetter, int $v): string {
        $bad = $higherIsBetter ? $v <= 3 : $v >= 7;
        $good = $higherIsBetter ? $v >= 7 : $v <= 3;
        return $bad ? 'score-badge--high' : ($good ? 'score-badge--low' : 'score-badge--mid');
    };
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
                    @foreach ($scores as $field => $meta)
                        <div>
                            <div class="text-sm text-gray-500 mb-2">{{ $meta['label'] }}</div>
                            <span class="score-badge {{ $scoreClass($meta['higher_is_better'], $log->$field) }}">{{ $log->$field }}</span>
                            <span class="text-gray-400 text-sm"> / 10</span>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- くわしく（未入力の項目は「—」。既存ログは全項目が未入力） --}}
            <section class="bg-white rounded-lg border border-gray-200 p-6 space-y-5">
                <h3 class="font-semibold text-gray-800">くわしく</h3>
                <div class="grid grid-cols-2 gap-4 text-center">
                    <div>
                        <div class="text-sm text-gray-500 mb-2">睡眠時間</div>
                        @if ($log->sleep_hours !== null)
                            <span class="text-lg font-semibold text-gray-800">{{ rtrim(rtrim((string) $log->sleep_hours, '0'), '.') }}</span>
                            <span class="text-gray-400 text-sm"> 時間</span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </div>
                    <div>
                        <div class="text-sm text-gray-500 mb-2">勤務形態</div>
                        <span class="{{ $log->day_type ? 'text-gray-800' : 'text-gray-400' }}">
                            {{ \App\Support\DayTypes::label($log->day_type) ?? '—' }}
                        </span>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4 text-center">
                    @foreach ($extraScores as $field => $meta)
                        <div>
                            <div class="text-sm text-gray-500 mb-2">{{ $meta['label'] }}</div>
                            @if ($log->$field !== null)
                                <span class="score-badge {{ $scoreClass($meta['higher_is_better'], $log->$field) }}">{{ $log->$field }}</span>
                                <span class="text-gray-400 text-sm"> / 10</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
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

            {{-- 相手タグ --}}
            <section class="bg-white rounded-lg border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-3">関わった相手</h3>
                @if ($log->people->isEmpty())
                    <p class="text-sm text-gray-400">記録はありません。</p>
                @else
                    <ul class="space-y-1">
                        @foreach ($log->people as $person)
                            <li class="text-sm text-gray-700">
                                <span class="font-medium">{{ $person->name }}</span>
                                @if ($person->pivot->detail_text)
                                    <span class="text-gray-500">— {{ $person->pivot->detail_text }}</span>
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
                                            @if ($s->duration_min !== null || $s->effect_score !== null)
                                                <span class="ml-1 text-gray-500">
                                                    （{{ collect([
                                                        \App\Support\DurationBuckets::describe($s->duration_min),
                                                        $s->effect_score === null ? null
                                                            : '効いた感 '.\App\Support\EffectLevels::describe($s->effect_score),
                                                    ])->filter()->implode(' / ') }}）
                                                </span>
                                            @endif
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
