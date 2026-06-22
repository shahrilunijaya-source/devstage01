<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Pins each project to a KB version + per-stage overrides (PRD §7.4). */
    public function up(): void
    {
        Schema::create('project_knowledge_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('knowledge_book_id')->constrained('knowledge_books');
            $table->foreignId('methodology_book_id')->nullable()->constrained('knowledge_books');
            $table->timestamp('pinned_at')->useCurrent();
            $table->foreignId('pinned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('overrides')->nullable();
            $table->timestamps();

            $table->unique('project_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_knowledge_pins');
    }
};
