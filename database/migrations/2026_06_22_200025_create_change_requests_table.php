<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Controlled change requests (PRD §9.3.3, §13.10). No baselined object is
     * changed without one. Intake → impact analysis → approval → apply → regenerate.
     */
    public function up(): void
    {
        Schema::create('change_requests', function (Blueprint $table) {
            $table->id();
            $table->string('ref')->nullable();                 // CR-0001 (per project)
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('target_object_id')->constrained('objects')->cascadeOnDelete();
            $table->foreignId('raised_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->json('proposed_changes')->nullable();        // title/body/status to apply
            $table->json('impact')->nullable();                  // affected refs + counts (auto-trace)
            $table->enum('status', ['draft', 'approved', 'applied', 'rejected'])->default('draft');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
            $table->index('target_object_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_requests');
    }
};
