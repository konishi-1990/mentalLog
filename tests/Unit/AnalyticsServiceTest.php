<?php

use App\Models\ChecklistOption;
use App\Models\Log;
use App\Models\LogCheckItemValue;
use App\Models\User;
use App\Services\AnalyticsService;
use Database\Seeders\ChecklistCategorySeeder;
use Database\Seeders\ChecklistOptionSeeder;

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
