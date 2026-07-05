<?php

use App\Models\CheckItem;
use App\Models\User;

it('ユーザ作成時に既定の○×項目が複製される', function () {
    $user = User::factory()->create();

    expect($user->checkItems()->count())->toBe(5)
        ->and($user->checkItems()->pluck('name')->all())
        ->toContain('仕事', 'バンド関係', 'コミュニティ', '自分の疲労', 'その他');
});

it('○×項目はユーザごとに独立して複製される', function () {
    $a = User::factory()->create();
    $b = User::factory()->create();

    expect(CheckItem::where('user_id', $a->id)->count())->toBe(5)
        ->and(CheckItem::where('user_id', $b->id)->count())->toBe(5);
});

it('複製された○×項目は既定で有効・並び順が付与される', function () {
    $user = User::factory()->create();

    expect($user->checkItems()->where('is_active', true)->count())->toBe(5)
        ->and($user->checkItems()->orderBy('sort_order')->first()->name)->toBe('仕事');
});
