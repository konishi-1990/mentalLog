@php
    $labels = $series->map(fn ($l) => $l->logged_on->format('m/d'))->values();
    $stress = $series->pluck('stress')->values();
    $stamina = $series->pluck('stamina')->values();
    $mental = $series->pluck('mental_capacity')->values();
    $sleep = $series->pluck('sleep_hours')->map(fn ($v) => $v === null ? null : (float) $v)->values();
    $sleepDays = $series->whereNotNull('sleep_hours')->count();

    // 相関の強さは目安。n が小さいうちは「傾向」までしか読めない。
    $rClass = function (?float $r): string {
        if ($r === null) return 'text-gray-400';
        return abs($r) >= 0.55 ? 'font-semibold text-gray-900' : 'text-gray-600';
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">分析</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            {{-- 期間 --}}
            <form method="GET" action="{{ route('analytics.index') }}"
                  class="bg-white rounded-lg border border-gray-200 p-5 flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">開始</label>
                    <input type="date" name="from" value="{{ $from }}" class="rounded-md border-gray-300 text-sm">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">終了</label>
                    <input type="date" name="to" value="{{ $to }}" class="rounded-md border-gray-300 text-sm">
                </div>
                <button class="px-4 py-1.5 text-sm bg-gray-800 text-white rounded-md hover:bg-gray-700">集計</button>
            </form>

            {{-- 時系列 --}}
            <section class="bg-white rounded-lg border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">数値の推移</h3>
                @if ($series->isEmpty())
                    <p class="text-sm text-gray-400">この期間のデータはありません。</p>
                @else
                    <canvas id="seriesChart" height="80"></canvas>
                    <p class="mt-3 text-xs text-gray-400">
                        対象 {{ $series->count() }}件 / 睡眠時間の入力済み {{ $sleepDays }}件
                        （睡眠時間は右軸・時間）
                    </p>
                @endif
            </section>

            {{-- 記録率（欠測の可視化） --}}
            <section class="bg-white rounded-lg border border-gray-200 p-6">
                <div class="flex flex-wrap items-baseline justify-between gap-2 mb-1">
                    <h3 class="font-semibold text-gray-800">記録率</h3>
                    <p class="text-sm text-gray-600">
                        <span class="text-2xl font-bold text-gray-800">{{ round($coverage['rate'] * 100) }}%</span>
                        <span class="text-gray-400">（{{ $coverage['logged_days'] }} / {{ $coverage['total_days'] }}日）</span>
                        <span class="ml-3 text-gray-500">最長の未記録：{{ $coverage['longest_gap'] }}日連続</span>
                    </p>
                </div>
                <p class="text-xs text-gray-400 mb-4">
                    書かなかった日も情報です。空白が続いた時期に何があったかを思い出す手がかりに使ってください。
                </p>
                <div class="flex flex-wrap gap-1">
                    @php $loggedSet = array_fill_keys($coverage['logged_dates'], true); @endphp
                    @foreach (Carbon\CarbonPeriod::create($from, $to) as $date)
                        @php $key = $date->format('Y-m-d'); @endphp
                        <span title="{{ $key }}{{ isset($loggedSet[$key]) ? '（記録あり）' : '（未記録）' }}"
                              class="w-4 h-4 rounded-sm {{ isset($loggedSet[$key]) ? 'bg-indigo-500' : 'bg-gray-200' }}"></span>
                    @endforeach
                </div>
                <div class="mt-3 flex items-center gap-4 text-xs text-gray-400">
                    <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-indigo-500"></span>記録あり</span>
                    <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-gray-200"></span>未記録</span>
                </div>
            </section>

            {{-- 相関 --}}
            <section class="bg-white rounded-lg border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-1">指標間の相関</h3>
                <p class="text-xs text-gray-400 mb-4">
                    n は両方の項目が入力されている日数。n が 10 件程度では |r| が 0.55 を超えないと有意とは言えないため、
                    太字以外は「傾向」として読んでください。
                </p>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="text-gray-500">
                            <tr>
                                <th class="py-2 pr-4 text-left font-normal">組み合わせ</th>
                                <th class="py-2 pr-4 text-right font-normal">r</th>
                                <th class="py-2 text-right font-normal">n</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($correlations as $c)
                                <tr>
                                    <td class="py-2 pr-4 text-gray-700">{{ $c['x_label'] }} × {{ $c['y_label'] }}</td>
                                    <td class="py-2 pr-4 text-right {{ $rClass($c['r']) }}">
                                        {{ $c['r'] === null ? '—' : number_format($c['r'], 3) }}
                                    </td>
                                    <td class="py-2 text-right text-gray-400">
                                        入力済み {{ $c['n'] }}件
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- 勤務形態別 --}}
            <section class="bg-white rounded-lg border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">勤務形態別の平均</h3>
                @if (empty($dayTypes))
                    <p class="text-sm text-gray-400">勤務形態が入力されたログがありません。</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-gray-500">
                                <tr>
                                    <th class="py-2 pr-4 text-left font-normal">勤務形態</th>
                                    <th class="py-2 pr-4 text-right font-normal">日数</th>
                                    <th class="py-2 pr-4 text-right font-normal">ストレス</th>
                                    <th class="py-2 pr-4 text-right font-normal">体力</th>
                                    <th class="py-2 pr-4 text-right font-normal">メンタル余裕</th>
                                    <th class="py-2 pr-4 text-right font-normal">睡眠時間</th>
                                    <th class="py-2 text-right font-normal">持ち越し感</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($dayTypes as $d)
                                    <tr>
                                        <td class="py-2 pr-4 text-gray-700">{{ $d['label'] }}</td>
                                        <td class="py-2 pr-4 text-right text-gray-500">{{ $d['days'] }}</td>
                                        <td class="py-2 pr-4 text-right text-gray-700">{{ $d['avg_stress'] === null ? '—' : number_format($d['avg_stress'], 1) }}</td>
                                        <td class="py-2 pr-4 text-right text-gray-700">{{ $d['avg_stamina'] === null ? '—' : number_format($d['avg_stamina'], 1) }}</td>
                                        <td class="py-2 pr-4 text-right text-gray-700">{{ $d['avg_mental_capacity'] === null ? '—' : number_format($d['avg_mental_capacity'], 1) }}</td>
                                        <td class="py-2 pr-4 text-right text-gray-700">
                                            {{ $d['avg_sleep_hours'] === null ? '—' : number_format($d['avg_sleep_hours'], 1) }}
                                            <span class="text-xs text-gray-400">({{ $d['sleep_days'] }})</span>
                                        </td>
                                        <td class="py-2 text-right text-gray-700">
                                            {{ $d['avg_carryover'] === null ? '—' : number_format($d['avg_carryover'], 1) }}
                                            <span class="text-xs text-gray-400">({{ $d['carryover_days'] }})</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-3 text-xs text-gray-400">（）内はその項目が入力済みの日数。未入力は平均から除外している。</p>
                @endif
            </section>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- ストレス源頻度 --}}
                <x-analytics-bars title="ストレス源（○）の頻度" :items="$checkItemFreq" label="name" />
                {{-- 頭のクセ頻度 --}}
                <x-analytics-bars title="頭の中のクセ 頻度" :items="$thoughtFreq" label="label" />
                {{-- 体の反応頻度 --}}
                <x-analytics-bars title="体の反応 頻度" :items="$bodyFreq" label="label" />
                {{-- 相手タグ頻度 --}}
                <x-analytics-bars title="関わった相手 頻度" :items="$personFreq" label="name" />
                {{-- 摂取物頻度 --}}
                <x-analytics-bars title="摂取したもの 頻度" :items="$intakeFreq" label="label" />

                {{-- 回復効果 --}}
                <section class="bg-white rounded-lg border border-gray-200 p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">回復行動の効果（自己申告）</h3>
                    @if (empty($recoveryEffect))
                        <p class="text-sm text-gray-400">回復行動の記録がありません。</p>
                    @else
                        <ul class="space-y-2 text-sm">
                            @foreach ($recoveryEffect as $r)
                                <li class="flex items-center justify-between gap-3">
                                    <span class="text-gray-700">{{ $r['label'] }}</span>
                                    <span class="text-right">
                                        <span class="font-medium text-gray-800">
                                            {{ $r['avg_effect'] === null ? '効果 —' : '効果 '.number_format($r['avg_effect'], 1).'/10' }}
                                        </span>
                                        @if ($r['avg_duration'] !== null)
                                            <span class="text-gray-500">/ {{ round($r['avg_duration']) }}分</span>
                                        @endif
                                        <span class="block text-xs text-gray-400">
                                            実施 {{ $r['days'] }}日 / 効果を入力済み {{ $r['effect_days'] }}日
                                        </span>
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                        <p class="mt-3 text-xs text-gray-400">「効いた感」の平均。翌日のメンタル余裕で測る下の回復パターンとは測り方が異なる。</p>
                    @endif
                </section>

                {{-- 回復パターン --}}
                <section class="bg-white rounded-lg border border-gray-200 p-6">
                    <h3 class="font-semibold text-gray-800 mb-4">回復パターン（翌日のメンタル余裕）</h3>
                    @php $hasData = collect($recovery)->contains(fn ($r) => $r['with_next_avg'] !== null); @endphp
                    @if (! $hasData)
                        <p class="text-sm text-gray-400">十分なデータがありません。</p>
                    @else
                        <ul class="space-y-2 text-sm">
                            @foreach (collect($recovery)->where('with_next_avg', '!==', null)->sortByDesc('delta') as $r)
                                <li class="flex items-center justify-between">
                                    <span class="text-gray-700">{{ $r['label'] }}</span>
                                    <span class="{{ ($r['delta'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }} font-medium">
                                        翌日余裕 {{ number_format($r['with_next_avg'], 1) }}
                                        @if ($r['delta'] !== null)
                                            （{{ $r['delta'] >= 0 ? '+' : '' }}{{ number_format($r['delta'], 1) }}）
                                        @endif
                                    </span>
                                </li>
                            @endforeach
                        </ul>
                        <p class="mt-3 text-xs text-gray-400">（）内はその行動を取らなかった翌日との差。プラスほど回復に効いている可能性。</p>
                    @endif
                </section>
            </div>
        </div>
    </div>

    @if ($series->isNotEmpty())
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                new window.Chart(document.getElementById('seriesChart'), {
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
