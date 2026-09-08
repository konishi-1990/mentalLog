<?php

use App\Models\ChecklistOption;
use App\Models\Log;
use App\Models\LogCheckItemValue;
use App\Models\Person;
use App\Models\User;
use App\Services\AnalyticsService;
use Database\Seeders\ChecklistCategorySeeder;
use Database\Seeders\ChecklistOptionSeeder;
use Illuminate\Database\Eloquent\Factories\Sequence;

beforeEach(function () {
    $this->seed([ChecklistCategorySeeder::class, ChecklistOptionSeeder::class]);
    $this->service = app(AnalyticsService::class);
});

it('時系列：期間内の日次3数値を日付順で返す', function () {
    $user = User::factory()->create();
    Log::factory()->for($user)->create(['logged_on' => '2026-07-03', 'stress' => 3]);
    Log::factory()->for($user)->create(['logged_on' => '2026-07-01', 'stress' => 8]);
    Log::factory()->for($user)->create(['logged_on' => '2026-06-01', 'stress' => 1]); // 範囲外

    $series = $this->service->timeSeries($user, '2026-07-01', '2026-07-31');

    expect($series)->toHaveCount(2)
        ->and($series->first()->logged_on->format('Y-m-d'))->toBe('2026-07-01')
        ->and($series->first()->stress)->toBe(8)
        ->and($series->last()->logged_on->format('Y-m-d'))->toBe('2026-07-03');
});

it('○×頻度：○になった回数を項目ごとに集計する', function () {
    $user = User::factory()->create();
    $items = $user->checkItems()->orderBy('sort_order')->get();
    $itemA = $items[0];
    $itemB = $items[1];

    foreach (['2026-07-01', '2026-07-02'] as $d) {
        $log = Log::factory()->for($user)->create(['logged_on' => $d]);
        LogCheckItemValue::create(['log_id' => $log->id, 'check_item_id' => $itemA->id, 'is_on' => true]);
    }
    $log = Log::factory()->for($user)->create(['logged_on' => '2026-07-03']);
    LogCheckItemValue::create(['log_id' => $log->id, 'check_item_id' => $itemB->id, 'is_on' => true]);
    LogCheckItemValue::create(['log_id' => $log->id, 'check_item_id' => $itemA->id, 'is_on' => false]); // ×は数えない

    $freq = $this->service->checkItemFrequency($user);

    expect($freq->firstWhere('id', $itemA->id)->total)->toBe(2)
        ->and($freq->firstWhere('id', $itemB->id)->total)->toBe(1);
});

it('チェック頻度：選択された回数を選択肢ごとに集計する', function () {
    $user = User::factory()->create();
    $irritation = ChecklistOption::whereRelation('category', 'code', 'body_reaction')
        ->where('label', 'イライラ')->first();

    foreach (['2026-07-01', '2026-07-02'] as $d) {
        $log = Log::factory()->for($user)->create(['logged_on' => $d]);
        $log->checklistSelections()->create(['checklist_option_id' => $irritation->id]);
    }

    $freq = $this->service->checklistFrequency($user);

    expect($freq->firstWhere('id', $irritation->id)->total)->toBe(2);
});

it('回復パターン：回復行動を取った翌日のメンタル余裕平均を算出する', function () {
    $user = User::factory()->create();
    $sauna = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '温泉・サウナ')->first();

    // 07-01 サウナ → 翌日07-02 の余裕=8（with）
    $d1 = Log::factory()->for($user)->create(['logged_on' => '2026-07-01', 'mental_capacity' => 5]);
    $d1->checklistSelections()->create(['checklist_option_id' => $sauna->id]);
    Log::factory()->for($user)->create(['logged_on' => '2026-07-02', 'mental_capacity' => 8]);

    // 07-10 サウナなし → 翌日07-11 の余裕=4（without）
    Log::factory()->for($user)->create(['logged_on' => '2026-07-10', 'mental_capacity' => 5]);
    Log::factory()->for($user)->create(['logged_on' => '2026-07-11', 'mental_capacity' => 4]);

    $patterns = $this->service->recoveryPattern($user);
    $saunaPattern = collect($patterns)->firstWhere('option_id', $sauna->id);

    expect($saunaPattern['with_next_avg'])->toBe(8.0)
        ->and($saunaPattern['without_next_avg'])->toBe(4.0)
        ->and($saunaPattern['delta'])->toBe(4.0);
});

it('チェック頻度：intake カテゴリで絞り込むと摂取物のみ集計する', function () {
    $user = User::factory()->create();
    $log = Log::factory()->for($user)->create(['logged_on' => '2026-07-01']);

    $intake = ChecklistOption::whereRelation('category', 'code', 'intake')
        ->where('label', 'アルコール')->firstOrFail();
    $recovery = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '温泉・サウナ')->firstOrFail();

    $log->checklistSelections()->create(['checklist_option_id' => $intake->id]);
    $log->checklistSelections()->create(['checklist_option_id' => $recovery->id]);

    $result = $this->service->checklistFrequency($user, null, null, 'intake');

    expect($result)->toHaveCount(1)
        ->and($result->first()->label)->toBe('アルコール')
        ->and($result->first()->total)->toBe(1);
});

it('回復効果：行動ごとの平均効果・平均時間・実施日数を返す', function () {
    $user = User::factory()->create();
    $onsen = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '温泉・サウナ')->firstOrFail();
    $alone = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '一人時間')->firstOrFail();

    $a = Log::factory()->for($user)->create(['logged_on' => '2026-07-01']);
    $b = Log::factory()->for($user)->create(['logged_on' => '2026-07-02']);

    $a->checklistSelections()->create([
        'checklist_option_id' => $onsen->id, 'duration_min' => 60, 'effect_score' => 8,
    ]);
    $b->checklistSelections()->create([
        'checklist_option_id' => $onsen->id, 'duration_min' => 120, 'effect_score' => 6,
    ]);
    $b->checklistSelections()->create([
        'checklist_option_id' => $alone->id, 'duration_min' => 30, 'effect_score' => 2,
    ]);

    $result = collect($this->service->recoveryEffect($user))->keyBy('label');

    expect($result['温泉・サウナ']['avg_effect'])->toBe(7.0)
        ->and($result['温泉・サウナ']['avg_duration'])->toBe(90.0)
        ->and($result['温泉・サウナ']['days'])->toBe(2)
        ->and($result['一人時間']['avg_effect'])->toBe(2.0)
        ->and($result['一人時間']['days'])->toBe(1);
});

it('回復効果：effect_score が未入力の選択は平均から除外される', function () {
    $user = User::factory()->create();
    $onsen = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '温泉・サウナ')->firstOrFail();

    $a = Log::factory()->for($user)->create(['logged_on' => '2026-07-01']);
    $b = Log::factory()->for($user)->create(['logged_on' => '2026-07-02']);

    $a->checklistSelections()->create(['checklist_option_id' => $onsen->id, 'effect_score' => 8]);
    $b->checklistSelections()->create(['checklist_option_id' => $onsen->id, 'effect_score' => null]);

    $result = collect($this->service->recoveryEffect($user))->keyBy('label');

    // 実施は2日、効果を答えたのは1日
    expect($result['温泉・サウナ']['days'])->toBe(2)
        ->and($result['温泉・サウナ']['effect_days'])->toBe(1)
        ->and($result['温泉・サウナ']['avg_effect'])->toBe(8.0);
});

it('回復効果：実施が1件もなければ空を返す', function () {
    $user = User::factory()->create();

    expect($this->service->recoveryEffect($user))->toBe([]);
});

it('相手タグ頻度：出現回数の多い順で返す', function () {
    $user = User::factory()->create();
    $boss = Person::factory()->for($user)->create(['name' => '上層部']);
    $member = Person::factory()->for($user)->create(['name' => 'バンドメンバー']);

    $a = Log::factory()->for($user)->create(['logged_on' => '2026-07-01']);
    $b = Log::factory()->for($user)->create(['logged_on' => '2026-07-02']);
    $c = Log::factory()->for($user)->create(['logged_on' => '2026-07-03']);

    $a->people()->attach($boss->id);
    $b->people()->attach($boss->id);
    $c->people()->attach([$boss->id, $member->id]);

    $result = $this->service->personFrequency($user);

    expect($result)->toHaveCount(2)
        ->and($result->first()->name)->toBe('上層部')
        ->and($result->first()->total)->toBe(3)
        ->and($result->last()->name)->toBe('バンドメンバー')
        ->and($result->last()->total)->toBe(1);
});

it('相手タグ頻度：期間で絞り込める', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user)->create(['name' => '上層部']);

    Log::factory()->for($user)->create(['logged_on' => '2026-06-01'])->people()->attach($person->id);
    Log::factory()->for($user)->create(['logged_on' => '2026-07-01'])->people()->attach($person->id);

    $result = $this->service->personFrequency($user, '2026-07-01', '2026-07-31');

    expect($result->first()->total)->toBe(1);
});

it('相手タグ頻度：紐づけが無ければ空を返す', function () {
    $user = User::factory()->create();

    expect($this->service->personFrequency($user))->toHaveCount(0);
});

it('相手タグ頻度：他人のログは含まない', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherPerson = Person::factory()->for($other)->create();

    Log::factory()->for($other)->create(['logged_on' => '2026-07-01'])->people()->attach($otherPerson->id);

    expect($this->service->personFrequency($user))->toHaveCount(0);
});

it('時系列：追加項目も列に含める', function () {
    $user = User::factory()->create();
    Log::factory()->for($user)->create([
        'logged_on' => '2026-07-01',
        'sleep_hours' => 6.5,
        'sleep_quality' => 4,
        'carryover' => 8,
        'controllability' => 2,
    ]);

    $row = $this->service->timeSeries($user)->first();

    expect((float) $row->sleep_hours)->toBe(6.5)
        ->and($row->sleep_quality)->toBe(4)
        ->and($row->carryover)->toBe(8)
        ->and($row->controllability)->toBe(2);
});

it('相関：相関値と有効件数 n を返す', function () {
    $user = User::factory()->create();
    // ストレスと余裕を完全な負の相関にする
    foreach ([[0, 10], [2, 8], [4, 6], [6, 4], [8, 2], [10, 0]] as $i => [$stress, $mental]) {
        Log::factory()->for($user)->create([
            'logged_on' => '2026-07-0'.($i + 1),
            'stress' => $stress,
            'mental_capacity' => $mental,
        ]);
    }

    $result = collect($this->service->correlations($user))->keyBy('key');
    $row = $result['stress_mental_capacity'];

    expect($row['r'])->toBe(-1.0)
        ->and($row['n'])->toBe(6);
});

it('相関：有効データが1件以下なら r は NULL・n は件数を返す', function () {
    $user = User::factory()->create();
    Log::factory()->for($user)->create([
        'logged_on' => '2026-07-01',
        'sleep_hours' => 7.0,
        'mental_capacity' => 5,
    ]);

    $result = collect($this->service->correlations($user))->keyBy('key');
    $row = $result['sleep_hours_mental_capacity'];

    expect($row['r'])->toBeNull()
        ->and($row['n'])->toBe(1);
});

it('相関：追加項目が全件 NULL でも落ちず n=0 を返す', function () {
    $user = User::factory()->create();
    Log::factory()->for($user)->count(3)->state(new Sequence(
        ['logged_on' => '2026-07-01'],
        ['logged_on' => '2026-07-02'],
        ['logged_on' => '2026-07-03'],
    ))->create(['sleep_hours' => null, 'carryover' => null, 'controllability' => null, 'sleep_quality' => null]);

    $result = collect($this->service->correlations($user))->keyBy('key');

    expect($result['sleep_hours_mental_capacity']['r'])->toBeNull()
        ->and($result['sleep_hours_mental_capacity']['n'])->toBe(0);
});

it('相関：ログが1件も無くても落ちない', function () {
    $user = User::factory()->create();

    $result = collect($this->service->correlations($user));

    expect($result)->not->toBeEmpty()
        ->and($result->every(fn ($row) => $row['r'] === null && $row['n'] === 0))->toBeTrue();
});

it('勤務形態別：day_type ごとの平均を返す', function () {
    $user = User::factory()->create();
    Log::factory()->for($user)->create([
        'logged_on' => '2026-07-01', 'day_type' => 'weekday', 'stress' => 8, 'mental_capacity' => 2,
    ]);
    Log::factory()->for($user)->create([
        'logged_on' => '2026-07-02', 'day_type' => 'weekday', 'stress' => 6, 'mental_capacity' => 4,
    ]);
    Log::factory()->for($user)->create([
        'logged_on' => '2026-07-03', 'day_type' => 'paid_leave', 'stress' => 2, 'mental_capacity' => 9,
    ]);

    $result = collect($this->service->dayTypeBreakdown($user))->keyBy('day_type');

    expect($result['weekday']['days'])->toBe(2)
        ->and($result['weekday']['avg_stress'])->toBe(7.0)
        ->and($result['weekday']['avg_mental_capacity'])->toBe(3.0)
        ->and($result['paid_leave']['days'])->toBe(1)
        ->and($result['paid_leave']['avg_stress'])->toBe(2.0)
        ->and($result['weekday']['label'])->toBe('平日');
});

it('勤務形態別：day_type が NULL のログは集計対象外', function () {
    $user = User::factory()->create();
    Log::factory()->for($user)->create(['logged_on' => '2026-07-01', 'day_type' => null]);

    expect($this->service->dayTypeBreakdown($user))->toBe([]);
});

it('勤務形態別：NULL 混在でも平均が壊れない（NULL を除外して集計）', function () {
    $user = User::factory()->create();
    Log::factory()->for($user)->create([
        'logged_on' => '2026-07-01', 'day_type' => 'weekday', 'sleep_hours' => 6.0,
    ]);
    Log::factory()->for($user)->create([
        'logged_on' => '2026-07-02', 'day_type' => 'weekday', 'sleep_hours' => null,
    ]);

    $result = collect($this->service->dayTypeBreakdown($user))->keyBy('day_type');

    expect($result['weekday']['days'])->toBe(2)
        ->and($result['weekday']['sleep_days'])->toBe(1)
        ->and($result['weekday']['avg_sleep_hours'])->toBe(6.0);
});

it('記録率：13/36 日なら 0.36 を返す', function () {
    $user = User::factory()->create();
    // 2026-07-06 〜 2026-08-10 の36日間のうち13日を記録
    $dates = ['2026-07-06', '2026-07-07', '2026-07-08', '2026-07-13', '2026-07-15',
        '2026-07-17', '2026-07-18', '2026-07-19', '2026-07-29', '2026-07-31',
        '2026-08-01', '2026-08-02', '2026-08-10'];
    foreach ($dates as $date) {
        Log::factory()->for($user)->create(['logged_on' => $date]);
    }

    $result = $this->service->coverage($user, '2026-07-06', '2026-08-10');

    expect($result['total_days'])->toBe(36)
        ->and($result['logged_days'])->toBe(13)
        ->and($result['rate'])->toBe(0.36);
});

it('記録率：最長未記録連続日数を返す', function () {
    $user = User::factory()->create();
    // 7/19 と 7/29 に記録 → 間の 7/20〜7/28 が9日連続で未記録
    Log::factory()->for($user)->create(['logged_on' => '2026-07-19']);
    Log::factory()->for($user)->create(['logged_on' => '2026-07-29']);

    $result = $this->service->coverage($user, '2026-07-19', '2026-07-29');

    expect($result['longest_gap'])->toBe(9);
});

it('記録率：未記録日の一覧を返す', function () {
    $user = User::factory()->create();
    Log::factory()->for($user)->create(['logged_on' => '2026-07-01']);
    Log::factory()->for($user)->create(['logged_on' => '2026-07-03']);

    $result = $this->service->coverage($user, '2026-07-01', '2026-07-03');

    expect($result['missing_dates'])->toBe(['2026-07-02']);
});

it('記録率：期間内に1件もログが無くても落ちない', function () {
    $user = User::factory()->create();

    $result = $this->service->coverage($user, '2026-07-01', '2026-07-10');

    expect($result['total_days'])->toBe(10)
        ->and($result['logged_days'])->toBe(0)
        ->and($result['rate'])->toBe(0.0)
        ->and($result['longest_gap'])->toBe(10)
        ->and($result['missing_dates'])->toHaveCount(10);
});

it('記録率：全日記録されていれば rate=1.0・gap=0', function () {
    $user = User::factory()->create();
    foreach (['2026-07-01', '2026-07-02', '2026-07-03'] as $date) {
        Log::factory()->for($user)->create(['logged_on' => $date]);
    }

    $result = $this->service->coverage($user, '2026-07-01', '2026-07-03');

    expect($result['rate'])->toBe(1.0)
        ->and($result['longest_gap'])->toBe(0)
        ->and($result['missing_dates'])->toBe([]);
});

it('記録率：他人のログは数えない', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Log::factory()->for($other)->create(['logged_on' => '2026-07-01']);

    $result = $this->service->coverage($user, '2026-07-01', '2026-07-01');

    expect($result['logged_days'])->toBe(0);
});

it('回復効果：is_none の選択肢（何もできてない）は集計対象外', function () {
    $user = User::factory()->create();
    $onsen = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '温泉・サウナ')->firstOrFail();
    $nothing = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('is_none', true)->firstOrFail();

    $a = Log::factory()->for($user)->create(['logged_on' => '2026-07-01']);
    $b = Log::factory()->for($user)->create(['logged_on' => '2026-07-02']);
    $a->checklistSelections()->create(['checklist_option_id' => $onsen->id, 'effect_score' => 8]);
    $b->checklistSelections()->create(['checklist_option_id' => $nothing->id]);

    $result = collect($this->service->recoveryEffect($user))->pluck('label');

    expect($result)->toContain('温泉・サウナ')
        ->and($result)->not->toContain($nothing->label);
});

describe('ストレス源の重なり', function () {
    it('同日の○件数ごとに日数・平均ストレス・平均余裕を返す', function () {
        $user = User::factory()->create();
        $items = $user->checkItems()->orderBy('sort_order')->get();

        // 1件の日：余裕6 / 2件の日：余裕4 と 余裕2（平均3）
        $one = Log::factory()->for($user)->create([
            'logged_on' => '2026-07-01', 'stress' => 6, 'mental_capacity' => 6,
        ]);
        LogCheckItemValue::create(['log_id' => $one->id, 'check_item_id' => $items[0]->id, 'is_on' => true]);
        LogCheckItemValue::create(['log_id' => $one->id, 'check_item_id' => $items[1]->id, 'is_on' => false]);

        foreach ([['2026-07-02', 8, 4], ['2026-07-03', 8, 2]] as [$date, $stress, $mental]) {
            $log = Log::factory()->for($user)->create([
                'logged_on' => $date, 'stress' => $stress, 'mental_capacity' => $mental,
            ]);
            LogCheckItemValue::create(['log_id' => $log->id, 'check_item_id' => $items[0]->id, 'is_on' => true]);
            LogCheckItemValue::create(['log_id' => $log->id, 'check_item_id' => $items[1]->id, 'is_on' => true]);
        }

        $result = collect($this->service->stressSourceOverlap($user))->keyBy('count');

        expect($result[1]['days'])->toBe(1)
            ->and($result[1]['avg_mental_capacity'])->toBe(6.0)
            ->and($result[2]['days'])->toBe(2)
            ->and($result[2]['avg_mental_capacity'])->toBe(3.0)
            ->and($result[2]['avg_stress'])->toBe(8.0);
    });

    it('○が0件の日も 0件グループとして返る', function () {
        $user = User::factory()->create();
        Log::factory()->for($user)->create([
            'logged_on' => '2026-07-01', 'stress' => 5, 'mental_capacity' => 8,
        ]);

        $result = collect($this->service->stressSourceOverlap($user))->keyBy('count');

        expect($result)->toHaveKey(0)
            ->and($result[0]['days'])->toBe(1)
            ->and($result[0]['avg_mental_capacity'])->toBe(8.0);
    });

    it('件数の昇順で返る', function () {
        $user = User::factory()->create();
        $item = $user->checkItems()->first();
        Log::factory()->for($user)->create(['logged_on' => '2026-07-01']);
        $log = Log::factory()->for($user)->create(['logged_on' => '2026-07-02']);
        LogCheckItemValue::create(['log_id' => $log->id, 'check_item_id' => $item->id, 'is_on' => true]);

        expect(collect($this->service->stressSourceOverlap($user))->pluck('count')->all())->toBe([0, 1]);
    });

    it('ログが1件も無ければ空を返す', function () {
        expect($this->service->stressSourceOverlap(User::factory()->create()))->toBe([]);
    });

    it('他人のログは含まない', function () {
        $user = User::factory()->create();
        Log::factory()->for(User::factory()->create())->create(['logged_on' => '2026-07-01']);

        expect($this->service->stressSourceOverlap($user))->toBe([]);
    });

    it('強度合計：severity ではなく件数で数える（フェーズ3までは件数のみ）', function () {
        $user = User::factory()->create();
        $item = $user->checkItems()->first();
        $log = Log::factory()->for($user)->create(['logged_on' => '2026-07-01', 'mental_capacity' => 4]);
        LogCheckItemValue::create(['log_id' => $log->id, 'check_item_id' => $item->id, 'is_on' => true]);

        $result = collect($this->service->stressSourceOverlap($user))->keyBy('count');

        expect($result[1]['days'])->toBe(1);
    });
});

describe('頭の中のクセの個数', function () {
    it('クセの個数ごとに日数・平均ストレス・平均余裕を返す', function () {
        $user = User::factory()->create();
        $habits = ChecklistOption::whereRelation('category', 'code', 'thought_habit')
            ->where('is_none', false)->orderBy('sort_order')->get();

        $two = Log::factory()->for($user)->create([
            'logged_on' => '2026-07-01', 'stress' => 8, 'mental_capacity' => 4,
        ]);
        $two->checklistSelections()->create(['checklist_option_id' => $habits[0]->id]);
        $two->checklistSelections()->create(['checklist_option_id' => $habits[1]->id]);

        $one = Log::factory()->for($user)->create([
            'logged_on' => '2026-07-02', 'stress' => 7, 'mental_capacity' => 5,
        ]);
        $one->checklistSelections()->create(['checklist_option_id' => $habits[0]->id]);

        $result = collect($this->service->thoughtHabitCount($user))->keyBy('count');

        expect($result[2]['days'])->toBe(1)
            ->and($result[2]['avg_mental_capacity'])->toBe(4.0)
            ->and($result[1]['days'])->toBe(1)
            ->and($result[1]['avg_mental_capacity'])->toBe(5.0);
    });

    it('「特になし」の選択は 0個として数える', function () {
        $user = User::factory()->create();
        $none = ChecklistOption::whereRelation('category', 'code', 'thought_habit')
            ->where('is_none', true)->firstOrFail();

        $log = Log::factory()->for($user)->create([
            'logged_on' => '2026-07-01', 'stress' => 5, 'mental_capacity' => 7,
        ]);
        $log->checklistSelections()->create(['checklist_option_id' => $none->id]);

        $result = collect($this->service->thoughtHabitCount($user))->keyBy('count');

        expect($result)->toHaveKey(0)
            ->and($result[0]['days'])->toBe(1)
            ->and($result[0]['avg_mental_capacity'])->toBe(7.0);
    });

    it('他カテゴリの選択は数えない', function () {
        $user = User::factory()->create();
        $body = ChecklistOption::whereRelation('category', 'code', 'body_reaction')
            ->where('label', 'イライラ')->firstOrFail();

        $log = Log::factory()->for($user)->create(['logged_on' => '2026-07-01']);
        $log->checklistSelections()->create(['checklist_option_id' => $body->id]);

        $result = collect($this->service->thoughtHabitCount($user))->keyBy('count');

        expect($result)->toHaveKey(0);
    });

    it('ログが1件も無ければ空を返す', function () {
        expect($this->service->thoughtHabitCount(User::factory()->create()))->toBe([]);
    });
});

describe('自己相関（前日→翌日）', function () {
    it('前日→翌日の相関値と有効件数 n を返す', function () {
        $user = User::factory()->create();
        // 余裕を 2,4,6,8,10 と単調増加させれば前日→翌日は完全な正の相関
        foreach ([2, 4, 6, 8, 10] as $i => $mental) {
            Log::factory()->for($user)->create([
                'logged_on' => '2026-07-0'.($i + 1),
                'mental_capacity' => $mental,
            ]);
        }

        $result = collect($this->service->autocorrelations($user))->keyBy('key');
        $row = $result['mental_capacity_next_mental_capacity'];

        expect($row['r'])->toBe(1.0)
            ->and($row['n'])->toBe(4);   // 5日 → 連続ペアは4組
    });

    it('連続していない日はペアに含めない', function () {
        $user = User::factory()->create();
        Log::factory()->for($user)->create(['logged_on' => '2026-07-01', 'mental_capacity' => 2]);
        Log::factory()->for($user)->create(['logged_on' => '2026-07-02', 'mental_capacity' => 4]);
        // 1日空ける
        Log::factory()->for($user)->create(['logged_on' => '2026-07-04', 'mental_capacity' => 6]);
        Log::factory()->for($user)->create(['logged_on' => '2026-07-05', 'mental_capacity' => 8]);

        $result = collect($this->service->autocorrelations($user))->keyBy('key');

        expect($result['mental_capacity_next_mental_capacity']['n'])->toBe(2);
    });

    it('NULL を含む項目はその日をペアから外す', function () {
        $user = User::factory()->create();
        Log::factory()->for($user)->create([
            'logged_on' => '2026-07-01', 'carryover' => null, 'mental_capacity' => 5,
        ]);
        Log::factory()->for($user)->create([
            'logged_on' => '2026-07-02', 'carryover' => 8, 'mental_capacity' => 3,
        ]);
        Log::factory()->for($user)->create([
            'logged_on' => '2026-07-03', 'carryover' => 2, 'mental_capacity' => 7,
        ]);

        $result = collect($this->service->autocorrelations($user))->keyBy('key');

        // 7/02→7/03 の1組だけ成立（7/01 は carryover が NULL）
        expect($result['carryover_next_mental_capacity']['n'])->toBe(1)
            ->and($result['carryover_next_mental_capacity']['r'])->toBeNull();
    });

    it('ログが0件・1件でも落ちず r=NULL を返す', function (int $count) {
        $user = User::factory()->create();
        for ($i = 0; $i < $count; $i++) {
            Log::factory()->for($user)->create(['logged_on' => '2026-07-0'.($i + 1)]);
        }

        $result = collect($this->service->autocorrelations($user));

        expect($result)->not->toBeEmpty()
            ->and($result->every(fn ($row) => $row['r'] === null))->toBeTrue();
    })->with([0, 1]);

    it('分散がゼロ（全日同じ値）なら r は NULL', function () {
        $user = User::factory()->create();
        foreach (range(1, 4) as $i) {
            Log::factory()->for($user)->create([
                'logged_on' => '2026-07-0'.$i, 'mental_capacity' => 5,
            ]);
        }

        $result = collect($this->service->autocorrelations($user))->keyBy('key');

        expect($result['mental_capacity_next_mental_capacity']['r'])->toBeNull()
            ->and($result['mental_capacity_next_mental_capacity']['n'])->toBe(3);
    });

    it('他人のログは含まない', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        foreach ([2, 4, 6] as $i => $mental) {
            Log::factory()->for($other)->create([
                'logged_on' => '2026-07-0'.($i + 1), 'mental_capacity' => $mental,
            ]);
        }

        $result = collect($this->service->autocorrelations($user))->keyBy('key');

        expect($result['mental_capacity_next_mental_capacity']['n'])->toBe(0);
    });
});

describe('欠測バイアス', function () {
    it('翌日が欠測だった日と翌日も記録した日の平均を返す', function () {
        $user = User::factory()->create();
        // 7/01,7/02 は連続 → 7/01 は「翌日も記録」
        Log::factory()->for($user)->create(['logged_on' => '2026-07-01', 'stress' => 6, 'mental_capacity' => 6]);
        Log::factory()->for($user)->create(['logged_on' => '2026-07-02', 'stress' => 7, 'mental_capacity' => 5]);
        // 7/04 は翌日(7/05)が欠測
        Log::factory()->for($user)->create(['logged_on' => '2026-07-04', 'stress' => 9, 'mental_capacity' => 3]);

        $bias = $this->service->coverage($user, '2026-07-01', '2026-07-10')['bias'];

        // 7/02 も翌日(7/03)が欠測なので next_missing は 7/02 と 7/04 の2日
        expect($bias['next_logged']['days'])->toBe(1)
            ->and($bias['next_logged']['avg_stress'])->toBe(6.0)
            ->and($bias['next_missing']['days'])->toBe(2)
            ->and($bias['next_missing']['avg_stress'])->toBe(8.0)
            ->and($bias['next_missing']['avg_mental_capacity'])->toBe(4.0);
    });

    it('期間の最終日は翌日が期間外なので判定に含めない', function () {
        $user = User::factory()->create();
        Log::factory()->for($user)->create(['logged_on' => '2026-07-01', 'stress' => 4]);
        Log::factory()->for($user)->create(['logged_on' => '2026-07-02', 'stress' => 9]);

        $bias = $this->service->coverage($user, '2026-07-01', '2026-07-02')['bias'];

        expect($bias['next_logged']['days'])->toBe(1)
            ->and($bias['next_logged']['avg_stress'])->toBe(4.0)
            ->and($bias['next_missing']['days'])->toBe(0)
            ->and($bias['next_missing']['avg_stress'])->toBeNull();
    });

    it('ログが1件も無くても落ちない', function () {
        $bias = $this->service->coverage(User::factory()->create(), '2026-07-01', '2026-07-10')['bias'];

        expect($bias['next_logged']['days'])->toBe(0)
            ->and($bias['next_missing']['days'])->toBe(0)
            ->and($bias['next_logged']['avg_stress'])->toBeNull();
    });
});

describe('直近の未記録連続日数', function () {
    it('期間末までの連続未記録日数を返す', function () {
        $user = User::factory()->create();
        Log::factory()->for($user)->create(['logged_on' => '2026-07-05']);

        $coverage = $this->service->coverage($user, '2026-07-01', '2026-07-10');

        // 7/06〜7/10 の5日が未記録のまま期間が終わる
        expect($coverage['current_gap'])->toBe(5)
            ->and($coverage['longest_gap'])->toBe(5);
    });

    it('期間末日に記録があれば 0 を返す', function () {
        $user = User::factory()->create();
        Log::factory()->for($user)->create(['logged_on' => '2026-07-01']);
        Log::factory()->for($user)->create(['logged_on' => '2026-07-10']);

        $coverage = $this->service->coverage($user, '2026-07-01', '2026-07-10');

        expect($coverage['current_gap'])->toBe(0)
            ->and($coverage['longest_gap'])->toBe(8);
    });

    it('1件も記録が無ければ全期間が連続未記録になる', function () {
        $coverage = $this->service->coverage(User::factory()->create(), '2026-07-01', '2026-07-10');

        expect($coverage['current_gap'])->toBe(10)
            ->and($coverage['logged_days'])->toBe(0);
    });
});

describe('入力ラグ', function () {
    it('当日入力・翌日入力・それ以降の件数を返す', function () {
        $user = User::factory()->create();
        Log::factory()->for($user)->create([
            'logged_on' => '2026-07-01', 'created_at' => '2026-07-01 15:00:00',
        ]);
        Log::factory()->for($user)->create([
            'logged_on' => '2026-07-02', 'created_at' => '2026-07-03 09:00:00',
        ]);
        Log::factory()->for($user)->create([
            'logged_on' => '2026-07-03', 'created_at' => '2026-07-07 09:00:00',
        ]);

        $lag = $this->service->inputLag($user, '2026-07-01', '2026-07-31');

        expect($lag['total'])->toBe(3)
            ->and($lag['same_day'])->toBe(1)
            ->and($lag['next_day'])->toBe(1)
            ->and($lag['later'])->toBe(1);
    });

    it('平均入力時刻を時（小数）で返す', function () {
        $user = User::factory()->create();
        Log::factory()->for($user)->create([
            'logged_on' => '2026-07-01', 'created_at' => '2026-07-01 14:00:00',
        ]);
        Log::factory()->for($user)->create([
            'logged_on' => '2026-07-02', 'created_at' => '2026-07-02 18:30:00',
        ]);

        $lag = $this->service->inputLag($user, '2026-07-01', '2026-07-31');

        expect($lag['avg_hour'])->toBe(16.25)
            ->and($lag['timezone'])->toBe(config('app.timezone'));
    });

    it('ログが1件も無くても落ちない', function () {
        $lag = $this->service->inputLag(User::factory()->create());

        expect($lag['total'])->toBe(0)
            ->and($lag['same_day'])->toBe(0)
            ->and($lag['avg_hour'])->toBeNull();
    });

    it('件数の合計は total と一致する（分類漏れを作らない）', function () {
        $user = User::factory()->create();
        // logged_on より前に作られた（未来日のログを先に書いた）ケースも later に入れる
        Log::factory()->for($user)->create([
            'logged_on' => '2026-07-05', 'created_at' => '2026-07-01 09:00:00',
        ]);
        Log::factory()->for($user)->create([
            'logged_on' => '2026-07-06', 'created_at' => '2026-07-06 09:00:00',
        ]);

        $lag = $this->service->inputLag($user);

        expect($lag['same_day'] + $lag['next_day'] + $lag['later'])->toBe($lag['total']);
    });
});

describe('相関の並び順', function () {
    it('n の多い順・|r| の大きい順に並び、信頼できるかのフラグを持つ', function () {
        $user = User::factory()->create();
        // ストレス×余裕は 12件（完全な負の相関）、睡眠×余裕は 2件だけ入力する
        foreach (range(0, 11) as $i) {
            Log::factory()->for($user)->create([
                'logged_on' => '2026-07-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'stress' => $i % 2 === 0 ? 2 : 8,
                'mental_capacity' => $i % 2 === 0 ? 8 : 2,
                'sleep_hours' => $i < 2 ? 6.0 + $i : null,
            ]);
        }

        $result = $this->service->correlations($user);

        expect($result[0]['key'])->toBe('stress_mental_capacity')
            ->and($result[0]['n'])->toBe(12)
            ->and($result[0]['reliable'])->toBeTrue();

        $sleep = collect($result)->firstWhere('key', 'sleep_hours_mental_capacity');
        expect($sleep['n'])->toBe(2)
            ->and($sleep['reliable'])->toBeFalse();
    });

    it('n が同じなら |r| の大きい順', function () {
        $user = User::factory()->create();
        foreach (range(0, 11) as $i) {
            Log::factory()->for($user)->create([
                'logged_on' => '2026-07-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
                'stress' => $i % 2 === 0 ? 2 : 8,
                'mental_capacity' => $i % 2 === 0 ? 8 : 2,
                'stamina' => 5,
            ]);
        }

        $result = collect($this->service->correlations($user))->where('n', 12)->values();
        $rs = $result->map(fn ($row) => abs($row['r'] ?? 0))->all();

        expect($rs)->toBe(collect($rs)->sortDesc()->values()->all());
    });
});
