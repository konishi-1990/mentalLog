<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_checklist_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('log_id')->constrained('logs')->cascadeOnDelete();
            $table->foreignId('checklist_option_id')->constrained('checklist_options')->cascadeOnDelete();
            $table->text('detail_text')->nullable();   // 「その他」等の補足
            $table->timestamps();

            $table->unique(['log_id', 'checklist_option_id']);
            $table->index('checklist_option_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_checklist_selections');
    }
};
