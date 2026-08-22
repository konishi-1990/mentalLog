<?php

use App\Models\ChecklistOption;
use App\Models\Log;
use App\Models\LogCheckItemValue;
use App\Models\LogChecklistSelection;
use App\Models\Person;
use App\Models\User;
use App\Services\LogService;
use Database\Seeders\ChecklistCategorySeeder;
use Database\Seeders\ChecklistOptionSeeder;

beforeEach(function () {
    $this->service = app(LogService::class);
});

it('新規ログを数値・テキストとともに作成する', function () {
    $user = User::factory()->create();

    $log = $this->service->upsertDailyLog($user, logPayload([
        'stress' => 8,
        'stamina' => 3,
        'mental_capacity' => 4,
    ]));

    expect($log->exists)->toBeTrue()
        ->and($log->user_id)->toBe($user->id)
        ->and($log->stress)->toBe(8)
        ->and($log->stamina)->toBe(3)
        ->and($log->mental_capacity)->toBe(4)
        ->and(Log::count())->toBe(1);
});

it('同一日の再登録は更新（1件のまま・値が上書き）', function () {
    $user = User::factory()->create();

    $this->service->upsertDailyLog($user, logPayload(['stress' => 5]));
    $log = $this->service->upsertDailyLog($user, logPayload(['stress' => 9]));

    expect(Log::count())->toBe(1)
        ->and($log->stress)->toBe(9);
});

it('○の○×項目は detail_text を保存し、×は null にする', function () {
    $user = User::factory()->create();
    $items = $user->checkItems()->orderBy('sort_order')->get();
    $onItem = $items[0];
    $offItem = $items[1];

    $log = $this->service->upsertDailyLog($user, logPayload([
        'check_items' => [
            $onItem->id => ['is_on' => true, 'detail_text' => '残業続き'],
            $offItem->id => ['is_on' => false, 'detail_text' => '無視されるはず'],
        ],
    ]));

    $on = LogCheckItemValue::where('log_id', $log->id)->where('check_item_id', $onItem->id)->first();
    $off = LogCheckItemValue::where('log_id', $log->id)->where('check_item_id', $offItem->id)->first();

    expect($on->is_on)->toBeTrue()
        ->and($on->detail_text)->toBe('残業続き')
        ->and($off->is_on)->toBeFalse()
        ->and($off->detail_text)->toBeNull();
});

it('再登録で子（○×・チェック）が置き換えられる', function () {
    $user = User::factory()->create();
    $item = $user->checkItems()->first();

    $this->service->upsertDailyLog($user, logPayload([
        'check_items' => [$item->id => ['is_on' => true, 'detail_text' => '初回']],
    ]));
    $log = $this->service->upsertDailyLog($user, logPayload([
        'check_items' => [$item->id => ['is_on' => true, 'detail_text' => '更新後']],
    ]));

    expect(LogCheckItemValue::where('log_id', $log->id)->count())->toBe(1)
        ->and(LogCheckItemValue::where('log_id', $log->id)->first()->detail_text)->toBe('更新後');
});

it('追加項目（睡眠・持ち越し感・コントロール可能度・勤務形態）を保存する', function () {
    $user = User::factory()->create();

    $log = $this->service->upsertDailyLog($user, logPayload([
        'sleep_hours' => 6.5,
        'sleep_quality' => 4,
        'carryover' => 8,
        'controllability' => 2,
        'day_type' => 'paid_leave',
    ]));

    expect((float) $log->sleep_hours)->toBe(6.5)
        ->and($log->sleep_quality)->toBe(4)
        ->and($log->carryover)->toBe(8)
        ->and($log->controllability)->toBe(2)
        ->and($log->day_type)->toBe('paid_leave');
});

it('追加項目を送らないと NULL のまま保存される', function () {
    $user = User::factory()->create();

    $log = $this->service->upsertDailyLog($user, logPayload());

    expect($log->sleep_hours)->toBeNull()
        ->and($log->sleep_quality)->toBeNull()
        ->and($log->carryover)->toBeNull()
        ->and($log->controllability)->toBeNull()
        ->and($log->day_type)->toBeNull();
});

it('更新時に追加項目だけを変更できる', function () {
    $user = User::factory()->create();

    $this->service->upsertDailyLog($user, logPayload([
        'stress' => 5,
        'sleep_hours' => 7.0,
        'carryover' => 3,
    ]));

    $log = $this->service->upsertDailyLog($user, logPayload([
        'stress' => 5,
        'sleep_hours' => 4.5,
        'carryover' => 9,
    ]));

    expect(Log::count())->toBe(1)
        ->and($log->stress)->toBe(5)
        ->and((float) $log->sleep_hours)->toBe(4.5)
        ->and($log->carryover)->toBe(9);
});

it('追加項目が入力済みでも未送信の更新で NULL に戻る（置き換え更新の担保）', function () {
    $user = User::factory()->create();

    $this->service->upsertDailyLog($user, logPayload(['sleep_hours' => 7.0]));
    $log = $this->service->upsertDailyLog($user, logPayload());

    expect($log->sleep_hours)->toBeNull();
});

describe('回復行動の計測', function () {
    beforeEach(function () {
        $this->seed([ChecklistCategorySeeder::class, ChecklistOptionSeeder::class]);
        $this->recoveryOption = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
            ->where('label', '温泉・サウナ')->firstOrFail();
    });

    it('選択と同時に duration_min / effect_score が保存される', function () {
        $user = User::factory()->create();

        $log = $this->service->upsertDailyLog($user, logPayload([
            'checklist' => [$this->recoveryOption->id],
            'selection_meta' => [
                $this->recoveryOption->id => ['duration_min' => 90, 'effect_score' => 8],
            ],
        ]));

        $selection = LogChecklistSelection::where('log_id', $log->id)
            ->where('checklist_option_id', $this->recoveryOption->id)->firstOrFail();

        expect($selection->duration_min)->toBe(90)
            ->and($selection->effect_score)->toBe(8);
    });

    it('duration_min / effect_score が未入力なら NULL で保存される', function () {
        $user = User::factory()->create();

        $log = $this->service->upsertDailyLog($user, logPayload([
            'checklist' => [$this->recoveryOption->id],
        ]));

        $selection = LogChecklistSelection::where('log_id', $log->id)->firstOrFail();

        expect($selection->duration_min)->toBeNull()
            ->and($selection->effect_score)->toBeNull();
    });

    it('選択していない選択肢の meta は保存されない', function () {
        $user = User::factory()->create();
        $unselected = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
            ->where('label', '一人時間')->firstOrFail();

        $log = $this->service->upsertDailyLog($user, logPayload([
            'checklist' => [$this->recoveryOption->id],
            'selection_meta' => [
                $unselected->id => ['duration_min' => 30, 'effect_score' => 5],
            ],
        ]));

        expect(LogChecklistSelection::where('log_id', $log->id)->count())->toBe(1)
            ->and(LogChecklistSelection::where('log_id', $log->id)->first()->duration_min)->toBeNull();
    });

    it('effect_score のみの入力でも保存できる', function () {
        $user = User::factory()->create();

        $log = $this->service->upsertDailyLog($user, logPayload([
            'checklist' => [$this->recoveryOption->id],
            'selection_meta' => [
                $this->recoveryOption->id => ['effect_score' => 0],
            ],
        ]));

        $selection = LogChecklistSelection::where('log_id', $log->id)->firstOrFail();

        expect($selection->effect_score)->toBe(0)
            ->and($selection->duration_min)->toBeNull();
    });
});

describe('相手タグ', function () {
    it('ログ保存時に相手タグが紐づく', function () {
        $user = User::factory()->create();
        $a = Person::factory()->for($user)->create(['name' => '上層部']);
        $b = Person::factory()->for($user)->create(['name' => 'バンドメンバー']);

        $log = $this->service->upsertDailyLog($user, logPayload([
            'people' => [$a->id, $b->id],
            'people_details' => [$a->id => '無茶な指示'],
        ]));

        expect($log->people()->pluck('people.id')->sort()->values()->all())
            ->toBe(collect([$a->id, $b->id])->sort()->values()->all());

        $this->assertDatabaseHas('log_people', [
            'log_id' => $log->id,
            'person_id' => $a->id,
            'detail_text' => '無茶な指示',
        ]);
        $this->assertDatabaseHas('log_people', [
            'log_id' => $log->id,
            'person_id' => $b->id,
            'detail_text' => null,
        ]);
    });

    it('他人の person_id を送っても無視される', function () {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $mine = Person::factory()->for($user)->create();
        $theirs = Person::factory()->for($other)->create();

        $log = $this->service->upsertDailyLog($user, logPayload([
            'people' => [$mine->id, $theirs->id],
        ]));

        expect($log->people()->pluck('people.id')->all())->toBe([$mine->id]);
    });

    it('相手タグを送らなければ紐づけは空になる', function () {
        $user = User::factory()->create();

        $log = $this->service->upsertDailyLog($user, logPayload());

        expect($log->people()->count())->toBe(0);
    });

    it('再登録で相手タグが置き換えられる', function () {
        $user = User::factory()->create();
        $a = Person::factory()->for($user)->create();
        $b = Person::factory()->for($user)->create();

        $this->service->upsertDailyLog($user, logPayload(['people' => [$a->id]]));
        $log = $this->service->upsertDailyLog($user, logPayload(['people' => [$b->id]]));

        expect($log->people()->pluck('people.id')->all())->toBe([$b->id]);
    });
});
