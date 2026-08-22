<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 回復行動の計測（所要時間・効いた感）。
 * 既存の選択レコードは未入力のため nullable とする。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('log_checklist_selections', function (Blueprint $table) {
            $table->smallInteger('duration_min')->nullable();
            $table->smallInteger('effect_score')->nullable();
        });

        DB::statement('ALTER TABLE log_checklist_selections ADD CONSTRAINT log_checklist_selections_duration_min_range CHECK (duration_min >= 0)');
        DB::statement('ALTER TABLE log_checklist_selections ADD CONSTRAINT log_checklist_selections_effect_score_range CHECK (effect_score BETWEEN 0 AND 10)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE log_checklist_selections DROP CONSTRAINT IF EXISTS log_checklist_selections_duration_min_range');
        DB::statement('ALTER TABLE log_checklist_selections DROP CONSTRAINT IF EXISTS log_checklist_selections_effect_score_range');

        Schema::table('log_checklist_selections', function (Blueprint $table) {
            $table->dropColumn(['duration_min', 'effect_score']);
        });
    }
};
