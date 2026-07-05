<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('logged_on');
            $table->smallInteger('stress');
            $table->smallInteger('stamina');
            $table->smallInteger('mental_capacity');
            $table->text('hardest_text')->nullable();
            $table->text('summary_text')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'logged_on']);
            $table->index(['user_id', 'logged_on']);
        });

        // 0-10 の範囲を DB レベルで保証（CHECK制約）
        DB::statement('ALTER TABLE logs ADD CONSTRAINT logs_stress_range CHECK (stress BETWEEN 0 AND 10)');
        DB::statement('ALTER TABLE logs ADD CONSTRAINT logs_stamina_range CHECK (stamina BETWEEN 0 AND 10)');
        DB::statement('ALTER TABLE logs ADD CONSTRAINT logs_mental_capacity_range CHECK (mental_capacity BETWEEN 0 AND 10)');
    }

    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};
