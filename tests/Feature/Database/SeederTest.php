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

it('チェックリストカテゴリが4件（クセ/体の反応/回復行動/摂取したもの）', function () {
    expect(ChecklistCategory::count())->toBe(4)
        ->and(ChecklistCategory::where('code', 'thought_habit')->exists())->toBeTrue()
        ->and(ChecklistCategory::where('code', 'body_reaction')->exists())->toBeTrue()
        ->and(ChecklistCategory::where('code', 'recovery_action')->exists())->toBeTrue()
        ->and(ChecklistCategory::where('code', 'intake')->exists())->toBeTrue();
});

it('回復行動の「その他」は requires_text=true', function () {
    $other = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', 'その他')->first();

    expect($other)->not->toBeNull()
        ->and($other->requires_text)->toBeTrue();
});

it('「特になし」相当（is_none=true）は3カテゴリに存在', function () {
    expect(ChecklistOption::where('is_none', true)->count())->toBe(3);
});

it('摂取したものの「その他」は requires_text=true', function () {
    $other = ChecklistOption::whereRelation('category', 'code', 'intake')
        ->where('label', 'その他')->first();

    expect($other)->not->toBeNull()
        ->and($other->requires_text)->toBeTrue();
});

it('各カテゴリに選択肢が投入される', function () {
    expect(ChecklistOption::whereRelation('category', 'code', 'thought_habit')->count())->toBe(6)
        ->and(ChecklistOption::whereRelation('category', 'code', 'body_reaction')->count())->toBe(6)
        ->and(ChecklistOption::whereRelation('category', 'code', 'recovery_action')->count())->toBe(7)
        ->and(ChecklistOption::whereRelation('category', 'code', 'intake')->count())->toBe(5);
});

it('効果測定フラグは回復行動カテゴリのみ true', function () {
    expect(ChecklistCategory::where('tracks_effect', true)->pluck('code')->all())
        ->toBe(['recovery_action']);
});
