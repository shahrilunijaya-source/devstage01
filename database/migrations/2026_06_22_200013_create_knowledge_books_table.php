<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Version registry for shared/global KRISA + Methodology knowledge (PRD §7.1, §7.2). */
    public function up(): void
    {
        Schema::create('knowledge_books', function (Blueprint $table) {
            $table->id();
            $table->enum('kind', ['krisa', 'methodology'])->default('krisa');
            $table->string('version');               // "v2026.1"
            $table->string('title');
            $table->enum('status', ['draft', 'published', 'deprecated'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['kind', 'version']);
            $table->index(['kind', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_books');
    }
};
