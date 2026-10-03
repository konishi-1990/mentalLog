<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * 2026-10 のフォーム改修（強度の必須化・体力 → 疲労度）を適用する対象日の境目。
 *
 * これより前の日付のログは改修前に書かれたもの。編集しても新しい必須項目を求めず、
 * 既定値が当時の値として保存されないようにする。リリース日に合わせて設定する。
 */
class FormRevision
{
    public const SINCE = '2026-10-05';

    /**
     * 対象日のログに改修後の必須項目を求めるか。
     */
    public static function appliesTo(?string $loggedOn): bool
    {
        if (blank($loggedOn)) {
            return true;
        }

        try {
            return Carbon::parse($loggedOn)->startOfDay()->gte(Carbon::parse(self::SINCE));
        } catch (\Throwable) {
            return true; // 日付として読めない値は logged_on のバリデーションに任せる
        }
    }
}
