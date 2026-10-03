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

it('「特になし」相当（is_none=true）は4件', function () {
    // クセ / 体の反応 / 摂取したもの の各「特になし」＋
    // 回復行動の「何もできてない」（他の回復行動と排他かつ効果を聞かない）
    expect(ChecklistOption::where('is_none', true)->count())->toBe(4);
});

it('回復行動の「何もできてない」は is_none=true', function () {
    $nothing = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '何もできてない')->first();

    expect($nothing)->not->toBeNull()
        ->and($nothing->is_none)->toBeTrue();
});

it('カテゴリに説明文が投入される（摂取物への誘導）', function () {
    expect(ChecklistCategory::where('code', 'intake')->value('description'))
        ->toContain('回復行動ではなくこちら')
        ->and(ChecklistCategory::where('code', 'thought_habit')->value('description'))
        ->toContain('超重要');
});

it('摂取したものの「その他」は requires_text=true', function () {
    $other = ChecklistOption::whereRelation('category', 'code', 'intake')
        ->where('label', 'その他')->first();

    expect($other)->not->toBeNull()
        ->and($other->requires_text)->toBeTrue();
});

it('各カテゴリに選択肢が投入される', function () {
    expect(ChecklistOption::whereRelation('category', 'code', 'thought_habit')->count())->toBe(9)
        ->and(ChecklistOption::whereRelation('category', 'code', 'body_reaction')->count())->toBe(8)
        ->and(ChecklistOption::whereRelation('category', 'code', 'recovery_action')->count())->toBe(8)
        ->and(ChecklistOption::whereRelation('category', 'code', 'intake')->count())->toBe(5);
});

it('効果測定フラグは回復行動カテゴリのみ true', function () {
    expect(ChecklistCategory::where('tracks_effect', true)->pluck('code')->all())
        ->toBe(['recovery_action']);
});

/**
 * カテゴリ内の選択肢ラベルを sort_order 順に返す。
 *
 * @return list<string>
 */
function seededLabels(string $categoryCode): array
{
    return ChecklistOption::whereRelation('category', 'code', $categoryCode)
        ->orderBy('sort_order')->pluck('label')->all();
}

it('本番で管理画面から追加された選択肢を、本番と同じ並び順で含む', function () {
    // 管理画面で末尾に追加されたものを seeder に取り込む。
    // 位置を変えると seeder 実行時に本番の並び順が崩れる（plan-app-improvement-202610.md Step 1）。
    expect(seededLabels('thought_habit'))->toBe([
        '全部ダメだと思った（0-100思考）',
        '自分のせいだと思いすぎた',
        '相手の気持ちを勝手に想像して疲れた',
        '同時に全部解決しようとした',
        '何も考えたくなくなった',
        '特になし',
        '激しく無駄な妄想してしまう',
        '関係がめんどくさい',
        '被害妄想がひどい',
    ])->and(seededLabels('body_reaction'))->toBe([
        '睡眠が浅い',
        '胃・胸が重い',
        'イライラ',
        '無気力',
        '頭が回らない',
        '特になし',
        '力が入らない',
        '体の痛み・不調',
    ]);
});

it('回復行動に「カフェ・喫茶」が「何もできてない」「その他」より前に入る', function () {
    expect(seededLabels('recovery_action'))->toBe([
        '温泉・サウナ',
        '食事で回復',
        '音楽・バンド系',
        '一人時間',
        '軽い運動・散歩',
        'カフェ・喫茶',
        '何もできてない',
        'その他',
    ]);
});

it('体の反応「体の痛み・不調」は部位を書くため requires_text=true', function () {
    $pain = ChecklistOption::whereRelation('category', 'code', 'body_reaction')
        ->where('label', '体の痛み・不調')->first();

    expect($pain)->not->toBeNull()
        ->and($pain->requires_text)->toBeTrue()
        ->and($pain->is_none)->toBeFalse();
});

it('seeder を再実行しても選択肢の id・件数・並び順が変わらない（冪等）', function () {
    $before = ChecklistOption::orderBy('id')->get(['id', 'label', 'sort_order'])->toArray();

    Artisan::call('db:seed', ['--class' => 'ChecklistOptionSeeder', '--force' => true]);

    expect(ChecklistOption::orderBy('id')->get(['id', 'label', 'sort_order'])->toArray())->toBe($before);
});

it('seeder に無い選択肢（管理画面で追加）は再実行しても残り、並び順も変わらない', function () {
    $category = ChecklistCategory::where('code', 'recovery_action')->first();
    $adminAdded = ChecklistOption::create([
        'category_id' => $category->id,
        'label' => '管理画面で追加した行動',
        'sort_order' => 9,
        'is_active' => true,
    ]);

    Artisan::call('db:seed', ['--class' => 'ChecklistOptionSeeder', '--force' => true]);

    expect($adminAdded->fresh()->sort_order)->toBe(9)
        ->and($adminAdded->fresh()->is_active)->toBeTrue();
});
