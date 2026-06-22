<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sentiment_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->enum('source_type', ['weekly_update', 'comment', 'issue']);
            $table->unsignedBigInteger('source_id');
            $table->enum('label', ['negative', 'neutral', 'positive']);
            $table->unsignedTinyInteger('score'); // 1..5
            $table->text('summary')->nullable();
            $table->string('model')->nullable();
            $table->date('source_date')->nullable();  // when the morale happened (drives the trend)
            $table->timestamp('scored_at')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id']); // one current score per source row
            $table->index(['project_id', 'source_type']);
            $table->index(['project_id', 'source_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sentiment_scores');
    }
};
