<?php

use App\Models\Log;
use App\Models\User;
use Database\Seeders\ChecklistCategorySeeder;
use Database\Seeders\ChecklistOptionSeeder;

beforeEach(function () {
    $this->seed([ChecklistCategorySeeder::class, ChecklistOptionSeeder::class]);
});

it('未ログインでは分析画面にアクセスできない', function () {
    $this->get(route('analytics.index'))->assertRedirect(route('login'));
});

it('ログイン済みユーザは分析画面を表示できる', function () {
    $user = User::factory()->create();
    Log::factory()->for($user)->create();

    $this->actingAs($user)->get(route('analytics.index'))->assertOk();
});

it('ダッシュボードを表示できる', function () {
    $user = User::factory()->create();
    Log::factory()->for($user)->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});
