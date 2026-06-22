<?php

use App\Enums\LifecycleStage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete(); // denormalized for ACL/index
            $table->enum('stage', array_column(LifecycleStage::cases(), 'value'));
            $table->enum('status', ['not_started', 'in_progress', 'in_review', 'baselined', 'blocked'])->default('not_started');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('gate_passed_at')->nullable();
            // current_baseline_id FK added after stage_baselines exists (200007).
            $table->timestamps();

            $table->unique(['module_id', 'stage']);
            $table->index(['project_id', 'stage', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stages');
    }
};
