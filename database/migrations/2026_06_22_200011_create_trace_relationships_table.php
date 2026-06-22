<?php

use App\Enums\RelationType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Directed trace edges between objects (PRD §12.3). */
    public function up(): void
    {
        Schema::create('trace_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_object_id')->constrained('objects')->cascadeOnDelete();
            $table->foreignId('to_object_id')->constrained('objects')->cascadeOnDelete();
            $table->enum('relation_type', array_column(RelationType::cases(), 'value'));
            $table->foreignId('project_id')->constrained()->cascadeOnDelete(); // denormalized for scoped walks
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['from_object_id', 'to_object_id', 'relation_type'], 'unique_trace_edge');
            $table->index(['to_object_id', 'relation_type']);   // reverse walk
            $table->index(['project_id', 'relation_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trace_relationships');
    }
};
