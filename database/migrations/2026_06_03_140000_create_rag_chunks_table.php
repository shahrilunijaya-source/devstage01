<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rag_chunks', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // Where the chunk came from. 'document' = Google Drive file; the rest are DB rows.
            $table->enum('source_type', [
                'document', 'comment', 'weekly_update', 'week_note',
                'issue', 'wbs', 'ledger', 'claim', 'project_meta',
            ]);
            // PK of the origin row (rag_documents.id for documents). Null for project_meta.
            $table->unsignedBigInteger('source_id')->nullable();
            // Human-readable citation string, e.g. "SST.pdf p.3" or "Weekly Update 2026-05-20".
            $table->string('source_label');
            $table->text('chunk_text');
            // voyage-3 embedding as a JSON float array (1024 dims). Decoded + cosine-scored in PHP.
            $table->longText('embedding');
            $table->unsignedSmallInteger('token_count')->default(0);
            // sha256(source_type|source_id|chunk_text) — makes re-indexing idempotent.
            $table->char('content_hash', 64);
            $table->timestamps();

            $table->index(['project_id', 'source_type']);
            $table->index(['project_id', 'source_type', 'source_id']);
            $table->index('content_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rag_chunks');
    }
};
