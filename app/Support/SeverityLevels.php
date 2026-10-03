<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * ストレス源の強度（log_check_item_values.severity）。
 *
 * ○×だけでは「仕事は常に○」になり、日ごとの変動を説明できない（report-202610.md §4 #6）。
 * 効いた感と同じく既定値なしの3段階ボタンにする（3段階ボタンは入力率100%の実績がある）。
 */
class SeverityLevels
{
    public const LIGHT = 1;

    public const MODERATE = 2;

    public const HEAVY = 3;

    /**
     * この日付以降のログでは、○の項目に強度を必須にする。
     * それより前のログ（過去ログの編集）は強度が無いまま保存できる。
     */
    public const REQUIRED_FROM = '2026-10-05';

    /**
     * @return array<int, string> 保存値 => 表示ラベル（表示順）
     */
    public static function options(): array
    {
        return [
            self::LIGHT => '軽い',
            self::MODERATE => '中くらい',
            self::HEAVY => '重い',
        ];
    }

    /**
     * @return list<int>
     */
    public static function values(): array
    {
        return array_keys(self::options());
    }

    public static function label(?int $severity): ?string
    {
        return self::options()[$severity] ?? null;
    }

    /**
     * 対象日のログで強度が必須か。
     */
    public static function requiredOn(string $loggedOn): bool
    {
        return Carbon::parse($loggedOn)->startOfDay()->gte(Carbon::parse(self::REQUIRED_FROM));
    }
}
