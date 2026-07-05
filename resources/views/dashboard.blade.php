@php
    $labels = $series->map(fn ($l) => $l->logged_on->format('m/d'))->values();
    $stress = $series->pluck('stress')->values();
    $stamina = $series->pluck('stamina')->values();
    $mental = $series->pluck('mental_capacity')->values();
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
            {{-- 直近14日の推移 --}}
            <section class="bg-white rounded-lg border border-gray-200 p-6">
                <h3 class="font-semibold text-gray-800 mb-4">直近14日の推移</h3>
                @if ($series->isEmpty())
                    <p class="text-sm text-gray-400">まだデータがありません。ログを記録すると推移が表示されます。</p>
                @else
                    <canvas id="trendChart" height="90"></canvas>
                @endif
            </section>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
