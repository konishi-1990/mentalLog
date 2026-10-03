<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 起きたときの余裕（0〜10・任意）。
 *
 * 効いた感（その場の評価）と翌日の余裕が一致しなかったため、朝の1点で
 * 「どれだけ持ち越したか」を直接測る（report-202610.md §3 ⑥ / §4 #8）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->smallInteger('morning_capacity')->nullable();
        });

        DB::statement('ALTER TABLE logs ADD CONSTRAINT logs_morning_capacity_range CHECK (morning_capacity BETWEEN 0 AND 10)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE logs DROP CONSTRAINT IF EXISTS logs_morning_capacity_range');

        Schema::table('logs', function (Blueprint $table) {
            $table->dropColumn('morning_capacity');
        });
    }
};
