<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AI Refinement Engine (spec §10): suggestions are stored proposals a human
     * explicitly accepts, edits or rejects — AI never overwrites content.
     * ai_usage_log records token spend per call (spec §11 cost monitoring).
     */
    public function up(): void
    {
        Schema::create('ai_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('object_id')->constrained('objects')->cascadeOnDelete();
            $table->string('action');                      // improve|challenge|generate_ac|find_missing|check_objective
            $table->enum('status', ['proposed', 'accepted', 'accepted_edited', 'rejected'])->default('proposed');
            $table->json('payload');                       // action-shaped: proposal, rationale, questions, criteria, classification...
            $table->string('model')->nullable();
            $table->string('prompt_version');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamps();

            $table->index(['object_id', 'status']);
            $table->index(['project_id', 'action']);
        });

        Schema::create('ai_usage_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
            $table->string('feature');                     // refine.improve, guardian.assess, chat.ask ...
            $table->string('model')->nullable();
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'feature']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_log');
        Schema::dropIfExists('ai_suggestions');
    }
};
