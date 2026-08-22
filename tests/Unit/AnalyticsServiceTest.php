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
