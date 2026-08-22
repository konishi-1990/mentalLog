<?php

use App\Models\Log;
use App\Models\Person;
use App\Models\User;

it('相手タグの設定画面を表示できる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('people.index'))->assertOk();
});

it('未ログインでは相手タグの設定画面にアクセスできない', function () {
    $this->get(route('people.index'))->assertRedirect(route('login'));
});

it('新規ユーザには相手タグが1件も作られない（人は完全にユーザ固有）', function () {
    $user = User::factory()->create();

    expect($user->people()->count())->toBe(0);
});

it('相手タグを追加できる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('people.store'), ['name' => '上層部'])
        ->assertRedirect(route('people.index'));

    $this->assertDatabaseHas('people', ['user_id' => $user->id, 'name' => '上層部']);
});

it('追加した相手タグは末尾の並び順になる', function () {
    $user = User::factory()->create();
    Person::factory()->for($user)->create(['sort_order' => 1]);

    $this->actingAs($user)->post(route('people.store'), ['name' => '追加分']);

    expect($user->people()->where('name', '追加分')->first()->sort_order)->toBe(2);
});

it('相手タグ名を変更できる', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user)->create(['name' => '旧名']);

    $this->actingAs($user)
        ->put(route('people.update', $person), ['name' => '新名'])
        ->assertRedirect(route('people.index'));

    expect($person->fresh()->name)->toBe('新名');
});

it('相手タグ名は必須', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('people.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('相手タグを論理削除できる（物理削除しない）', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user)->create();

    $this->actingAs($user)
        ->delete(route('people.destroy', $person))
        ->assertRedirect(route('people.index'));

    expect($person->fresh()->is_active)->toBeFalse();
    $this->assertDatabaseHas('people', ['id' => $person->id]);
});

it('無効化しても過去ログの紐づけは保持される', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user)->create();
    $log = Log::factory()->for($user)->create();
    $log->people()->attach($person->id, ['detail_text' => '記録済み']);

    $this->actingAs($user)->delete(route('people.destroy', $person));

    $this->assertDatabaseHas('log_people', [
        'log_id' => $log->id,
        'person_id' => $person->id,
        'detail_text' => '記録済み',
    ]);
});

it('他人の相手タグは更新できない（403）', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $person = Person::factory()->for($owner)->create(['name' => '本人のもの']);

    $this->actingAs($other)
        ->put(route('people.update', $person), ['name' => '乗っ取り'])
        ->assertForbidden();

    expect($person->fresh()->name)->toBe('本人のもの');
});

it('他人の相手タグは削除できない（403）', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $person = Person::factory()->for($owner)->create();

    $this->actingAs($other)
        ->delete(route('people.destroy', $person))
        ->assertForbidden();

    expect($person->fresh()->is_active)->toBeTrue();
});

it('並び替えできる', function () {
    $user = User::factory()->create();
    $a = Person::factory()->for($user)->create(['sort_order' => 1]);
    $b = Person::factory()->for($user)->create(['sort_order' => 2]);

    $this->actingAs($user)
        ->put(route('people.reorder'), ['order' => [$b->id, $a->id]])
        ->assertRedirect(route('people.index'));

    expect($b->fresh()->sort_order)->toBe(1)
        ->and($a->fresh()->sort_order)->toBe(2);
});

it('他人の ID を混ぜた並び替えは無視される', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherPerson = Person::factory()->for($other)->create(['sort_order' => 5]);

    $this->actingAs($user)
        ->put(route('people.reorder'), ['order' => [$otherPerson->id]]);

    expect($otherPerson->fresh()->sort_order)->toBe(5);
});
