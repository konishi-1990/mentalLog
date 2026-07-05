<?php

use App\Models\User;

/**
 * フェーズ0: TDD 土台の疎通確認スモークテスト。
 * 認証で保護されたダッシュボードが、未ログイン時にログインへ誘導されることを確認する。
 */
it('未ログインではダッシュボードからログインへリダイレクトされる', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

it('ログイン済みユーザはダッシュボードを表示できる', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();
});
