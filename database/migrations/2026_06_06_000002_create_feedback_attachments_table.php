<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feedback_item_id')->constrained('feedback_items')->cascadeOnDelete();
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 128)->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->timestamps();

            $table->index('feedback_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_attachments');
    }
};
