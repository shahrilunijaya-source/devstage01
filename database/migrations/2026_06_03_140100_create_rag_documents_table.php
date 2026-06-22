<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rag_documents', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('gdrive_file_id', 128);
            $table->string('name');                 // filename, used in citations
            $table->string('mime', 128)->nullable();
            $table->timestamp('modified_time')->nullable(); // Drive modifiedTime — skip re-index if unchanged
            $table->timestamp('indexed_at')->nullable();
            // pending = queued; indexed = chunked; unindexable = scanned/no text; error = failed
            $table->enum('status', ['pending', 'indexed', 'unindexable', 'error'])->default('pending');
            $table->string('error', 500)->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'gdrive_file_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rag_documents');
    }
};
