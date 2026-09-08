@php
    $labels = $series->map(fn ($l) => $l->logged_on->format('m/d'))->values();
    $stress = $series->pluck('stress')->values();
    $stamina = $series->pluck('stamina')->values();
    $mental = $series->pluck('mental_capacity')->values();
    $sleep = $series->pluck('sleep_hours')->map(fn ($v) => $v === null ? null : (float) $v)->values();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold text-gray-800">ダッシュボード</h2>
            @unless ($todayLog)
                <a href="{{ route('logs.create') }}"
                   class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700">今日のログを書く</a>
            @else
                <a href="{{ route('logs.show', $todayLog) }}"
                   class="px-4 py-2 text-sm bg-white border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50">今日のログを見る</a>
            @endunless
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            {{-- 記録が途切れているときだけ促す。1件も無い初回利用時は出さない --}}
            @if ($coverage['logged_days'] > 0 && $coverage['current_gap'] >= \App\Services\AnalyticsService::NUDGE_GAP_DAYS)
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-5 py-4">
                    <p class="text-sm text-amber-900">
                        <span class="font-semibold">{{ $coverage['current_gap'] }}日続けて記録がありません。</span>
                        書けなかった日ほど分析から抜け落ちて、平均が実態より軽く出ます。
                        数値3つだけでも残しておくと後で読めます。
                    </p>
                    <a href="{{ route('logs.create') }}" class="mt-2 inline-block text-sm text-amber-800 underline">
                        今日のログを書く
                    </a>
                </div>
            @endif

            {{-- 直近14日の推移 --}}
            <section class="bg-white rounded-lg border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">直近14日の推移</h3>
                @if ($series->isEmpty())
                    <p class="text-sm text-gray-400">まだデータがありません。ログを記録すると推移が表示されます。</p>
                @else
                    <canvas id="trendChart" height="90"></canvas>
                @endif
            </section>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                {{-- 睡眠 × メンタル余裕（今回いちばん検証したい仮説） --}}
                <section class="bg-white rounded-lg border border-gray-200 p-6">
                    <h3 class="text-sm text-gray-500 mb-2">睡眠時間 × メンタル余裕</h3>
                    @if ($sleepMental === null || $sleepMental['r'] === null)
                        <p class="text-3xl font-bold text-gray-300">—</p>
                        <p class="mt-1 text-xs text-gray-400">
                            睡眠時間の入力が {{ $sleepMental['n'] ?? 0 }}件。2件以上たまると相関が出ます。
                        </p>
                    @else
                        <p class="text-3xl font-bold {{ abs($sleepMental['r']) >= 0.55 ? 'text-gray-800' : 'text-gray-500' }}">
                            {{ number_format($sleepMental['r'], 2) }}
                            <span class="text-base font-normal text-gray-400">r</span>
                        </p>
                        <p class="mt-1 text-xs text-gray-400">
                            入力済み {{ $sleepMental['n'] }}件。
                            {{ abs($sleepMental['r']) >= 0.55 ? 'はっきりした関係が出ています。' : 'まだ傾向どまりです。' }}
                        </p>
                    @endif
                </section>

                {{-- 直近ストレス平均 --}}
                <section class="bg-white rounded-lg border border-gray-200 p-6">
                    <h3 class="text-sm text-gray-500 mb-2">直近14日のストレス平均</h3>
                    <p class="text-3xl font-bold text-gray-800">
                        {{ $recentStress !== null ? number_format($recentStress, 1) : '—' }}
                        <span class="text-base font-normal text-gray-400">/ 10</span>
                    </p>
                </section>

                {{-- よく出る思考のクセ --}}
                <section class="bg-white rounded-lg border border-gray-200 p-6">
                    <h3 class="text-sm text-gray-500 mb-2">よく出る思考のクセ TOP3</h3>
                    @if ($topHabits->isEmpty())
                        <p class="text-sm text-gray-400">データがありません。</p>
                    @else
                        <ol class="space-y-1 text-sm text-gray-700 list-decimal list-inside">
                            @foreach ($topHabits as $h)
                                <li>{{ $h->label }} <span class="text-gray-400">（{{ $h->total }}回）</span></li>
                            @endforeach
                        </ol>
                    @endif
                </section>
            </div>
        </div>
    </div>

    @if ($series->isNotEmpty())
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                new window.Chart(document.getElementById('trendChart'), {
                    type: 'line',
                    data: {
                        labels: @json($labels),
                        datasets: [
                            { label: 'ストレス', data: @json($stress), borderColor: '#e53e3e', tension: 0.3 },
                            { label: '体力', data: @json($stamina), borderColor: '#38a169', tension: 0.3 },
                            { label: 'メンタル余裕', data: @json($mental), borderColor: '#3182ce', tension: 0.3 },
                            {
                                label: '睡眠時間',
                                data: @json($sleep),
                                borderColor: '#805ad5',
                                borderDash: [4, 3],
                                tension: 0.3,
                                yAxisID: 'ySleep',
                                spanGaps: true,
                            },
                        ],
                    },
                    options: {
                        scales: {
                            y: { min: 0, max: 10 },
                            ySleep: { position: 'right', min: 0, max: 12, grid: { drawOnChartArea: false } },
                        },
                        plugins: { legend: { position: 'bottom' } },
                    },
                });
            });
        </script>
    @endif
</x-app-layout>
