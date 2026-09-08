<?php

namespace App\Services;

use App\Models\ChecklistOption;
use App\Models\Log;
use App\Models\LogCheckItemValue;
use App\Models\LogChecklistSelection;
use App\Models\User;
use App\Support\DayTypes;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    /**
     * 相関を取る指標の組み合わせ。
     * 追加項目は未入力（NULL）が多いため、有効件数 n を必ず併記する。
     */
    private const CORRELATION_PAIRS = [
        ['stress', 'mental_capacity'],
        ['stamina', 'mental_capacity'],
        ['sleep_hours', 'mental_capacity'],
        ['sleep_hours', 'stamina'],
        ['sleep_quality', 'mental_capacity'],
        ['carryover', 'mental_capacity'],
        ['carryover', 'stress'],
        ['controllability', 'stress'],
        ['controllability', 'mental_capacity'],
    ];

    /**
     * 前日→翌日で見る組み合わせ。
     *
     * ストレスは日ごとに独立（r=+0.08）／メンタル余裕は持ち越す（r=+0.46）という構造は
     * 行動指針に直結するが、既存の correlations()（同日の相関）では出てこない。
     * report-202609.md §2 ④ / §5 #4。
     */
    private const AUTOCORRELATION_PAIRS = [
        ['mental_capacity', 'mental_capacity'],
        ['stress', 'stress'],
        ['stamina', 'stamina'],
        ['stress', 'mental_capacity'],
        ['carryover', 'mental_capacity'],
    ];

    /**
     * 「参考値」ではなく素直に読んでよい有効件数の下限。
     *
     * n=28 で信頼できたのは ストレス×余裕 だけで、n≤13 は全部参考値だった（§2 ⑥）。
     * この線を下回る行は画面側でバッジを付けて割り引いて読ませる。
     */
    public const RELIABLE_N = 10;

    /**
     * 個数集計の対象カテゴリ code。
     */
    private const THOUGHT_HABIT_CODE = 'thought_habit';

    /**
     * 未記録が何日続いたら促すか（ダッシュボードのバナー）。
     */
    public const NUDGE_GAP_DAYS = 3;

    /**
     * 指標の表示名。
     */
    public const METRIC_LABELS = [
        'stress' => 'ストレス',
        'stamina' => '体力',
        'mental_capacity' => 'メンタル余裕',
        'sleep_hours' => '睡眠時間',
        'sleep_quality' => '睡眠の質',
        'carryover' => '持ち越し感',
        'controllability' => 'コントロール可能度',
    ];

    /**
     * 時系列：期間内の日次数値を日付順で返す。
     */
    public function timeSeries(User $user, ?string $from = null, ?string $to = null): Collection
    {
        return $user->logs()
            ->when($from, fn ($q, $v) => $q->whereDate('logged_on', '>=', $v))
            ->when($to, fn ($q, $v) => $q->whereDate('logged_on', '<=', $v))
            ->orderBy('logged_on')
            ->get([
                'logged_on',
                'stress',
                'stamina',
                'mental_capacity',
                'sleep_hours',
                'sleep_quality',
                'carryover',
                'controllability',
            ]);
    }

    /**
     * 相関：主要指標間のピアソン相関係数と有効件数 n。
     *
     * n は「両方の項目が入力されている日数」。corr() は n<2 で NULL を返すため、
     * r が NULL でも n を見れば「データ不足」と「相関なし」を区別できる。
     *
     * @return array<int, array{key:string, x:string, y:string, x_label:string, y_label:string, r:?float, n:int}>
     */
    public function correlations(User $user, ?string $from = null, ?string $to = null): array
    {
        $selects = [];
        foreach (self::CORRELATION_PAIRS as [$x, $y]) {
            $selects[] = DB::raw("corr({$x}::float, {$y}::float) as r_{$x}_{$y}");
            $selects[] = DB::raw("count(*) filter (where {$x} is not null and {$y} is not null)::int as n_{$x}_{$y}");
        }

        $row = $user->logs()
            ->when($from, fn ($q, $v) => $q->whereDate('logged_on', '>=', $v))
            ->when($to, fn ($q, $v) => $q->whereDate('logged_on', '<=', $v))
            ->select($selects)
            ->first();

        return collect(self::CORRELATION_PAIRS)->map(function (array $pair) use ($row) {
            [$x, $y] = $pair;
            $r = $row?->{"r_{$x}_{$y}"};
            $n = (int) ($row?->{"n_{$x}_{$y}"} ?? 0);

            return [
                'key' => "{$x}_{$y}",
                'x' => $x,
                'y' => $y,
                'x_label' => self::METRIC_LABELS[$x],
                'y_label' => self::METRIC_LABELS[$y],
                'r' => $r === null ? null : round((float) $r, 3),
                'n' => $n,
                'reliable' => $n >= self::RELIABLE_N,
            ];
        })
            // n が少ない参考値が上に並ぶとミスリードになる（§5 #5）。
            // n の多い順、同じなら |r| の大きい順。
            ->sort(fn (array $a, array $b) => [$b['n'], abs($b['r'] ?? 0)] <=> [$a['n'], abs($a['r'] ?? 0)])
            ->values()
            ->all();
    }

    /**
     * 自己相関：前日の指標と翌日の指標の相関係数と有効件数 n。
     *
     * 連続していない日（翌日のログが無い日）はペアに含めない。
     *
     * @return array<int, array{key:string, x:string, y:string, x_label:string, y_label:string, r:?float, n:int, reliable:bool}>
     */
    public function autocorrelations(User $user, ?string $from = null, ?string $to = null): array
    {
        $logs = $user->logs()
            ->when($from, fn ($q, $v) => $q->whereDate('logged_on', '>=', $v))
            ->when($to, fn ($q, $v) => $q->whereDate('logged_on', '<=', $v))
            ->orderBy('logged_on')
            ->get(['logged_on', 'stress', 'stamina', 'mental_capacity', 'carryover']);

        $byDate = $logs->keyBy(fn ($log) => $log->logged_on->format('Y-m-d'));

        return collect(self::AUTOCORRELATION_PAIRS)->map(function (array $pair) use ($logs, $byDate) {
            [$x, $y] = $pair;

            $xs = [];
            $ys = [];

            foreach ($logs as $log) {
                $next = $byDate->get($log->logged_on->copy()->addDay()->format('Y-m-d'));

                // 翌日のログが無い日／どちらかが未入力の日はペアにしない
                if ($next === null || $log->{$x} === null || $next->{$y} === null) {
                    continue;
                }

                $xs[] = (float) $log->{$x};
                $ys[] = (float) $next->{$y};
            }

            $n = count($xs);

            return [
                'key' => "{$x}_next_{$y}",
                'x' => $x,
                'y' => $y,
                'x_label' => self::METRIC_LABELS[$x],
                'y_label' => self::METRIC_LABELS[$y],
                'r' => $this->pearson($xs, $ys),
                'n' => $n,
                'reliable' => $n >= self::RELIABLE_N,
            ];
        })->all();
    }

    /**
     * ストレス源の重なり：同日に○がついた件数ごとの日数と平均スコア（件数の昇順）。
     *
     * 単純頻度（checkItemFrequency）では「仕事が75%で常時ON」までしか分からず、
     * 1件→2件でメンタル余裕が −1.93 落ちる閾値構造は見えない（§2 ① / §5 #1）。
     * ○が0件の日を落とさないため、logs を起点に数える。
     *
     * @return array<int, array{count:int, days:int, avg_stress:?float, avg_mental_capacity:?float}>
     */
    public function stressSourceOverlap(User $user, ?string $from = null, ?string $to = null): array
    {
        return $this->groupByDailyCount(
            $this->dailyCountQuery($user, $from, $to, <<<'SQL'
                (select count(*)
                   from log_check_item_values v
                  where v.log_id = logs.id
                    and v.is_on = true)::int as cnt
            SQL),
        );
    }

    /**
     * 頭の中のクセの個数：同日に選んだ個数ごとの日数と平均スコア（個数の昇順）。
     *
     * ほぼ単調にメンタル余裕が落ちるため、余裕の代替指標として使える（§2 ② / §5 #2）。
     * 「特になし」は 0個として数える。
     *
     * @return array<int, array{count:int, days:int, avg_stress:?float, avg_mental_capacity:?float}>
     */
    public function thoughtHabitCount(User $user, ?string $from = null, ?string $to = null): array
    {
        $code = self::THOUGHT_HABIT_CODE;

        return $this->groupByDailyCount(
            $this->dailyCountQuery($user, $from, $to, <<<SQL
                (select count(*)
                   from log_checklist_selections s
                   join checklist_options o on o.id = s.checklist_option_id
                   join checklist_categories c on c.id = o.category_id
                  where s.log_id = logs.id
                    and c.code = '{$code}'
                    and o.is_none = false)::int as cnt
            SQL),
        );
    }

    /**
     * 入力のタイミング：対象日に対していつ書いたかの内訳と平均入力時刻。
     *
     * 入力が夕方以降に寄っていると夜のイベントが翌日ログに混ざる（§3）。
     * created_at はアプリのタイムゾーン基準なので、avg_hour には timezone を併記する。
     *
     * @return array{total:int, same_day:int, next_day:int, later:int, avg_hour:?float, timezone:string}
     */
    public function inputLag(User $user, ?string $from = null, ?string $to = null): array
    {
        $row = $user->logs()
            ->when($from, fn ($q, $v) => $q->whereDate('logged_on', '>=', $v))
            ->when($to, fn ($q, $v) => $q->whereDate('logged_on', '<=', $v))
            ->select([
                DB::raw('count(*)::int as total'),
                DB::raw('count(*) filter (where created_at::date - logged_on = 0)::int as same_day'),
                DB::raw('count(*) filter (where created_at::date - logged_on = 1)::int as next_day'),
                // NULL の created_at も later に寄せて、3区分の合計が必ず total と一致するようにする
                DB::raw('count(*) filter (where created_at is null or created_at::date - logged_on not in (0, 1))::int as later'),
                DB::raw('avg(extract(hour from created_at) + extract(minute from created_at) / 60.0)::float as avg_hour'),
            ])
            ->first();

        return [
            'total' => (int) ($row?->total ?? 0),
            'same_day' => (int) ($row?->same_day ?? 0),
            'next_day' => (int) ($row?->next_day ?? 0),
            'later' => (int) ($row?->later ?? 0),
            'avg_hour' => $row?->avg_hour === null ? null : round((float) $row->avg_hour, 2),
            'timezone' => (string) config('app.timezone'),
        ];
    }

    /**
     * 日次の「該当件数」を持つクエリ。groupByDailyCount() に渡す。
     */
    private function dailyCountQuery(User $user, ?string $from, ?string $to, string $countExpression): Builder
    {
        return Log::query()
            ->where('user_id', $user->id)
            ->when($from, fn ($q, $v) => $q->whereDate('logged_on', '>=', $v))
            ->when($to, fn ($q, $v) => $q->whereDate('logged_on', '<=', $v))
            ->select([
                'logs.stress',
                'logs.mental_capacity',
                DB::raw($countExpression),
            ]);
    }

    /**
     * 件数ごとに日数と平均スコアを集計する（件数の昇順）。
     *
     * @return array<int, array{count:int, days:int, avg_stress:?float, avg_mental_capacity:?float}>
     */
    private function groupByDailyCount(Builder $daily): array
    {
        return DB::query()
            ->fromSub($daily, 'd')
            ->groupBy('cnt')
            ->orderBy('cnt')
            ->get([
                'cnt',
                DB::raw('count(*)::int as days'),
                DB::raw('avg(stress)::float as avg_stress'),
                DB::raw('avg(mental_capacity)::float as avg_mental_capacity'),
            ])
            ->map(fn ($row) => [
                'count' => (int) $row->cnt,
                'days' => (int) $row->days,
                'avg_stress' => $this->round($row->avg_stress),
                'avg_mental_capacity' => $this->round($row->avg_mental_capacity),
            ])
            ->all();
    }

    /**
     * ピアソン相関係数。n<2 と分散ゼロでは定義できないため NULL を返す。
     *
     * @param  list<float>  $xs
     * @param  list<float>  $ys
     */
    private function pearson(array $xs, array $ys): ?float
    {
        $n = count($xs);
        if ($n < 2) {
            return null;
        }

        $meanX = array_sum($xs) / $n;
        $meanY = array_sum($ys) / $n;

        $covariance = 0.0;
        $varianceX = 0.0;
        $varianceY = 0.0;

        for ($i = 0; $i < $n; $i++) {
            $dx = $xs[$i] - $meanX;
            $dy = $ys[$i] - $meanY;
            $covariance += $dx * $dy;
            $varianceX += $dx ** 2;
            $varianceY += $dy ** 2;
        }

        if ($varianceX === 0.0 || $varianceY === 0.0) {
            return null;
        }

        return round($covariance / sqrt($varianceX * $varianceY), 3);
    }

    /**
     * 勤務形態別：day_type ごとの各スコア平均。
     *
     * days は該当日数、*_days は各項目が入力済みの日数。
     * NULL は平均から除外するため両者は一致しないことがある。
     * day_type が NULL のログは集計対象外。
     *
     * @return array<int, array<string, mixed>>
     */
    public function dayTypeBreakdown(User $user, ?string $from = null, ?string $to = null): array
    {
        return $user->logs()
            ->whereNotNull('day_type')
            ->when($from, fn ($q, $v) => $q->whereDate('logged_on', '>=', $v))
            ->when($to, fn ($q, $v) => $q->whereDate('logged_on', '<=', $v))
            ->groupBy('day_type')
            ->orderByDesc(DB::raw('count(*)'))
            ->get([
                'day_type',
                DB::raw('count(*)::int as days'),
                DB::raw('avg(stress)::float as avg_stress'),
                DB::raw('avg(stamina)::float as avg_stamina'),
                DB::raw('avg(mental_capacity)::float as avg_mental_capacity'),
                DB::raw('avg(sleep_hours)::float as avg_sleep_hours'),
                DB::raw('count(sleep_hours)::int as sleep_days'),
                DB::raw('avg(carryover)::float as avg_carryover'),
                DB::raw('count(carryover)::int as carryover_days'),
                DB::raw('avg(controllability)::float as avg_controllability'),
                DB::raw('count(controllability)::int as controllability_days'),
            ])
            ->map(fn ($row) => [
                'day_type' => $row->day_type,
                'label' => DayTypes::label($row->day_type) ?? $row->day_type,
                'days' => $row->days,
                'avg_stress' => $this->round($row->avg_stress),
                'avg_stamina' => $this->round($row->avg_stamina),
                'avg_mental_capacity' => $this->round($row->avg_mental_capacity),
                'avg_sleep_hours' => $this->round($row->avg_sleep_hours),
                'sleep_days' => $row->sleep_days,
                'avg_carryover' => $this->round($row->avg_carryover),
                'carryover_days' => $row->carryover_days,
                'avg_controllability' => $this->round($row->avg_controllability),
                'controllability_days' => $row->controllability_days,
            ])
            ->all();
    }

    private function round(mixed $value): ?float
    {
        return $value === null ? null : round((float) $value, 2);
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
     * 記録率：期間の記録率・最長／直近の未記録連続日数・未記録日一覧・欠測バイアス。
     *
     * 欠測そのものを情報として扱う（入力項目は増やさない）。
     *
     * bias は「翌日も記録した日」と「翌日が欠測だった日」のスコア平均。
     * しんどい日の翌日に記録が飛んでいるため（§2 ⑨）、平均値をどれだけ割り引いて
     * 読むべきかがこの2つの差で分かる。
     *
     * @return array{total_days:int, logged_days:int, rate:float, longest_gap:int, current_gap:int, missing_dates:list<string>, logged_dates:list<string>, bias:array{next_logged:array{days:int, avg_stress:?float, avg_mental_capacity:?float}, next_missing:array{days:int, avg_stress:?float, avg_mental_capacity:?float}}}
     */
    public function coverage(User $user, string $from, string $to): array
    {
        $logs = $user->logs()
            ->whereDate('logged_on', '>=', $from)
            ->whereDate('logged_on', '<=', $to)
            ->orderBy('logged_on')
            ->get(['logged_on', 'stress', 'mental_capacity']);

        $logged = [];
        foreach ($logs as $log) {
            $logged[$log->logged_on->format('Y-m-d')] = $log;
        }

        $missing = [];
        $longestGap = 0;
        $currentGap = 0;
        $totalDays = 0;

        foreach (CarbonPeriod::create($from, $to) as $date) {
            $key = $date->format('Y-m-d');
            $totalDays++;

            if (isset($logged[$key])) {
                $currentGap = 0;

                continue;
            }

            $missing[] = $key;
            $currentGap++;
            $longestGap = max($longestGap, $currentGap);
        }

        $loggedDays = count($logged);

        return [
            'total_days' => $totalDays,
            'logged_days' => $loggedDays,
            'rate' => $totalDays === 0 ? 0.0 : round($loggedDays / $totalDays, 2),
            'longest_gap' => $longestGap,
            // ループ終端の値がそのまま「期間末までの連続未記録日数」になる
            'current_gap' => $currentGap,
            'missing_dates' => $missing,
            'logged_dates' => array_keys($logged),
            'bias' => $this->missingBias($logs, $logged, $to),
        ];
    }

    /**
     * 欠測バイアス：翌日も記録した日／翌日が欠測だった日のスコア平均。
     *
     * 翌日が期間外になる最終日は判定できないため対象外。
     *
     * @param  Collection<int, Log>  $logs
     * @param  array<string, Log>  $logged
     * @return array{next_logged:array{days:int, avg_stress:?float, avg_mental_capacity:?float}, next_missing:array{days:int, avg_stress:?float, avg_mental_capacity:?float}}
     */
    private function missingBias(Collection $logs, array $logged, string $to): array
    {
        $nextLogged = [];
        $nextMissing = [];

        foreach ($logs as $log) {
            $nextDate = $log->logged_on->copy()->addDay()->format('Y-m-d');

            if ($nextDate > $to) {
                continue; // 翌日が期間外の日は判定できない
            }

            if (isset($logged[$nextDate])) {
                $nextLogged[] = $log;
            } else {
                $nextMissing[] = $log;
            }
        }

        $summary = fn (array $rows): array => [
            'days' => count($rows),
            'avg_stress' => $this->round(collect($rows)->avg('stress')),
            'avg_mental_capacity' => $this->round(collect($rows)->avg('mental_capacity')),
        ];

        return [
            'next_logged' => $summary($nextLogged),
            'next_missing' => $summary($nextMissing),
        ];
    }

    /**
     * 相手タグ頻度：ログに登場した回数を相手ごとに集計（多い順）。
     */
    public function personFrequency(User $user, ?string $from = null, ?string $to = null): Collection
    {
        return DB::table('log_people')
            ->join('logs', 'logs.id', '=', 'log_people.log_id')
            ->join('people', 'people.id', '=', 'log_people.person_id')
            ->where('logs.user_id', $user->id)
            ->when($from, fn ($q, $v) => $q->whereDate('logs.logged_on', '>=', $v))
            ->when($to, fn ($q, $v) => $q->whereDate('logs.logged_on', '<=', $v))
            ->groupBy('people.id', 'people.name')
            ->orderByDesc(DB::raw('count(*)'))
            ->get([
                'people.id',
                'people.name',
                DB::raw('count(*)::int as total'),
            ]);
    }

    /**
     * 回復効果：回復行動ごとの平均効果・平均所要時間・実施日数を返す（効果の高い順）。
     *
     * days は実施した日数、effect_days は「効いた感」を答えた日数。
     * 未入力（NULL）は平均から除外するため、両者は一致しないことがある。
     *
     * @return array<int, array{option_id:int, label:string, days:int, effect_days:int, avg_effect:?float, avg_duration:?float}>
     */
    public function recoveryEffect(User $user, ?string $from = null, ?string $to = null): array
    {
        return LogChecklistSelection::query()
            ->join('logs', 'logs.id', '=', 'log_checklist_selections.log_id')
            ->join('checklist_options', 'checklist_options.id', '=', 'log_checklist_selections.checklist_option_id')
            ->join('checklist_categories', 'checklist_categories.id', '=', 'checklist_options.category_id')
            ->where('logs.user_id', $user->id)
            ->where('checklist_categories.tracks_effect', true)
            // 「何もできてない」は効果を聞いていないので集計対象から外す
            ->where('checklist_options.is_none', false)
            ->when($from, fn ($q, $v) => $q->whereDate('logs.logged_on', '>=', $v))
            ->when($to, fn ($q, $v) => $q->whereDate('logs.logged_on', '<=', $v))
            ->groupBy('checklist_options.id', 'checklist_options.label')
            ->orderByDesc(DB::raw('avg(log_checklist_selections.effect_score)'))
            ->get([
                'checklist_options.id as option_id',
                'checklist_options.label',
                DB::raw('count(*)::int as days'),
                DB::raw('count(log_checklist_selections.effect_score)::int as effect_days'),
                DB::raw('avg(log_checklist_selections.effect_score)::float as avg_effect'),
                DB::raw('avg(log_checklist_selections.duration_min)::float as avg_duration'),
            ])
            ->map(fn ($row) => [
                'option_id' => (int) $row->option_id,
                'label' => $row->label,
                'days' => $row->days,
                'effect_days' => $row->effect_days,
                'avg_effect' => $row->avg_effect === null ? null : round($row->avg_effect, 2),
                'avg_duration' => $row->avg_duration === null ? null : round($row->avg_duration, 2),
            ])
            ->all();
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
