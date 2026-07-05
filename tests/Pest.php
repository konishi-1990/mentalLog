<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * ログ登録の有効なペイロード雛形を返す（テスト用）。
 */
function logPayload(array $overrides = []): array
{
    return array_merge([
        'logged_on' => '2026-07-06',
        'stress' => 5,
        'stamina' => 6,
        'mental_capacity' => 7,
        'hardest_text' => '締切対応がきつかった',
        'summary_text' => 'なんとか乗り切った',
        'check_items' => [],       // [check_item_id => ['is_on'=>bool, 'detail_text'=>?string]]
        'checklist' => [],         // [checklist_option_id, ...]
        'checklist_details' => [], // [checklist_option_id => string]
    ], $overrides);
}
