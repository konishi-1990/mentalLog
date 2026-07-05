<?php

use App\Models\ChecklistCategory;
use App\Models\ChecklistOption;
use App\Models\User;
use Database\Seeders\ChecklistCategorySeeder;
use Database\Seeders\ChecklistOptionSeeder;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed([RoleSeeder::class, ChecklistCategorySeeder::class, ChecklistOptionSeeder::class]);
    $this->admin = User::factory()->admin()->create();
});

it('一般ユーザはチェックリスト管理にアクセスできない（403）', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.checklist.index'))->assertForbidden();
});

it('管理者はチェックリスト管理画面を表示できる', function () {
    $this->actingAs($this->admin)->get(route('admin.checklist.index'))->assertOk();
});

it('管理者は選択肢を追加できる', function () {
    $category = ChecklistCategory::where('code', 'recovery_action')->first();

    $this->actingAs($this->admin)->post(route('admin.checklist.store'), [
        'category_id' => $category->id,
        'label' => '瞑想',
        'requires_text' => false,
        'is_none' => false,
    ])->assertRedirect(route('admin.checklist.index'));

    $this->assertDatabaseHas('checklist_options', ['label' => '瞑想', 'category_id' => $category->id]);
});

it('管理者は選択肢を更新できる（requires_text等）', function () {
    $option = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '一人時間')->first();

    $this->actingAs($this->admin)->put(route('admin.checklist.update', $option), [
        'category_id' => $option->category_id,
        'label' => '静かな一人時間',
        'requires_text' => true,
        'is_none' => false,
        'is_active' => true,
    ]);

    $fresh = $option->fresh();
    expect($fresh->label)->toBe('静かな一人時間')
        ->and($fresh->requires_text)->toBeTrue();
});

it('管理者は選択肢を無効化できる', function () {
    $option = ChecklistOption::whereRelation('category', 'code', 'body_reaction')->first();

    $this->actingAs($this->admin)->delete(route('admin.checklist.destroy', $option));

    expect($option->fresh()->is_active)->toBeFalse();
});

it('ラベルは必須', function () {
    $category = ChecklistCategory::first();

    $this->actingAs($this->admin)->post(route('admin.checklist.store'), [
        'category_id' => $category->id,
        'label' => '',
    ])->assertSessionHasErrors('label');
});
