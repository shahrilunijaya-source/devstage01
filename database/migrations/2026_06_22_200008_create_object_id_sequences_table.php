<?php

use App\Enums\ObjectType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Per-(project, type) monotonic counter for permanent-ID minting (PRD §12.6). */
    public function up(): void
    {
        Schema::create('object_id_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->enum('type', array_column(ObjectType::cases(), 'value'));
            $table->unsignedInteger('next_seq')->default(1);
            $table->timestamps();

            $table->unique(['project_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('object_id_sequences');
    }
};
