<?php

namespace App\Support;

/**
 * 回復行動にかけた時間（log_checklist_selections.duration_min）の選択肢。
 *
 * 分の自由入力は本番28日分すべて未入力だった。効果測定に必須なのは「効いた感」のほうなので、
 * こちらは任意入力のまま、代表値を選ぶだけにして負担を下げる。
 * 保存値は分（既存カラムのまま）。
 */
class DurationBuckets
{
    public const QUARTER_HOUR = 15;

    public const HALF_HOUR = 30;

    public const HOUR = 60;

    public const OVER_HOUR = 120;

    /**
     * @return array<int, string> 保存値（分） => 表示ラベル（表示順）
     */
    public static function options(): array
    {
        return [
            self::QUARTER_HOUR => '〜15分',
            self::HALF_HOUR => '〜30分',
            self::HOUR => '〜1時間',
            self::OVER_HOUR => '1時間以上',
        ];
    }

    /**
     * @return list<int> 許可される保存値（分）
     */
    public static function minutes(): array
    {
        return array_keys(self::options());
    }

    public static function label(?int $minutes): ?string
    {
        return self::options()[$minutes] ?? null;
    }

    /**
     * 表示用。選択肢に載らない値（選択式化より前に入った値）は「N分」へフォールバックする。
     */
    public static function describe(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        return self::label($minutes) ?? "{$minutes}分";
    }
}
