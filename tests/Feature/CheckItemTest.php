<?php

use App\Models\CheckItem;
use App\Models\Log;
use App\Models\LogCheckItemValue;
use App\Models\User;

it('設定画面を表示できる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('check-items.index'))->assertOk();
});

it('○×項目を追加できる', function () {
    $user = User::factory()->create();
    $before = $user->checkItems()->count();

    $this->actingAs($user)
        ->post(route('check-items.store'), ['name' => '副業'])
        ->assertRedirect(route('check-items.index'));

    expect($user->checkItems()->count())->toBe($before + 1);
    $this->assertDatabaseHas('check_items', ['user_id' => $user->id, 'name' => '副業']);
});

it('追加した項目は末尾の並び順になる', function () {
    $user = User::factory()->create();
    $maxBefore = $user->checkItems()->max('sort_order');

    $this->actingAs($user)->post(route('check-items.store'), ['name' => '追加項目']);

    $added = $user->checkItems()->where('name', '追加項目')->first();
    expect($added->sort_order)->toBeGreaterThan($maxBefore);
});

it('項目名を変更できる', function () {
    $user = User::factory()->create();
    $item = $user->checkItems()->first();

    $this->actingAs($user)
        ->put(route('check-items.update', $item), ['name' => '改名後'])
        ->assertRedirect(route('check-items.index'));

    expect($item->fresh()->name)->toBe('改名後');
});

it('項目を無効化できる', function () {
    $user = User::factory()->create();
    $item = $user->checkItems()->first();

    $this->actingAs($user)
        ->put(route('check-items.update', $item), ['name' => $item->name, 'is_active' => false]);

    expect($item->fresh()->is_active)->toBeFalse();
});

it('無効化しても過去ログの回答は保持される', function () {
    $user = User::factory()->create();
    $item = $user->checkItems()->first();
    $log = Log::factory()->for($user)->create();
    $value = LogCheckItemValue::create([
        'log_id' => $log->id,
        'check_item_id' => $item->id,
        'is_on' => true,
        'detail_text' => '記録済み',
    ]);

    $this->actingAs($user)
        ->put(route('check-items.update', $item), ['name' => $item->name, 'is_active' => false]);

    $this->assertDatabaseHas('check_items', ['id' => $item->id]);
    $this->assertDatabaseHas('log_check_item_values', ['id' => $value->id, 'detail_text' => '記録済み']);
});

it('項目名は必須', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('check-items.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});

it('他人の項目は更新できない（403）', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $item = $owner->checkItems()->first();

    $this->actingAs($other)
        ->put(route('check-items.update', $item), ['name' => '乗っ取り'])
        ->assertForbidden();

    expect($item->fresh()->name)->not->toBe('乗っ取り');
});

it('項目を削除（無効化）できる', function () {
    $user = User::factory()->create();
    $item = $user->checkItems()->first();

    $this->actingAs($user)
        ->delete(route('check-items.destroy', $item))
        ->assertRedirect(route('check-items.index'));

    expect($item->fresh()->is_active)->toBeFalse();
    $this->assertDatabaseHas('check_items', ['id' => $item->id]); // 物理削除されない
});

it('他人の項目は削除できない（403）', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $item = $owner->checkItems()->first();

    $this->actingAs($other)
        ->delete(route('check-items.destroy', $item))
        ->assertForbidden();
});

it('並び替えできる', function () {
    $user = User::factory()->create();
    $items = $user->checkItems()->orderBy('sort_order')->get();
    $reversed = $items->pluck('id')->reverse()->values()->all();

    $this->actingAs($user)
        ->put(route('check-items.reorder'), ['order' => $reversed])
        ->assertRedirect(route('check-items.index'));

    $first = CheckItem::find($reversed[0]);
    expect($first->sort_order)->toBe(1);
});

it('他人の項目を含む並び替えは無視される', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherItem = $other->checkItems()->first();
    $originalOrder = $otherItem->sort_order;

    $this->actingAs($user)
        ->put(route('check-items.reorder'), ['order' => [$otherItem->id]]);

    expect($otherItem->fresh()->sort_order)->toBe($originalOrder);
});
