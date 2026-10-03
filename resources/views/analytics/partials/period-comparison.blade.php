@php
    /**
     * 期間比較カード（AnalyticsService::periodComparison() の戻り値）。
     *
     * @var array $cmp
     */
    $num = fn ($v) => $v === null ? '—' : number_format($v, 1);
    $pct = fn ($v) => $v === null ? '—' : round($v * 100).'%';
    // 後 − 前。どちらかが無ければ差は出さない
    $diff = function ($before, $after, bool $percent = false) {
        if ($before === null || $after === null) {
            return '';
        }
        $d = $after - $before;
        $text = $percent ? sprintf('%+d pt', round($d * 100)) : sprintf('%+.1f', $d);

        return abs($d) < 0.005 ? '±0' : $text;
    };
@endphp

<section class="bg-white rounded-lg border border-gray-200 p-6 space-y-6">
    <div>
        <h3 class="font-semibold text-gray-800 mb-1">期間比較</h3>
        <p class="text-xs text-gray-400">
            区切り日 {{ $cmp['pivot'] }} の前（{{ $cmp['before']['from'] }}〜{{ $cmp['before']['to'] }}）と
            後（{{ $cmp['after']['from'] }}〜{{ $cmp['after']['to'] }}）を比べます。区切り日当日は「後」に入ります。
        </p>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="text-gray-500">
                <tr>
                    <th class="py-2 pr-4 text-left font-normal"></th>
                    <th class="py-2 pr-4 text-right font-normal">前</th>
                    <th class="py-2 pr-4 text-right font-normal">後</th>
                    <th class="py-2 text-right font-normal">差</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <tr>
                    <td class="py-2 pr-4 text-gray-700">記録率</td>
                    <td class="py-2 pr-4 text-right text-gray-700">{{ $pct($cmp['before']['rate']) }} <span class="text-xs text-gray-400">({{ $cmp['before']['logged_days'] }}/{{ $cmp['before']['total_days'] }})</span></td>
                    <td class="py-2 pr-4 text-right text-gray-700">{{ $pct($cmp['after']['rate']) }} <span class="text-xs text-gray-400">({{ $cmp['after']['logged_days'] }}/{{ $cmp['after']['total_days'] }})</span></td>
                    <td class="py-2 text-right text-gray-500">{{ $diff($cmp['before']['rate'], $cmp['after']['rate'], true) }}</td>
                </tr>
                @foreach (\App\Services\AnalyticsService::METRIC_LABELS as $key => $label)
                    @continue($cmp['before']['avg'][$key] === null && $cmp['after']['avg'][$key] === null)
                    <tr>
                        <td class="py-2 pr-4 text-gray-700">{{ $label }}</td>
                        <td class="py-2 pr-4 text-right text-gray-700">{{ $num($cmp['before']['avg'][$key]) }}</td>
                        <td class="py-2 pr-4 text-right font-medium text-gray-800">{{ $num($cmp['after']['avg'][$key]) }}</td>
                        <td class="py-2 text-right text-gray-500">{{ $diff($cmp['before']['avg'][$key], $cmp['after']['avg'][$key]) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td class="py-2 pr-4 text-gray-700">回復行動「何もできてない」</td>
                    <td class="py-2 pr-4 text-right text-gray-700">{{ $pct($cmp['before']['no_recovery_rate']) }}</td>
                    <td class="py-2 pr-4 text-right font-medium text-gray-800">{{ $pct($cmp['after']['no_recovery_rate']) }}</td>
                    <td class="py-2 text-right text-gray-500">{{ $diff($cmp['before']['no_recovery_rate'], $cmp['after']['no_recovery_rate'], true) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @foreach ([
            ['title' => 'ストレス源の○率', 'rows' => $cmp['check_items'], 'key' => 'name', 'note' => '○ / 回答した日数'],
            ['title' => '頭の中のクセの選択率', 'rows' => $cmp['thought_habits'], 'key' => 'label', 'note' => '選んだ日 / 選択肢を追加した日以降の記録日数'],
        ] as $block)
            <div>
                <h4 class="text-sm font-semibold text-gray-700">{{ $block['title'] }}</h4>
                <p class="text-xs text-gray-400 mb-2">{{ $block['note'] }}。選べなかった期間は「—」。</p>
                @if (empty($block['rows']))
                    <p class="text-sm text-gray-400">データがありません。</p>
                @else
                    <table class="min-w-full text-sm">
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($block['rows'] as $row)
                                <tr>
                                    <td class="py-1.5 pr-3 text-gray-700">{{ $row[$block['key']] }}</td>
                                    <td class="py-1.5 pr-3 text-right text-gray-500">{{ $pct($row['before_rate']) }}</td>
                                    <td class="py-1.5 pr-3 text-right text-gray-800">{{ $pct($row['after_rate']) }}</td>
                                    <td class="py-1.5 text-right text-xs text-gray-400">{{ $diff($row['before_rate'], $row['after_rate'], true) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endforeach
    </div>
</section>
