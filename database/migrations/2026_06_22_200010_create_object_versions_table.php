<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Append-only, immutable version history (PRD §12.5). One row per change. */
    public function up(): void
    {
        Schema::create('object_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('object_id')->constrained('objects')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot');                 // full frozen copy of the object state
            $table->string('change_summary')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent(); // no updated_at — rows are immutable

            $table->unique(['object_id', 'version']);
            $table->index('object_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('object_versions');
    }
};
