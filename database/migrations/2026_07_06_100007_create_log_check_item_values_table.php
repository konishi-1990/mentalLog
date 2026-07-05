<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_check_item_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('log_id')->constrained('logs')->cascadeOnDelete();
            $table->foreignId('check_item_id')->constrained('check_items')->cascadeOnDelete();
            $table->boolean('is_on')->default(false);   // ○=true / ×=false
            $table->text('detail_text')->nullable();    // ○のときの内容補足
            $table->timestamps();

            $table->unique(['log_id', 'check_item_id']);
            $table->index(['check_item_id', 'is_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_check_item_values');
    }
};
