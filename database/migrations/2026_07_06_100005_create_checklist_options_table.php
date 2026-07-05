<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('checklist_categories')->cascadeOnDelete();
            $table->string('label', 150);
            $table->boolean('requires_text')->default(false);
            $table->boolean('is_none')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['category_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_options');
    }
};
