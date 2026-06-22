<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Immutable stage rollup. Doubles as the canonical-graph Baseline header:
     * approved session objects are frozen into baseline_objects (Foundation B)
     * pinned to their object version.
     */
    public function up(): void
    {
        Schema::create('stage_baselines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained('stages')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->string('version_label');                 // "BRS v1.0"
            $table->unsignedInteger('sequence');             // 1,2,3 monotonic per stage
            $table->enum('status', ['draft', 'approved', 'superseded'])->default('draft');
            $table->string('knowledge_book_version')->nullable();
            $table->json('snapshot_meta')->nullable();        // counts, coverage %, session ids
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['stage_id', 'sequence']);
            $table->index(['project_id', 'status']);
        });

        // Now that stage_baselines exists, give stages a pointer to its active baseline.
        Schema::table('stages', function (Blueprint $table) {
            $table->foreignId('current_baseline_id')->nullable()->after('gate_passed_at')
                ->constrained('stage_baselines')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_baseline_id');
        });

        Schema::dropIfExists('stage_baselines');
    }
};
