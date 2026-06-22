<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_items', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['bug', 'feature']);
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['new', 'triaged', 'in_progress', 'resolved', 'closed', 'wont_fix'])->default('new');
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');
            $table->foreignId('submitted_by')->constrained('users');
            $table->text('admin_response')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('page_url', 2048)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
            $table->index('submitted_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_items');
    }
};
