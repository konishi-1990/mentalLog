<?php

use App\Models\Log;
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
