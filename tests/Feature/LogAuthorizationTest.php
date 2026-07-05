<?php

use App\Models\Log;
use App\Models\User;

it('他人のログ詳細は閲覧できない（403）', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $log = Log::factory()->for($owner)->create();

    $this->actingAs($other)->get(route('logs.show', $log))->assertForbidden();
});

it('他人のログ編集画面は開けない（403）', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $log = Log::factory()->for($owner)->create();

    $this->actingAs($other)->get(route('logs.edit', $log))->assertForbidden();
});

it('他人のログは更新できない（403）', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $log = Log::factory()->for($owner)->create(['logged_on' => '2026-07-06']);

    $this->actingAs($other)->put(route('logs.update', $log), logPayload([
        'logged_on' => '2026-07-06',
    ]))->assertForbidden();
});

it('他人のログは削除できない（403）', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $log = Log::factory()->for($owner)->create();

    $this->actingAs($other)->delete(route('logs.destroy', $log))->assertForbidden();
    $this->assertDatabaseHas('logs', ['id' => $log->id]);
});

it('管理者は他人のログを閲覧できる', function () {
    $owner = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $log = Log::factory()->for($owner)->create();

    $this->actingAs($admin)->get(route('logs.show', $log))->assertOk();
});
