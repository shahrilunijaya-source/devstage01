<?php

use App\Enums\LifecycleStage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tenant/project-isolated knowledge base (PRD §7.3). */
    public function up(): void
    {
        Schema::create('project_knowledge_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->enum('item_type', [
                'tailored_question', 'matrix', 'approved_assumption',
                'reference_doc', 'lesson_learned', 'ai_learning',
            ]);
            $table->foreignId('source_kb_item_id')->nullable()->constrained('knowledge_book_items')->nullOnDelete();
            $table->enum('stage', array_column(LifecycleStage::cases(), 'value'))->nullable();
            $table->foreignId('module_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->enum('status', ['draft', 'approved', 'retired'])->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'item_type', 'stage']);
            $table->index(['tenant_id', 'project_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_knowledge_items');
    }
};
