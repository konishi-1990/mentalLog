<?php

use App\Models\ChecklistOption;
use App\Models\Log;
use App\Models\User;
use Database\Seeders\ChecklistCategorySeeder;
use Database\Seeders\ChecklistOptionSeeder;

beforeEach(function () {
    $this->seed([ChecklistCategorySeeder::class, ChecklistOptionSeeder::class]);
    $this->other = recoveryOption('その他');
    $this->cafe = recoveryOption('カフェ・喫茶');
    $this->user = User::factory()->create(['email' => 'me@example.com']);
});

function recoveryOption(string $label): ChecklistOption
{
    return ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', $label)->firstOrFail();
}

/**
 * 指定日のログに選択を1件付けて返す。
 */
function selectOn(User $user, string $date, ChecklistOption $option, ?string $detail, array $meta = [])
{
    $log = Log::factory()->for($user)->create(['logged_on' => $date]);

    return $log->checklistSelections()->create(array_merge([
        'checklist_option_id' => $option->id,
        'detail_text' => $detail,
    ], $meta));
}

function reclassify(array $options)
{
    return test()->artisan('app:reclassify-selections', $options);
}

it('補足に一致した「その他」だけを付け替える', function () {
    $hit = selectOn($this->user, '2026-09-01', $this->other, '夕方サテン');
    $miss = selectOn($this->user, '2026-09-02', $this->other, '遊行');

    reclassify(['--from' => $this->other->id, '--to' => $this->cafe->id, '--match' => 'サテン'])
        ->assertSuccessful();

    expect($hit->fresh()->checklist_option_id)->toBe($this->cafe->id)
        ->and($miss->fresh()->checklist_option_id)->toBe($this->other->id);
});

it('部分一致は大文字小文字を区別しない', function () {
    $hit = selectOn($this->user, '2026-09-01', $this->other, 'Cafe で休憩');

    reclassify(['--from' => $this->other->id, '--to' => $this->cafe->id, '--match' => 'cafe'])
        ->assertSuccessful();

    expect($hit->fresh()->checklist_option_id)->toBe($this->cafe->id);
});

it('補足テキスト・効いた感・時間は残す', function () {
    $hit = selectOn($this->user, '2026-09-01', $this->other, 'サテン', ['effect_score' => 5, 'duration_min' => 60]);

    reclassify(['--from' => $this->other->id, '--to' => $this->cafe->id, '--match' => 'サテン']);

    $fresh = $hit->fresh();
    expect($fresh->detail_text)->toBe('サテン')
        ->and($fresh->effect_score)->toBe(5)
        ->and($fresh->duration_min)->toBe(60);
});

it('--user を指定すると他ユーザの行は変えない', function () {
    $mine = selectOn($this->user, '2026-09-01', $this->other, 'サテン');
    $theirs = selectOn(User::factory()->create(), '2026-09-01', $this->other, 'サテン');

    reclassify([
        '--from' => $this->other->id, '--to' => $this->cafe->id, '--match' => 'サテン',
        '--user' => 'me@example.com',
    ])->assertSuccessful();

    expect($mine->fresh()->checklist_option_id)->toBe($this->cafe->id)
        ->and($theirs->fresh()->checklist_option_id)->toBe($this->other->id);
});

it('--dry-run では対象を表示するだけで書き換えない', function () {
    $hit = selectOn($this->user, '2026-09-01', $this->other, 'サテン');

    reclassify(['--from' => $this->other->id, '--to' => $this->cafe->id, '--match' => 'サテン', '--dry-run' => true])
        ->expectsOutputToContain('2026-09-01')
        ->expectsOutputToContain('dry-run')
        ->assertSuccessful();

    expect($hit->fresh()->checklist_option_id)->toBe($this->other->id);
});

it('同じログに付け替え先の選択が既にあればスキップして報告する（unique 制約）', function () {
    $hit = selectOn($this->user, '2026-09-01', $this->other, 'サテン');
    $hit->log->checklistSelections()->create(['checklist_option_id' => $this->cafe->id]);

    reclassify(['--from' => $this->other->id, '--to' => $this->cafe->id, '--match' => 'サテン'])
        ->expectsOutputToContain('スキップ')
        ->assertSuccessful();

    expect($hit->fresh()->checklist_option_id)->toBe($this->other->id);
});

it('付け替え元と先のカテゴリが違えば何もせず失敗する', function () {
    $hit = selectOn($this->user, '2026-09-01', $this->other, 'サテン');
    $irritation = ChecklistOption::whereRelation('category', 'code', 'body_reaction')
        ->where('label', 'イライラ')->firstOrFail();

    reclassify(['--from' => $this->other->id, '--to' => $irritation->id, '--match' => 'サテン'])
        ->assertFailed();

    expect($hit->fresh()->checklist_option_id)->toBe($this->other->id);
});

it('存在しない --user を指定すると失敗する', function () {
    reclassify([
        '--from' => $this->other->id, '--to' => $this->cafe->id, '--match' => 'サテン',
        '--user' => 'nobody@example.com',
    ])->assertFailed();
});

it('--match が空なら失敗する（全件付け替えの事故を防ぐ）', function () {
    $hit = selectOn($this->user, '2026-09-01', $this->other, 'サテン');

    reclassify(['--from' => $this->other->id, '--to' => $this->cafe->id, '--match' => ''])
        ->assertFailed();

    expect($hit->fresh()->checklist_option_id)->toBe($this->other->id);
});
