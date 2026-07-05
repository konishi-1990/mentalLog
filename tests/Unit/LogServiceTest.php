<?php

use App\Models\Log;
use App\Models\LogCheckItemValue;
use App\Models\User;
use App\Services\LogService;

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
