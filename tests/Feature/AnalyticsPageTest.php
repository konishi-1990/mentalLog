<?php

use App\Models\ChecklistOption;
use App\Models\Log;
use App\Models\Person;
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

it('追加項目が全件 NULL でも分析画面は 200 を返す（本番データがこの状態）', function () {
    $user = User::factory()->create();
    Log::factory()->for($user)->count(3)->sequence(
        ['logged_on' => '2026-07-01'],
        ['logged_on' => '2026-07-02'],
        ['logged_on' => '2026-07-03'],
    )->create([
        'sleep_hours' => null,
        'sleep_quality' => null,
        'carryover' => null,
        'controllability' => null,
        'day_type' => null,
    ]);

    $this->actingAs($user)
        ->get(route('analytics.index', ['from' => '2026-07-01', 'to' => '2026-07-31']))
        ->assertOk()
        ->assertSee('相関')
        ->assertSee('入力済み 0');
});

it('ログが1件も無くても分析画面は 200 を返す', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('analytics.index'))->assertOk();
});

it('追加項目が入力済みなら分析画面に相関と勤務形態別平均が出る', function () {
    $user = User::factory()->create();
    foreach ([[0, 10], [5, 5], [10, 0]] as $i => [$stress, $mental]) {
        Log::factory()->for($user)->create([
            'logged_on' => '2026-07-0'.($i + 1),
            'stress' => $stress,
            'mental_capacity' => $mental,
            'sleep_hours' => 6.0 + $i,
            'day_type' => 'weekday',
        ]);
    }

    $this->actingAs($user)
        ->get(route('analytics.index', ['from' => '2026-07-01', 'to' => '2026-07-31']))
        ->assertOk()
        ->assertSee('勤務形態')
        ->assertSee('平日');
});

it('相手タグ頻度・摂取物頻度・回復効果が分析画面に出る', function () {
    $user = User::factory()->create();
    $person = Person::factory()->for($user)->create(['name' => '上層部']);
    $log = Log::factory()->for($user)->create(['logged_on' => '2026-07-01']);
    $log->people()->attach($person->id);

    $intake = ChecklistOption::whereRelation('category', 'code', 'intake')
        ->where('label', 'アルコール')->firstOrFail();
    $recovery = ChecklistOption::whereRelation('category', 'code', 'recovery_action')
        ->where('label', '温泉・サウナ')->firstOrFail();
    $log->checklistSelections()->create(['checklist_option_id' => $intake->id]);
    $log->checklistSelections()->create([
        'checklist_option_id' => $recovery->id, 'duration_min' => 90, 'effect_score' => 8,
    ]);

    $this->actingAs($user)
        ->get(route('analytics.index', ['from' => '2026-07-01', 'to' => '2026-07-31']))
        ->assertOk()
        ->assertSee('上層部')
        ->assertSee('アルコール')
        ->assertSee('温泉・サウナ');
});

it('睡眠が全件 NULL でもダッシュボードは 200 を返す', function () {
    $user = User::factory()->create();
    Log::factory()->for($user)->create(['logged_on' => now()->format('Y-m-d'), 'sleep_hours' => null]);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

it('ログが1件も無くてもダッシュボードは 200 を返す', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

it('睡眠が入力済みならダッシュボードに睡眠×余裕の相関カードが出る', function () {
    $user = User::factory()->create();
    foreach ([[4.0, 2], [6.0, 5], [8.0, 9]] as $i => [$sleep, $mental]) {
        Log::factory()->for($user)->create([
            'logged_on' => now()->subDays($i)->format('Y-m-d'),
            'sleep_hours' => $sleep,
            'mental_capacity' => $mental,
        ]);
    }

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('睡眠')
        ->assertSee('メンタル余裕');
});

it('記録率と未記録日のヒートマップが分析画面に出る', function () {
    $user = User::factory()->create();
    Log::factory()->for($user)->create(['logged_on' => '2026-07-01']);
    Log::factory()->for($user)->create(['logged_on' => '2026-07-05']);

    $this->actingAs($user)
        ->get(route('analytics.index', ['from' => '2026-07-01', 'to' => '2026-07-10']))
        ->assertOk()
        ->assertSee('記録率')
        ->assertSee('20%')          // 2 / 10 日
        ->assertSee('最長');
});

it('期間内にログが無くてもヒートマップで落ちない', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('analytics.index', ['from' => '2026-07-01', 'to' => '2026-07-10']))
        ->assertOk()
        ->assertSee('記録率');
});
