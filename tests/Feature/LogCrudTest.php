<?php

use App\Models\ChecklistOption;
use App\Models\Log;
use App\Models\User;
use Database\Seeders\ChecklistCategorySeeder;
use Database\Seeders\ChecklistOptionSeeder;

beforeEach(function () {
    $this->seed([ChecklistCategorySeeder::class, ChecklistOptionSeeder::class]);
});

it('未ログインではログ作成画面にアクセスできない', function () {
    $this->get(route('logs.create'))->assertRedirect(route('login'));
});

it('ログイン済みユーザはログ作成画面を表示できる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('logs.create'))->assertOk();
});

it('ログを保存できる（数値・テキスト）', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logs.store'), logPayload([
        'stress' => 7,
        'summary_text' => 'まとめテキスト',
    ]));

    $log = Log::first();
    $response->assertRedirect(route('logs.show', $log));
    expect($log->user_id)->toBe($user->id)
        ->and($log->stress)->toBe(7)
        ->and($log->summary_text)->toBe('まとめテキスト');
});

it('○の○×項目の内容が保存される', function () {
    $user = User::factory()->create();
    $item = $user->checkItems()->first();

    $this->actingAs($user)->post(route('logs.store'), logPayload([
        'check_items' => [$item->id => ['is_on' => true, 'detail_text' => '会議が多い']],
    ]));

    $this->assertDatabaseHas('log_check_item_values', [
        'check_item_id' => $item->id,
        'is_on' => true,
        'detail_text' => '会議が多い',
    ]);
});

it('チェックリストの選択が保存される', function () {
    $user = User::factory()->create();
    $option = ChecklistOption::whereRelation('category', 'code', 'body_reaction')
        ->where('label', 'イライラ')->first();

    $this->actingAs($user)->post(route('logs.store'), logPayload([
        'checklist' => [$option->id],
    ]));

    $this->assertDatabaseHas('log_checklist_selections', [
        'checklist_option_id' => $option->id,
    ]);
});

it('ログを更新できる', function () {
    $user = User::factory()->create();
    $log = Log::factory()->for($user)->create(['logged_on' => '2026-07-06', 'stress' => 2]);

    $this->actingAs($user)->put(route('logs.update', $log), logPayload([
        'logged_on' => '2026-07-06',
        'stress' => 9,
    ]))->assertRedirect(route('logs.show', $log));

    expect($log->fresh()->stress)->toBe(9);
});

it('ログを削除できる', function () {
    $user = User::factory()->create();
    $log = Log::factory()->for($user)->create();

    $this->actingAs($user)->delete(route('logs.destroy', $log))
        ->assertRedirect(route('logs.index'));

    $this->assertDatabaseMissing('logs', ['id' => $log->id]);
});

it('ログ詳細を表示できる', function () {
    $user = User::factory()->create();
    $log = Log::factory()->for($user)->create();

    $this->actingAs($user)->get(route('logs.show', $log))->assertOk();
});
