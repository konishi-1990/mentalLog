<?php

use App\Models\ChecklistOption;
use App\Models\Log;
use App\Models\Person;
use App\Models\User;
use App\Support\DurationBuckets;
use App\Support\EffectLevels;
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

it('追加項目を1つも送らなくても従来どおり保存できる（回帰）', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logs.store'), logPayload());

    $response->assertSessionHasNoErrors();
    $log = Log::first();
    $response->assertRedirect(route('logs.show', $log));

    $this->assertDatabaseHas('logs', [
        'id' => $log->id,
        'sleep_hours' => null,
        'sleep_quality' => null,
        'carryover' => null,
        'controllability' => null,
        'day_type' => null,
    ]);
});

it('追加項目を含めて保存すると詳細画面に表示される', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('logs.store'), logPayload([
        'sleep_hours' => 6.5,
        'sleep_quality' => 4,
        'carryover' => 8,
        'controllability' => 2,
        'day_type' => 'paid_leave',
    ]))->assertSessionHasNoErrors();

    $log = Log::first();

    $this->actingAs($user)->get(route('logs.show', $log))
        ->assertOk()
        ->assertSee('6.5')
        ->assertSee('睡眠の質')
        ->assertSee('持ち越し感')
        ->assertSee('コントロール可能度')
        ->assertSee('有給');
});

it('追加項目がすべて NULL のログでも詳細画面が表示できる（本番既存データ）', function () {
    $user = User::factory()->create();
    $log = Log::factory()->for($user)->create([
        'sleep_hours' => null,
        'sleep_quality' => null,
        'carryover' => null,
        'controllability' => null,
        'day_type' => null,
    ]);

    $this->actingAs($user)->get(route('logs.show', $log))->assertOk();
});

it('追加項目がすべて NULL のログでも一覧・編集画面が表示できる', function () {
    $user = User::factory()->create();
    $log = Log::factory()->for($user)->create(['sleep_hours' => null, 'day_type' => null]);

    $this->actingAs($user)->get(route('logs.index'))->assertOk();
    $this->actingAs($user)->get(route('logs.edit', $log))->assertOk();
});

it('新規作成フォームの追加スライダーは name を持たない（未操作なら送信されない）', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('logs.create'));

    $response->assertOk()
        ->assertSee('data-score-name="carryover"', false)
        // 先頭の空白込みで判定（data-score-name="carryover" と区別するため）
        ->assertDontSee(' name="carryover"', false)
        // 既存の必須項目は従来どおり name を持つ
        ->assertSee(' name="stress"', false);
});

it('入力済みの追加項目は編集フォームで name つきで描画される', function () {
    $user = User::factory()->create();
    $log = Log::factory()->for($user)->create(['carryover' => 8, 'day_type' => 'business_trip']);

    $this->actingAs($user)->get(route('logs.edit', $log))
        ->assertOk()
        ->assertSee(' name="carryover"', false)
        ->assertSee('value="8"', false);
});

it('intake カテゴリの選択肢を選んで保存できる', function () {
    $user = User::factory()->create();
    $option = ChecklistOption::whereRelation('category', 'code', 'intake')
        ->where('label', 'アルコール')->firstOrFail();

    $this->actingAs($user)->post(route('logs.store'), logPayload([
        'checklist' => [$option->id],
    ]))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('log_checklist_selections', [
        'checklist_option_id' => $option->id,
    ]);
});

it('intake カテゴリがログ作成フォームに表示される', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('logs.create'))
        ->assertOk()
        ->assertSee('摂取したもの')
        ->assertSee('アルコール');
});

it('intake の「どちらもなし」は他項目と同時選択できない', function () {
    $user = User::factory()->create();
    $none = ChecklistOption::whereRelation('category', 'code', 'intake')
        ->where('is_none', true)->firstOrFail();
    $other = ChecklistOption::whereRelation('category', 'code', 'intake')
        ->where('is_none', false)->firstOrFail();

    $this->actingAs($user)->post(route('logs.store'), logPayload([
        'checklist' => [$none->id, $other->id],
    ]))->assertSessionHasErrors('checklist');
});

it('tracks_effect カテゴリの選択肢にだけ計測欄が描画される', function () {
    $user = User::factory()->create();
    $recovery = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '温泉・サウナ')->firstOrFail();
    $bodyReaction = ChecklistOption::whereRelation('category', 'code', 'body_reaction')
        ->where('label', 'イライラ')->firstOrFail();

    $this->actingAs($user)->get(route('logs.create'))
        ->assertOk()
        ->assertSee("selection_meta[{$recovery->id}][effect_score]", false)
        ->assertSee("selection_meta[{$recovery->id}][duration_min]", false)
        // 効果測定をしないカテゴリには出さない
        ->assertDontSee("selection_meta[{$bodyReaction->id}]", false);
});

it('所要時間と効いた感が詳細画面にラベルで表示される', function () {
    $user = User::factory()->create();
    $option = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '温泉・サウナ')->firstOrFail();

    $this->actingAs($user)->post(route('logs.store'), logPayload([
        'checklist' => [$option->id],
        'selection_meta' => [$option->id => [
            'duration_min' => 60,
            'effect_score' => EffectLevels::STRONG,
        ]],
    ]))->assertSessionHasNoErrors();

    $this->actingAs($user)->get(route('logs.show', Log::first()))
        ->assertOk()
        ->assertSee('〜1時間')
        ->assertSee('かなり効いた');
});

it('3段階以外の effect_score（旧データ）は詳細画面で数値表記にフォールバックする', function () {
    $user = User::factory()->create();
    $option = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '温泉・サウナ')->firstOrFail();
    $log = Log::factory()->for($user)->create();
    $log->checklistSelections()->create([
        'checklist_option_id' => $option->id, 'duration_min' => 90, 'effect_score' => 7,
    ]);

    $this->actingAs($user)->get(route('logs.show', $log))
        ->assertOk()
        ->assertSee('90分')
        ->assertSee('効いた感 7/10');
});

it('計測値が未入力の選択でも詳細画面が表示できる（本番既存データ）', function () {
    $user = User::factory()->create();
    $option = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '温泉・サウナ')->firstOrFail();
    // 効いた感の必須化より前に保存された28件はこの状態（duration_min / effect_score が NULL）
    $log = Log::factory()->for($user)->create();
    $log->checklistSelections()->create(['checklist_option_id' => $option->id]);

    $this->actingAs($user)->get(route('logs.show', $log))
        ->assertOk()
        ->assertSee('温泉・サウナ');
});

it('編集画面に既存の計測値が反映される', function () {
    $user = User::factory()->create();
    $option = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '温泉・サウナ')->firstOrFail();

    $this->actingAs($user)->post(route('logs.store'), logPayload([
        'checklist' => [$option->id],
        'selection_meta' => [$option->id => [
            'duration_min' => DurationBuckets::HOUR,
            'effect_score' => EffectLevels::STRONG,
        ]],
    ]))->assertSessionHasNoErrors();

    $this->actingAs($user)->get(route('logs.edit', Log::first()))
        ->assertOk()
        ->assertSee('value="'.DurationBuckets::HOUR.'" selected', false)
        ->assertSee('value="'.EffectLevels::STRONG.'" checked', false);
});

it('相手タグをログ作成フォームで選んで保存できる', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user)->create(['name' => '上層部']);

    $this->actingAs($user)->get(route('logs.create'))
        ->assertOk()
        ->assertSee('上層部');

    $this->actingAs($user)->post(route('logs.store'), logPayload([
        'people' => [$person->id],
        'people_details' => [$person->id => '無茶な指示'],
    ]))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('log_people', [
        'log_id' => Log::first()->id,
        'person_id' => $person->id,
        'detail_text' => '無茶な指示',
    ]);
});

it('相手タグが詳細画面に表示される', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user)->create(['name' => '上層部']);
    $log = Log::factory()->for($user)->create();
    $log->people()->attach($person->id, ['detail_text' => '無茶な指示']);

    $this->actingAs($user)->get(route('logs.show', $log))
        ->assertOk()
        ->assertSee('上層部')
        ->assertSee('無茶な指示');
});

it('相手タグが未登録でもログ作成・詳細が表示できる', function () {
    $user = User::factory()->create();
    $log = Log::factory()->for($user)->create();

    $this->actingAs($user)->get(route('logs.create'))->assertOk();
    $this->actingAs($user)->get(route('logs.show', $log))->assertOk();
});

it('無効化した相手タグはフォームに出ないが詳細画面には残る', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user)->create(['name' => '退職者', 'is_active' => false]);
    $log = Log::factory()->for($user)->create();
    $log->people()->attach($person->id);

    $this->actingAs($user)->get(route('logs.create'))->assertOk()->assertDontSee('退職者');
    $this->actingAs($user)->get(route('logs.show', $log))->assertOk()->assertSee('退職者');
});

it('編集画面に既存の相手タグ選択が反映される', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user)->create(['name' => '上層部']);
    $log = Log::factory()->for($user)->create();
    $log->people()->attach($person->id, ['detail_text' => '既存メモ']);

    $this->actingAs($user)->get(route('logs.edit', $log))
        ->assertOk()
        ->assertSee('既存メモ');
});

it('3段階ボタンで選んだ効いた感が 2/5/8 で保存される', function (int $score) {
    $user = User::factory()->create();
    $option = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '温泉・サウナ')->firstOrFail();

    $this->actingAs($user)->post(route('logs.store'), logPayload([
        'checklist' => [$option->id],
        'selection_meta' => [$option->id => ['effect_score' => $score]],
    ]))->assertSessionHasNoErrors();

    $this->assertDatabaseHas('log_checklist_selections', [
        'checklist_option_id' => $option->id,
        'effect_score' => $score,
    ]);
})->with(EffectLevels::scores());

it('効いた感は3段階ラジオで描画され、既定では何も選択されていない', function () {
    $user = User::factory()->create();
    $option = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '温泉・サウナ')->firstOrFail();

    $response = $this->actingAs($user)->get(route('logs.create'));

    $response->assertOk()
        ->assertSee('効かなかった')
        ->assertSee('少し効いた')
        ->assertSee('かなり効いた');

    foreach (EffectLevels::scores() as $score) {
        $attrs = 'name="selection_meta['.$option->id.'][effect_score]" value="'.$score.'"';
        $response->assertSee($attrs, false)
            // 既定値を置くと全件同じ値が入って分散が消えるため、初期選択は無し
            ->assertDontSee($attrs.' checked', false);
    }
});

it('「何もできてない」には計測欄を描画しない', function () {
    $user = User::factory()->create();
    $none = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('is_none', true)->firstOrFail();

    $this->actingAs($user)->get(route('logs.create'))
        ->assertOk()
        ->assertSee('何もできてない')
        ->assertDontSee("selection_meta[{$none->id}]", false);
});

it('「何もできてない」は他の回復行動と同時選択できない', function () {
    $user = User::factory()->create();
    $none = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('is_none', true)->firstOrFail();
    $onsen = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '温泉・サウナ')->firstOrFail();

    $this->actingAs($user)->post(route('logs.store'), logPayload([
        'checklist' => [$none->id, $onsen->id],
        'selection_meta' => [$onsen->id => ['effect_score' => EffectLevels::SOME]],
    ]))->assertSessionHasErrors('checklist');
});

it('所要時間は選択式で描画される（自由入力ではない）', function () {
    $user = User::factory()->create();
    $option = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '温泉・サウナ')->firstOrFail();

    $response = $this->actingAs($user)->get(route('logs.create'))->assertOk();

    foreach (DurationBuckets::options() as $minutes => $label) {
        $response->assertSee($label);
    }
    $response->assertSee('name="selection_meta['.$option->id.'][duration_min]"', false);
});

it('カテゴリの説明文がフォームに表示される（摂取物への誘導）', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('logs.create'))
        ->assertOk()
        ->assertSee('コーヒー・お酒・薬はこちら')
        ->assertSee('超重要');
});
