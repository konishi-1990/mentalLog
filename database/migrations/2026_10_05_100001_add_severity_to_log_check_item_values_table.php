<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ストレス源の強度（1=軽い / 2=中くらい / 3=重い）。
 * ○×だけでは「仕事は常に○」で変動が説明できないため（report-202610.md §4 #6）。
 * 既存の回答には強度が無いため nullable とする。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('log_check_item_values', function (Blueprint $table) {
            $table->smallInteger('severity')->nullable();
        });

        DB::statement('ALTER TABLE log_check_item_values ADD CONSTRAINT log_check_item_values_severity_range CHECK (severity BETWEEN 1 AND 3)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE log_check_item_values DROP CONSTRAINT IF EXISTS log_check_item_values_severity_range');

        Schema::table('log_check_item_values', function (Blueprint $table) {
            $table->dropColumn('severity');
        });
    }
};
