<?php

use App\Enums\LifecycleStage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deliverables (D01-D19), question banks, matrices, coverage rules, stage
     * gates, quality rules, glossary — the structured methodology (PRD §7.1).
     */
    public function up(): void
    {
        Schema::create('knowledge_book_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('knowledge_book_id')->constrained()->cascadeOnDelete();
            $table->enum('item_type', [
                'deliverable', 'document_section', 'question', 'matrix', 'model',
                'coverage_rule', 'stage_gate', 'quality_rule', 'glossary_term',
            ]);
            $table->string('code');                  // "D07", "BRS-Q-014", "GATE-URS-01"
            $table->enum('stage', array_column(LifecycleStage::cases(), 'value'))->nullable();
            $table->unsignedBigInteger('parent_id')->nullable();   // self-ref FK below
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['knowledge_book_id', 'code']);
            $table->index(['knowledge_book_id', 'item_type', 'stage']);
        });

        Schema::table('knowledge_book_items', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('knowledge_book_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_book_items');
    }
};
