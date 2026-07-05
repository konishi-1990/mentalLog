<?php

namespace App\Support;

/**
 * ユーザ作成時に複製される○×項目（ストレス源カテゴリ）の既定テンプレート。
 * require.md FR-21 に準拠。
 */
class DefaultCheckItems
{
    /**
     * @return list<string> 表示順どおりの項目名
     */
    public static function names(): array
    {
        return [
            '仕事',
            'バンド関係',
            'コミュニティ',
            '自分の疲労',
            'その他',
        ];
    }
}
