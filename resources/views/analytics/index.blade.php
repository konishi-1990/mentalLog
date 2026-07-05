@php
    $labels = $series->map(fn ($l) => $l->logged_on->format('m/d'))->values();
    $stress = $series->pluck('stress')->values();
    $stamina = $series->pluck('stamina')->values();
    $mental = $series->pluck('mental_capacity')->values();

    $bar = function ($items, $labelKey) {
        $max = max(1, collect($items)->max('total') ?? 1);
        return [$items, $max];
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
                @endif
            </section>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- ストレス源頻度 --}}
                <x-analytics-bars title="ストレス源（○）の頻度" :items="$checkItemFreq" label="name" />
                {{-- 頭のクセ頻度 --}}
                <x-analytics-bars title="頭の中のクセ 頻度" :items="$thoughtFreq" label="label" />
                {{-- 体の反応頻度 --}}
                <x-analytics-bars title="体の反応 頻度" :items="$bodyFreq" label="label" />

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
                        ],
                    },
                    options: {
                        scales: { y: { min: 0, max: 10 } },
                        plugins: { legend: { position: 'bottom' } },
                    },
                });
            });
        </script>
    @endif
</x-app-layout>
