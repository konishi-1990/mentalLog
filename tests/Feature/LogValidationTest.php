<?php

use App\Models\ChecklistOption;
use App\Models\User;
use App\Support\DurationBuckets;
use App\Support\EffectLevels;
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

it('sleep_hours が範囲外(24.5)だと422', function () {
    $this->actingAs($this->user)
        ->post(route('logs.store'), logPayload(['sleep_hours' => 24.5]))
        ->assertSessionHasErrors('sleep_hours');
});

it('sleep_hours が負数だと422', function () {
    $this->actingAs($this->user)
        ->post(route('logs.store'), logPayload(['sleep_hours' => -1]))
        ->assertSessionHasErrors('sleep_hours');
});

it('sleep_hours は 0.5 刻みの小数を受け付ける', function () {
    $this->actingAs($this->user)
        ->post(route('logs.store'), logPayload(['sleep_hours' => 6.5]))
        ->assertSessionHasNoErrors();
});

it('sleep_quality / carryover / controllability が範囲外(11)だと422', function (string $field) {
    $this->actingAs($this->user)
        ->post(route('logs.store'), logPayload([$field => 11]))
        ->assertSessionHasErrors($field);
})->with(['sleep_quality', 'carryover', 'controllability']);

it('sleep_quality / carryover / controllability が負数だと422', function (string $field) {
    $this->actingAs($this->user)
        ->post(route('logs.store'), logPayload([$field => -1]))
        ->assertSessionHasErrors($field);
})->with(['sleep_quality', 'carryover', 'controllability']);

it('day_type が許可値以外だと422', function () {
    $this->actingAs($this->user)
        ->post(route('logs.store'), logPayload(['day_type' => 'vacation']))
        ->assertSessionHasErrors('day_type');
});

it('day_type の許可値は保存できる', function (string $dayType) {
    $this->actingAs($this->user)
        ->post(route('logs.store'), logPayload(['day_type' => $dayType]))
        ->assertSessionHasNoErrors();
})->with(['weekday', 'holiday', 'paid_leave', 'business_trip', 'other']);

it('追加項目が空文字でもエラーにならない（未入力のフォーム送信）', function () {
    $this->actingAs($this->user)
        ->post(route('logs.store'), logPayload([
            'sleep_hours' => '',
            'sleep_quality' => '',
            'carryover' => '',
            'controllability' => '',
            'day_type' => '',
        ]))
        ->assertSessionHasNoErrors();
});

it('effect_score が範囲外(11)だと422', function () {
    $option = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '温泉・サウナ')->firstOrFail();

    $this->actingAs($this->user)
        ->post(route('logs.store'), logPayload([
            'checklist' => [$option->id],
            'selection_meta' => [$option->id => ['effect_score' => 11]],
        ]))
        ->assertSessionHasErrors("selection_meta.{$option->id}.effect_score");
});

it('duration_min が負数だと422', function () {
    $option = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '温泉・サウナ')->firstOrFail();

    $this->actingAs($this->user)
        ->post(route('logs.store'), logPayload([
            'checklist' => [$option->id],
            'selection_meta' => [$option->id => ['duration_min' => -1]],
        ]))
        ->assertSessionHasErrors("selection_meta.{$option->id}.duration_min");
});

it('所要時間は未選択でも保存できる（任意入力のまま）', function () {
    $option = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '温泉・サウナ')->firstOrFail();

    $this->actingAs($this->user)
        ->post(route('logs.store'), logPayload([
            'checklist' => [$option->id],
            'selection_meta' => [$option->id => ['duration_min' => '', 'effect_score' => EffectLevels::SOME]],
        ]))
        ->assertSessionHasNoErrors();
});

it('回復行動を選んだのに「効いた感」が未入力だと422', function () {
    $option = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '温泉・サウナ')->firstOrFail();

    $this->actingAs($this->user)
        ->post(route('logs.store'), logPayload([
            'checklist' => [$option->id],
        ]))
        ->assertSessionHasErrors("selection_meta.{$option->id}.effect_score");
});

it('効いた感が空文字でも「未入力」として422', function () {
    $option = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '温泉・サウナ')->firstOrFail();

    $this->actingAs($this->user)
        ->post(route('logs.store'), logPayload([
            'checklist' => [$option->id],
            'selection_meta' => [$option->id => ['effect_score' => '']],
        ]))
        ->assertSessionHasErrors("selection_meta.{$option->id}.effect_score");
});

it('is_none の回復行動（何もできてない）だけなら効いた感は不要', function () {
    $none = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('is_none', true)->firstOrFail();

    $this->actingAs($this->user)
        ->post(route('logs.store'), logPayload([
            'checklist' => [$none->id],
        ]))
        ->assertSessionHasNoErrors();
});

it('tracks_effect でないカテゴリでは効いた感を要求しない', function (string $categoryCode) {
    $option = ChecklistOption::whereRelation('category', 'code', $categoryCode)
        ->where('is_none', false)->where('requires_text', false)->firstOrFail();

    $this->actingAs($this->user)
        ->post(route('logs.store'), logPayload([
            'checklist' => [$option->id],
        ]))
        ->assertSessionHasNoErrors();
})->with(['thought_habit', 'body_reaction', 'intake']);

it('効いた感は3段階の値だけを受け付ける', function (int $score) {
    $option = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '温泉・サウナ')->firstOrFail();

    $this->actingAs($this->user)
        ->post(route('logs.store'), logPayload([
            'checklist' => [$option->id],
            'selection_meta' => [$option->id => ['effect_score' => $score]],
        ]))
        ->assertSessionHasNoErrors();
})->with(EffectLevels::scores());

it('効いた感が3段階以外の値だと422', function () {
    $option = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '温泉・サウナ')->firstOrFail();

    $this->actingAs($this->user)
        ->post(route('logs.store'), logPayload([
            'checklist' => [$option->id],
            'selection_meta' => [$option->id => ['effect_score' => 7]],
        ]))
        ->assertSessionHasErrors("selection_meta.{$option->id}.effect_score");
});

it('所要時間は選択肢の値だけを受け付ける', function (int $minutes) {
    $option = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '温泉・サウナ')->firstOrFail();

    $this->actingAs($this->user)
        ->post(route('logs.store'), logPayload([
            'checklist' => [$option->id],
            'selection_meta' => [$option->id => [
                'duration_min' => $minutes,
                'effect_score' => EffectLevels::SOME,
            ]],
        ]))
        ->assertSessionHasNoErrors();
})->with(DurationBuckets::minutes());

it('所要時間が選択肢以外の値だと422', function () {
    $option = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '温泉・サウナ')->firstOrFail();

    $this->actingAs($this->user)
        ->post(route('logs.store'), logPayload([
            'checklist' => [$option->id],
            'selection_meta' => [$option->id => [
                'duration_min' => 90,
                'effect_score' => EffectLevels::SOME,
            ]],
        ]))
        ->assertSessionHasErrors("selection_meta.{$option->id}.duration_min");
});

it('選択していない選択肢の selection_meta は検証されない', function () {
    $selected = ChecklistOption::whereRelation('category', 'code', 'body_reaction')
        ->where('label', 'イライラ')->firstOrFail();
    $unselected = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '温泉・サウナ')->firstOrFail();

    $this->actingAs($this->user)
        ->post(route('logs.store'), logPayload([
            'checklist' => [$selected->id],
            'selection_meta' => [$unselected->id => ['effect_score' => '']],
        ]))
        ->assertSessionHasNoErrors();
});
