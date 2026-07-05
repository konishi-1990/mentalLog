<?php

use App\Models\Log;
use App\Models\User;

/** 指定日・スコアのログを作る補助 */
function makeLog(User $user, string $date, array $scores = []): Log
{
    return Log::factory()->for($user)->create(array_merge([
        'logged_on' => $date,
        'stress' => 5,
        'stamina' => 5,
        'mental_capacity' => 5,
    ], $scores));
}

it('日付 from/to で絞り込める', function () {
    $user = User::factory()->create();
    makeLog($user, '2026-07-01');
    makeLog($user, '2026-07-05');
    makeLog($user, '2026-07-10');

    $this->actingAs($user)
        ->get(route('logs.index', ['from' => '2026-07-03', 'to' => '2026-07-08']))
        ->assertOk()
        ->assertViewHas('logs', fn ($logs) => $logs->count() === 1);
});

it('ストレスの min/max で絞り込める（境界値を含む）', function () {
    $user = User::factory()->create();
    makeLog($user, '2026-07-01', ['stress' => 2]);
    makeLog($user, '2026-07-02', ['stress' => 6]);
    makeLog($user, '2026-07-03', ['stress' => 8]);
    makeLog($user, '2026-07-04', ['stress' => 9]);

    $this->actingAs($user)
        ->get(route('logs.index', ['stress_min' => 6, 'stress_max' => 8]))
        ->assertViewHas('logs', fn ($logs) => $logs->count() === 2);
});

it('体力・メンタル余裕の min/max で絞り込める', function () {
    $user = User::factory()->create();
    makeLog($user, '2026-07-01', ['stamina' => 3, 'mental_capacity' => 8]);
    makeLog($user, '2026-07-02', ['stamina' => 7, 'mental_capacity' => 2]);

    $this->actingAs($user)
        ->get(route('logs.index', ['stamina_min' => 5]))
        ->assertViewHas('logs', fn ($logs) => $logs->count() === 1);

    $this->actingAs($user)
        ->get(route('logs.index', ['mental_max' => 5]))
        ->assertViewHas('logs', fn ($logs) => $logs->count() === 1);
});

it('一般ユーザは自分のログしか見えない', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    makeLog($me, '2026-07-01');
    makeLog($other, '2026-07-01');

    $this->actingAs($me)
        ->get(route('logs.index'))
        ->assertViewHas('logs', fn ($logs) => $logs->count() === 1
            && $logs->every(fn ($l) => $l->user_id === $me->id));
});

it('管理者は全ユーザのログを見られる', function () {
    $admin = User::factory()->admin()->create();
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();
    makeLog($u1, '2026-07-01');
    makeLog($u2, '2026-07-01');

    $this->actingAs($admin)
        ->get(route('logs.index'))
        ->assertViewHas('logs', fn ($logs) => $logs->count() === 2);
});

it('管理者はユーザ指定で絞り込める', function () {
    $admin = User::factory()->admin()->create();
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();
    makeLog($u1, '2026-07-01');
    makeLog($u1, '2026-07-02');
    makeLog($u2, '2026-07-01');

    $this->actingAs($admin)
        ->get(route('logs.index', ['user_id' => $u1->id]))
        ->assertViewHas('logs', fn ($logs) => $logs->count() === 2);
});

it('範囲外の絞り込み値はバリデーションエラー', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('logs.index', ['stress_min' => 99]))
        ->assertSessionHasErrors('stress_min');
});
