<?php

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->admin = User::factory()->admin()->create();
});

it('一般ユーザは管理ユーザ画面にアクセスできない（403）', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
});

it('管理者はユーザ管理画面を表示できる', function () {
    $this->actingAs($this->admin)->get(route('admin.users.index'))->assertOk();
});

it('管理者はユーザを作成できる', function () {
    $userRole = Role::where('code', 'user')->first();

    $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => '新人',
        'email' => 'newbie@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role_id' => $userRole->id,
        'is_active' => true,
    ])->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseHas('users', ['email' => 'newbie@example.com']);
});

it('管理者はユーザのロールを変更できる', function () {
    $user = User::factory()->create();
    $adminRole = Role::where('code', 'admin')->first();

    $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
        'name' => $user->name,
        'email' => $user->email,
        'role_id' => $adminRole->id,
        'is_active' => true,
    ]);

    expect($user->fresh()->isAdmin())->toBeTrue();
});

it('管理者はユーザを無効化できる', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)->put(route('admin.users.update', $user), [
        'name' => $user->name,
        'email' => $user->email,
        'role_id' => $user->role_id,
        'is_active' => false,
    ]);

    expect($user->fresh()->is_active)->toBeFalse();
});

it('メールアドレスは重複できない', function () {
    $existing = User::factory()->create(['email' => 'taken@example.com']);

    $this->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'dup',
        'email' => 'taken@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'role_id' => $existing->role_id,
    ])->assertSessionHasErrors('email');
});

it('管理者はユーザを削除できる', function () {
    $user = User::factory()->create();

    $this->actingAs($this->admin)->delete(route('admin.users.destroy', $user))
        ->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseMissing('users', ['id' => $user->id]);
});
