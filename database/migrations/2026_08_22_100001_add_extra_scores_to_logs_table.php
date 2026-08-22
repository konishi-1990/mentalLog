<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ログ項目拡張 フェーズ1: 睡眠 / 持ち越し感 / コントロール可能度 / 勤務形態。
 *
 * 既存ログ（本番稼働分）はいずれも未入力のため、全カラムを nullable とする。
 * CHECK 制約は NULL に対して UNKNOWN となり制約を満たすため、nullable と併用できる。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logs', function (Blueprint $table) {
            $table->decimal('sleep_hours', 3, 1)->nullable();
            $table->smallInteger('sleep_quality')->nullable();
            $table->smallInteger('carryover')->nullable();
            $table->smallInteger('controllability')->nullable();
            $table->string('day_type', 20)->nullable();
        });

        // 0-10 の範囲を DB レベルで保証（CHECK制約）
        DB::statement('ALTER TABLE logs ADD CONSTRAINT logs_sleep_hours_range CHECK (sleep_hours BETWEEN 0 AND 24)');
        DB::statement('ALTER TABLE logs ADD CONSTRAINT logs_sleep_quality_range CHECK (sleep_quality BETWEEN 0 AND 10)');
        DB::statement('ALTER TABLE logs ADD CONSTRAINT logs_carryover_range CHECK (carryover BETWEEN 0 AND 10)');
        DB::statement('ALTER TABLE logs ADD CONSTRAINT logs_controllability_range CHECK (controllability BETWEEN 0 AND 10)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE logs DROP CONSTRAINT IF EXISTS logs_sleep_hours_range');
        DB::statement('ALTER TABLE logs DROP CONSTRAINT IF EXISTS logs_sleep_quality_range');
        DB::statement('ALTER TABLE logs DROP CONSTRAINT IF EXISTS logs_carryover_range');
        DB::statement('ALTER TABLE logs DROP CONSTRAINT IF EXISTS logs_controllability_range');

        Schema::table('logs', function (Blueprint $table) {
            $table->dropColumn([
                'sleep_hours',
                'sleep_quality',
                'carryover',
                'controllability',
                'day_type',
            ]);
        });
    }
};
