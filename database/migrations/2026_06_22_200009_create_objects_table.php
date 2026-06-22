<?php

use App\Enums\ConfidenceLevel;
use App\Enums\ObjectStatus;
use App\Enums\ObjectType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The canonical object graph spine (PRD §12). One row per artefact; all
     * common metadata here, type-specific fields in the JSON `attributes` bag.
     * Documents are generated views of these rows.
     */
    public function up(): void
    {
        Schema::create('objects', function (Blueprint $table) {
            $table->id();
            $table->string('ref');                                  // permanent human ID, e.g. SRS-FR-0102
            $table->enum('type', array_column(ObjectType::cases(), 'value'));
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('attributes')->nullable();

            // Scope tuple (ACL scopes without joins).
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('stage_id')->nullable()->constrained('stages')->nullOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('requirement_sessions')->nullOnDelete();

            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->nullable();
            $table->unsignedBigInteger('source_object_id')->nullable();   // self-ref FK below

            $table->enum('status', array_column(ObjectStatus::cases(), 'value'))
                ->default(ObjectStatus::NEEDS_CONFIRMATION->value);
            $table->enum('confidence', array_column(ConfidenceLevel::cases(), 'value'))->nullable();
            $table->enum('impact', ['low', 'medium', 'high', 'critical'])->nullable();

            $table->unsignedInteger('current_version')->default(1);
            $table->date('effective_date')->nullable();
            $table->foreignId('baseline_id')->nullable()->constrained('stage_baselines')->nullOnDelete();
            $table->unsignedBigInteger('approval_id')->nullable();        // self-ref FK below

            $table->softDeletes();
            $table->timestamps();

            $table->unique(['project_id', 'ref']);
            $table->index(['project_id', 'type', 'status']);
            $table->index(['tenant_id', 'project_id']);
            $table->index(['module_id', 'stage_id', 'session_id']);
            $table->index(['type', 'ref']);
        });

        // Self-referencing FKs (table must exist first).
        Schema::table('objects', function (Blueprint $table) {
            $table->foreign('source_object_id')->references('id')->on('objects')->nullOnDelete();
            $table->foreign('approval_id')->references('id')->on('objects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('objects');
    }
};
