<?php

use App\Enums\ObjectType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Frozen membership of a stage baseline: each object pinned at a version. */
    public function up(): void
    {
        Schema::create('baseline_objects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_baseline_id')->constrained('stage_baselines')->cascadeOnDelete();
            $table->foreignId('object_id')->constrained('objects')->cascadeOnDelete();
            $table->unsignedInteger('object_version');
            $table->string('ref');                                  // denormalized for fast rendering
            $table->enum('type', array_column(ObjectType::cases(), 'value'));
            $table->timestamps();

            $table->unique(['stage_baseline_id', 'object_id']);
            $table->index('object_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baseline_objects');
    }
};
