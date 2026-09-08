<?php

use App\Models\ChecklistOption;
use App\Models\Log;
use App\Models\LogCheckItemValue;
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

it('ログが0件でも新しい分析カードすべてで 200 を返す', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('analytics.index', ['from' => '2026-07-01', 'to' => '2026-07-31']))
        ->assertOk()
        ->assertSee('重なり')
        ->assertSee('頭の中のクセの個数')
        ->assertSee('前日からの持ち越し')
        ->assertSee('入力のタイミング');
});

it('重なり数カードに件数ごとの平均が出る', function () {
    $user = User::factory()->create();
    $items = $user->checkItems()->orderBy('sort_order')->get();

    $log = Log::factory()->for($user)->create([
        'logged_on' => '2026-07-01', 'stress' => 8, 'mental_capacity' => 4,
    ]);
    LogCheckItemValue::create(['log_id' => $log->id, 'check_item_id' => $items[0]->id, 'is_on' => true]);
    LogCheckItemValue::create(['log_id' => $log->id, 'check_item_id' => $items[1]->id, 'is_on' => true]);

    $this->actingAs($user)
        ->get(route('analytics.index', ['from' => '2026-07-01', 'to' => '2026-07-31']))
        ->assertOk()
        ->assertSee('2件')
        ->assertSee('4.0');
});

it('相関表は n の多い順に並び、n が少ない行に参考値バッジが付く', function () {
    $user = User::factory()->create();
    foreach (range(0, 11) as $i) {
        Log::factory()->for($user)->create([
            'logged_on' => '2026-07-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT),
            'stress' => $i % 2 === 0 ? 2 : 8,
            'mental_capacity' => $i % 2 === 0 ? 8 : 2,
            'carryover' => $i < 3 ? $i : null,
        ]);
    }

    $response = $this->actingAs($user)
        ->get(route('analytics.index', ['from' => '2026-07-01', 'to' => '2026-07-31']))
        ->assertOk()
        ->assertSee('参考値');

    // n=12 のストレス×余裕が n=3 の持ち越し感より先に出る
    $html = $response->getContent();
    expect(strpos($html, 'ストレス × メンタル余裕'))
        ->toBeLessThan(strpos($html, '持ち越し感 × メンタル余裕'));
});

it('欠測バイアスと入力ラグが記録率カードに出る', function () {
    $user = User::factory()->create();
    Log::factory()->for($user)->create([
        'logged_on' => '2026-07-01', 'stress' => 6, 'created_at' => '2026-07-01 15:00:00',
    ]);
    Log::factory()->for($user)->create([
        'logged_on' => '2026-07-02', 'stress' => 9, 'created_at' => '2026-07-03 10:00:00',
    ]);

    $this->actingAs($user)
        ->get(route('analytics.index', ['from' => '2026-07-01', 'to' => '2026-07-10']))
        ->assertOk()
        ->assertSee('翌日も記録した日')
        ->assertSee('翌日が欠測だった日')
        ->assertSee('当日入力')
        ->assertSee('翌日入力');
});

it('未記録が3日以上続いたらダッシュボードにバナーが出る', function () {
    $user = User::factory()->create();
    Log::factory()->for($user)->create(['logged_on' => now()->subDays(5)->format('Y-m-d')]);

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertSee('日続けて記録がありません');
});

it('記録が続いていればダッシュボードにバナーは出ない', function () {
    $user = User::factory()->create();
    foreach (range(0, 5) as $i) {
        Log::factory()->for($user)->create(['logged_on' => now()->subDays($i)->format('Y-m-d')]);
    }

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('日続けて記録がありません');
});

it('ログが1件も無ければダッシュボードにバナーは出ない（初回利用時に脅さない）', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('日続けて記録がありません');
});
