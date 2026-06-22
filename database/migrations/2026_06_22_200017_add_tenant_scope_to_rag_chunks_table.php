<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Extend the RAG retrieval substrate (PRD §7.5): tenant isolation key + a
     * project/global scope so shared KRISA knowledge is indexed once and read by
     * all tenants, while project content stays gated by project_id. Relax
     * source_type to a string so 'knowledge' (and future kinds) are allowed.
     */
    public function up(): void
    {
        Schema::table('rag_chunks', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            $table->enum('scope', ['project', 'global'])->default('project')->after('tenant_id');
            $table->string('source_type', 50)->change();
            $table->index(['tenant_id', 'scope']);
        });

        // Backfill tenant_id from the owning project.
        DB::table('rag_chunks')->whereNull('tenant_id')->update([
            'tenant_id' => DB::raw('(select tenant_id from projects where projects.id = rag_chunks.project_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('rag_chunks', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'scope']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->dropColumn('scope');
        });
    }
};
