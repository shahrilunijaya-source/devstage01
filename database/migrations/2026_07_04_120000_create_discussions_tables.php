<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Contextual discussions (spec §12): threads attachable to a Project, Stage,
     * Session, StageBaseline or EngObject. Visibility separates internal notes
     * from client-visible conversation; blocking discussions hold the stage
     * gate. tenant/project ids are denormalized so isolation filters never need
     * the morph target.
     */
    public function up(): void
    {
        Schema::create('discussions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('discussable_type');
            $table->unsignedBigInteger('discussable_id');
            $table->string('title');
            $table->enum('visibility', ['internal', 'client'])->default('internal');
            $table->boolean('blocking')->default(false);
            $table->enum('status', ['open', 'resolved'])->default('open');
            $table->foreignId('opened_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['discussable_type', 'discussable_id']);
            $table->index(['project_id', 'status', 'blocking']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('discussion_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discussion_id')->constrained('discussions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            // Ref of the graph object minted from this comment (convert-to-X).
            $table->string('converted_ref')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_comments');
        Schema::dropIfExists('discussions');
    }
};
