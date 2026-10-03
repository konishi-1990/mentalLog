<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 体力（stamina）を凍結し、疲労度（fatigue・高いと疲れている）に置き換える。
 *
 * 体力は 2026-09-08 以降「4 か 5」しか入らず、身体（脚）の不調と混ざっていた
 * （report-202610.md §2）。過去ログの意味を書き換えないよう、ラベル変更ではなく
 * 新カラムを追加し、stamina は値を残したまま NOT NULL だけ外す（plan 要判断 B-1）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->smallInteger('fatigue')->nullable();
            $table->smallInteger('stamina')->nullable()->change();
        });

        DB::statement('ALTER TABLE logs ADD CONSTRAINT logs_fatigue_range CHECK (fatigue BETWEEN 0 AND 10)');
    }

    /**
     * stamina の NOT NULL は戻さない（凍結後の新規ログが NULL を持つため戻すと失敗する）。
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE logs DROP CONSTRAINT IF EXISTS logs_fatigue_range');

        Schema::table('logs', function (Blueprint $table) {
            $table->dropColumn('fatigue');
        });
    }
};
