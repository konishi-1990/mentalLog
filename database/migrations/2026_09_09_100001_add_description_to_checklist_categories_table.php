<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * カテゴリごとの説明文。
 *
 * `intake`（摂取したもの）はマスタがあるのに選択0件で、飲酒が回復行動の「その他」に
 * 紛れていた（docs/analysis/report-202609.md §1 / §4 #6）。誘導文を出して導線を直す。
 *
 * あわせて form.blade.php が `code === 'thought_habit'` を直書きして「超重要」を
 * 出していた規約違反（カテゴリ code をブレードに直書きしない）も、この列に寄せて解消する。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_categories', function (Blueprint $table) {
            $table->text('description')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('checklist_categories', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
