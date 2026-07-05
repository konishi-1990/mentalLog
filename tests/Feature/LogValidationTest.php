<?php

use App\Models\ChecklistOption;
use App\Models\User;
use Database\Seeders\ChecklistCategorySeeder;
use Database\Seeders\ChecklistOptionSeeder;

beforeEach(function () {
    $this->seed([ChecklistCategorySeeder::class, ChecklistOptionSeeder::class]);
    $this->user = User::factory()->create();
});

it('stress が範囲外(11)だと422', function () {
    $this->actingAs($this->user)
        ->post(route('logs.store'), logPayload(['stress' => 11]))
        ->assertSessionHasErrors('stress');
});

it('数値が未入力だとエラー', function () {
    $this->actingAs($this->user)
        ->post(route('logs.store'), logPayload(['stress' => null]))
        ->assertSessionHasErrors('stress');
});

it('logged_on が未入力だとエラー', function () {
    $this->actingAs($this->user)
        ->post(route('logs.store'), logPayload(['logged_on' => null]))
        ->assertSessionHasErrors('logged_on');
});

it('同一カテゴリで「特になし」と他項目の同時選択はエラー', function () {
    $none = ChecklistOption::whereRelation('category', 'code', 'thought_habit')
        ->where('is_none', true)->first();
    $other = ChecklistOption::whereRelation('category', 'code', 'thought_habit')
        ->where('is_none', false)->first();

    $this->actingAs($this->user)
        ->post(route('logs.store'), logPayload([
            'checklist' => [$none->id, $other->id],
        ]))
        ->assertSessionHasErrors('checklist');
});

it('requires_text の「その他」を選んで補足が空だとエラー', function () {
    $other = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('requires_text', true)->first();

    $this->actingAs($this->user)
        ->post(route('logs.store'), logPayload([
            'checklist' => [$other->id],
            'checklist_details' => [],
        ]))
        ->assertSessionHasErrors('checklist_details.'.$other->id);
});

it('「特になし」を単独で選ぶのは有効', function () {
    $none = ChecklistOption::whereRelation('category', 'code', 'thought_habit')
        ->where('is_none', true)->first();

    $this->actingAs($this->user)
        ->post(route('logs.store'), logPayload([
            'checklist' => [$none->id],
        ]))
        ->assertSessionHasNoErrors();
});
