<?php

use App\Models\CheckItem;
use App\Models\Log;
use App\Models\Role;
use App\Models\User;

it('admin ロールのユーザは isAdmin() が true', function () {
    $user = User::factory()->admin()->create();

    expect($user->isAdmin())->toBeTrue();
});

it('一般ユーザは isAdmin() が false', function () {
    $user = User::factory()->create();

    expect($user->isAdmin())->toBeFalse();
});

it('user は role に属する', function () {
    $user = User::factory()->create();

    expect($user->role)->toBeInstanceOf(Role::class);
});

it('user は logs を持つ', function () {
    $user = User::factory()->create();
    Log::factory()->for($user)->create();

    expect($user->logs)->toHaveCount(1)
        ->and($user->logs->first())->toBeInstanceOf(Log::class);
});

it('user は checkItems を持つ', function () {
    $user = User::factory()->create();

    expect($user->checkItems->first())->toBeInstanceOf(CheckItem::class);
});
