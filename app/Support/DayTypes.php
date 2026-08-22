<?php

namespace App\Support;

/**
 * ログの勤務形態（logs.day_type）の定義。
 * 曜日は logged_on から導出できるが、有給・出張・祝日は判別できないため項目として持つ。
 */
class DayTypes
{
    public const WEEKDAY = 'weekday';

    public const HOLIDAY = 'holiday';

    public const PAID_LEAVE = 'paid_leave';

    public const BUSINESS_TRIP = 'business_trip';

    public const OTHER = 'other';

    /**
     * @return array<string, string> code => 表示ラベル（表示順）
     */
    public static function options(): array
    {
        return [
            self::WEEKDAY => '平日',
            self::HOLIDAY => '休日',
            self::PAID_LEAVE => '有給',
            self::BUSINESS_TRIP => '出張',
            self::OTHER => 'その他',
        ];
    }

    /**
     * @return list<string> 許可される code
     */
    public static function codes(): array
    {
        return array_keys(self::options());
    }

    /**
     * 表示用ラベル。未入力・未知の値は null を返す。
     */
    public static function label(?string $code): ?string
    {
        return self::options()[$code] ?? null;
    }
}
