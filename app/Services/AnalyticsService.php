<?php

namespace App\Services;

use App\Models\ChecklistOption;
use App\Models\Log;
use App\Models\LogCheckItemValue;
use App\Models\LogChecklistSelection;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * 時系列：期間内の日次3数値を日付順で返す。
     */
    public function timeSeries(User $user, ?string $from = null, ?string $to = null): Collection
    {
        return $user->logs()
            ->when($from, fn ($q, $v) => $q->whereDate('logged_on', '>=', $v))
            ->when($to, fn ($q, $v) => $q->whereDate('logged_on', '<=', $v))
            ->orderBy('logged_on')
            ->get(['logged_on', 'stress', 'stamina', 'mental_capacity']);
    }

    /**
     * ○×頻度：○（is_on=true）の回数を項目ごとに集計（多い順）。
     */
    public function checkItemFrequency(User $user, ?string $from = null, ?string $to = null): Collection
    {
        return LogCheckItemValue::query()
            ->join('logs', 'logs.id', '=', 'log_check_item_values.log_id')
            ->join('check_items', 'check_items.id', '=', 'log_check_item_values.check_item_id')
            ->where('logs.user_id', $user->id)
            ->where('log_check_item_values.is_on', true)
            ->when($from, fn ($q, $v) => $q->whereDate('logs.logged_on', '>=', $v))
            ->when($to, fn ($q, $v) => $q->whereDate('logs.logged_on', '<=', $v))
            ->groupBy('check_items.id', 'check_items.name')
            ->orderByDesc(DB::raw('count(*)'))
            ->get([
                'check_items.id',
                'check_items.name',
                DB::raw('count(*)::int as total'),
            ]);
    }

    /**
     * チェック頻度：選択回数を選択肢ごとに集計（多い順）。任意でカテゴリ絞り込み。
     */
    public function checklistFrequency(User $user, ?string $from = null, ?string $to = null, ?string $categoryCode = null): Collection
    {
        return LogChecklistSelection::query()
            ->join('logs', 'logs.id', '=', 'log_checklist_selections.log_id')
            ->join('checklist_options', 'checklist_options.id', '=', 'log_checklist_selections.checklist_option_id')
            ->join('checklist_categories', 'checklist_categories.id', '=', 'checklist_options.category_id')
            ->where('logs.user_id', $user->id)
            ->when($from, fn ($q, $v) => $q->whereDate('logs.logged_on', '>=', $v))
            ->when($to, fn ($q, $v) => $q->whereDate('logs.logged_on', '<=', $v))
            ->when($categoryCode, fn ($q, $v) => $q->where('checklist_categories.code', $v))
            ->groupBy('checklist_options.id', 'checklist_options.label', 'checklist_categories.name')
            ->orderByDesc(DB::raw('count(*)'))
            ->get([
                'checklist_options.id',
                'checklist_options.label',
                'checklist_categories.name as category_name',
                DB::raw('count(*)::int as total'),
            ]);
    }

    /**
     * 回復パターン：回復行動を取った翌日／取らなかった翌日のメンタル余裕平均を比較する。
     *
     * @return array<int, array{option_id:int, label:string, with_next_avg:?float, without_next_avg:?float, delta:?float, with_days:int}>
     */
    public function recoveryPattern(User $user, ?string $from = null, ?string $to = null): array
    {
        $logs = $user->logs()
            ->when($from, fn ($q, $v) => $q->whereDate('logged_on', '>=', $v))
            ->when($to, fn ($q, $v) => $q->whereDate('logged_on', '<=', $v))
            ->orderBy('logged_on')
            ->get(['id', 'logged_on', 'mental_capacity']);

        // 日付(Y-m-d) → メンタル余裕
        $mcByDate = $logs->mapWithKeys(fn ($l) => [$l->logged_on->format('Y-m-d') => $l->mental_capacity]);

        // log_id → 選択された回復行動 option_id の配列
        $selectionsByLog = LogChecklistSelection::whereIn('log_id', $logs->pluck('id'))
            ->get(['log_id', 'checklist_option_id'])
            ->groupBy('log_id')
            ->map(fn ($group) => $group->pluck('checklist_option_id')->all());

        $options = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
            ->orderBy('sort_order')
            ->get(['id', 'label']);

        $with = [];
        $without = [];

        foreach ($logs as $log) {
            $nextDate = $log->logged_on->copy()->addDay()->format('Y-m-d');
            if (! $mcByDate->has($nextDate)) {
                continue; // 翌日のログが無い日はスキップ
            }
            $nextMc = $mcByDate[$nextDate];
            $selected = $selectionsByLog[$log->id] ?? [];

            foreach ($options as $option) {
                if (in_array($option->id, $selected, true)) {
                    $with[$option->id][] = $nextMc;
                } else {
                    $without[$option->id][] = $nextMc;
                }
            }
        }

        $avg = fn (array $values): ?float => $values === [] ? null : round(array_sum($values) / count($values), 2);

        return $options->map(function ($option) use ($with, $without, $avg) {
            $withAvg = $avg($with[$option->id] ?? []);
            $withoutAvg = $avg($without[$option->id] ?? []);

            return [
                'option_id' => $option->id,
                'label' => $option->label,
                'with_next_avg' => $withAvg,
                'without_next_avg' => $withoutAvg,
                'delta' => ($withAvg !== null && $withoutAvg !== null) ? round($withAvg - $withoutAvg, 2) : null,
                'with_days' => count($with[$option->id] ?? []),
            ];
        })->all();
    }
}
