<?php

namespace App\Support;

/**
 * 回復行動の「効いた感」（log_checklist_selections.effect_score）の入力段階。
 *
 * 0〜10 の自由入力は本番28日分すべて未入力だった（docs/analysis/report-202609.md §1）。
 * 空欄でも送信できるうえ、自分で数字を決める必要があったのが原因。
 *
 * かわりに3段階から選ばせる。既定値は置かない —— 既定値があると全件同じ値が入り、
 * 入力率だけ上がって分散がゼロになる（分析上は未入力と同じ）。
 * 保存値は既存カラム（smallint / CHECK 0..10）の範囲に収めるため、カラム変更は不要。
 */
class EffectLevels
{
    public const NONE = 2;

    public const SOME = 5;

    public const STRONG = 8;

    /**
     * @return array<int, string> 保存値 => 表示ラベル（表示順）
     */
    public static function options(): array
    {
        return [
            self::NONE => '効かなかった',
            self::SOME => '少し効いた',
            self::STRONG => 'かなり効いた',
        ];
    }

    /**
     * @return list<int> 許可される保存値
     */
    public static function scores(): array
    {
        return array_keys(self::options());
    }

    /**
     * 表示用ラベル。3段階以外の値・未入力は null を返す。
     */
    public static function label(?int $score): ?string
    {
        return self::options()[$score] ?? null;
    }

    /**
     * 表示用。3段階に載らない値（3段階化より前に入った値）は数値表記へフォールバックする。
     */
    public static function describe(?int $score): ?string
    {
        if ($score === null) {
            return null;
        }

        return self::label($score) ?? "{$score}/10";
    }

    /**
     * 平均値に最も近い段階のラベル。集計結果の表示に使う。
     */
    public static function nearestLabel(?float $average): ?string
    {
        if ($average === null) {
            return null;
        }

        $nearest = collect(self::scores())
            ->sortBy(fn (int $score) => abs($score - $average))
            ->first();

        return self::label($nearest);
    }
}
