<?php

use App\Models\Log;
use App\Models\LogCheckItemValue;
use App\Models\User;
use Illuminate\Database\QueryException;

it('stress が範囲外(11)だと保存できない（CHECK制約）', function () {
    $user = User::factory()->create();

    Log::factory()->for($user)->create(['stress' => 11]);
})->throws(QueryException::class);

it('stamina が範囲外(-1)だと保存できない（CHECK制約）', function () {
    $user = User::factory()->create();

    Log::factory()->for($user)->create(['stamina' => -1]);
})->throws(QueryException::class);

it('同一ユーザ・同一日のログは重複作成できない（複合ユニーク）', function () {
    $user = User::factory()->create();

    Log::factory()->for($user)->create(['logged_on' => '2026-07-06']);
    Log::factory()->for($user)->create(['logged_on' => '2026-07-06']);
})->throws(QueryException::class);

it('異なるユーザなら同一日でも作成できる', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    Log::factory()->for($a)->create(['logged_on' => '2026-07-06']);
    Log::factory()->for($b)->create(['logged_on' => '2026-07-06']);

    expect(Log::count())->toBe(2);
});

it('sleep_quality が範囲外(11)だと保存できない（CHECK制約）', function () {
    $user = User::factory()->create();

    Log::factory()->for($user)->create(['sleep_quality' => 11]);
})->throws(QueryException::class);

it('carryover が範囲外(-1)だと保存できない（CHECK制約）', function () {
    $user = User::factory()->create();

    Log::factory()->for($user)->create(['carryover' => -1]);
})->throws(QueryException::class);

it('controllability が範囲外(11)だと保存できない（CHECK制約）', function () {
    $user = User::factory()->create();

    Log::factory()->for($user)->create(['controllability' => 11]);
})->throws(QueryException::class);

it('sleep_hours が範囲外(24.5)だと保存できない（CHECK制約）', function () {
    $user = User::factory()->create();

    Log::factory()->for($user)->create(['sleep_hours' => 24.5]);
})->throws(QueryException::class);

it('追加項目が NULL なら CHECK 制約を通過する（既存ログ互換）', function () {
    $user = User::factory()->create();

    $log = Log::factory()->for($user)->create([
        'sleep_hours' => null,
        'sleep_quality' => null,
        'carryover' => null,
        'controllability' => null,
        'day_type' => null,
    ]);

    expect($log->exists)->toBeTrue();
});

it('ストレス源の強度が 1〜3 以外だと保存できない（CHECK制約）', function () {
    $user = User::factory()->create();
    $log = Log::factory()->for($user)->create();

    LogCheckItemValue::create([
        'log_id' => $log->id,
        'check_item_id' => $user->checkItems()->first()->id,
        'is_on' => true,
        'severity' => 4,
    ]);
})->throws(QueryException::class);

it('疲労度が範囲外(11)だと保存できない（CHECK制約）', function () {
    $user = User::factory()->create();
    Log::factory()->for($user)->create(['fatigue' => 11]);
})->throws(QueryException::class);

it('体力は NULL で保存できる（凍結後の新規ログ）', function () {
    $user = User::factory()->create();
    $log = Log::factory()->for($user)->create(['stamina' => null]);

    expect($log->fresh()->stamina)->toBeNull();
});

it('起きたときの余裕が範囲外(11)だと保存できない（CHECK制約）', function () {
    $user = User::factory()->create();
    Log::factory()->for($user)->create(['morning_capacity' => 11]);
})->throws(QueryException::class);
