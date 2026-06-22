<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * URSB requirement sessions. Named `requirement_sessions` to avoid colliding
     * with Laravel's auth `sessions` table (database session driver is active).
     * Session dimension = Module / Champion / Process / Domain / Location (PRD §5).
     */
    public function up(): void
    {
        Schema::create('requirement_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained('stages')->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();   // denormalized
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();  // denormalized, ACL
            $table->string('title');
            $table->foreignId('champion_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('process')->nullable();
            $table->string('domain')->nullable();
            $table->string('location')->nullable();
            $table->enum('status', ['draft', 'in_progress', 'submitted', 'approved', 'rejected'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['stage_id', 'status']);
            $table->index(['project_id', 'module_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requirement_sessions');
    }
};
