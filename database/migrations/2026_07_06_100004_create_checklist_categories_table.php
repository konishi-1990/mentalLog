<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();   // thought_habit / body_reaction / recovery_action
            $table->string('name', 50);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_categories');
    }
};
