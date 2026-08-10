<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

test('login screen can be rendered', function () {
    $response = $this->get('/login');

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('一般ユーザは管理画面を経由してログインしてもダッシュボードへ遷移する', function () {
    $this->seed(RoleSeeder::class);
    $user = User::factory()->create();

    // 未ログインで管理画面にアクセスし、intended URL をセッションに残す
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('管理者は管理画面を経由してログインすると元の管理画面へ遷移する', function () {
    $this->seed(RoleSeeder::class);
    $admin = User::factory()->admin()->create();

    $this->get(route('admin.users.index'))->assertRedirect(route('login'));

    $response = $this->post('/login', [
        'email' => $admin->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('admin.users.index'));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/logout');

    $this->assertGuest();
    $response->assertRedirect('/');
});
