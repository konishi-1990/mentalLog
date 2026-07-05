<?php

use App\Models\ChecklistCategory;
use App\Models\ChecklistOption;
use App\Models\Role;
use Illuminate\Support\Facades\Artisan;

beforeEach(function () {
    Artisan::call('db:seed', ['--force' => true]);
});

it('ロールが admin / user の2件投入される', function () {
    expect(Role::count())->toBe(2)
        ->and(Role::where('code', 'admin')->exists())->toBeTrue()
        ->and(Role::where('code', 'user')->exists())->toBeTrue();
});

it('チェックリストカテゴリが3件（クセ/体の反応/回復行動）', function () {
    expect(ChecklistCategory::count())->toBe(3)
        ->and(ChecklistCategory::where('code', 'thought_habit')->exists())->toBeTrue()
        ->and(ChecklistCategory::where('code', 'body_reaction')->exists())->toBeTrue()
        ->and(ChecklistCategory::where('code', 'recovery_action')->exists())->toBeTrue();
});

it('回復行動の「その他」は requires_text=true', function () {
    $other = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', 'その他')->first();

    expect($other)->not->toBeNull()
        ->and($other->requires_text)->toBeTrue();
});

it('「特になし」は is_none=true で2カテゴリに存在', function () {
    expect(ChecklistOption::where('is_none', true)->count())->toBe(2);
});

it('各カテゴリに選択肢が投入される', function () {
    expect(ChecklistOption::whereRelation('category', 'code', 'thought_habit')->count())->toBe(6)
        ->and(ChecklistOption::whereRelation('category', 'code', 'body_reaction')->count())->toBe(6)
        ->and(ChecklistOption::whereRelation('category', 'code', 'recovery_action')->count())->toBe(7);
});
