<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The five-phase session lifecycle (PRD §9.3): AI pre-analysis → quality
     * firewall → ready → in session → post-session. `status` keeps the approval
     * state; `phase` tracks engine progress.
     */
    public function up(): void
    {
        Schema::table('requirement_sessions', function (Blueprint $table) {
            $table->enum('phase', [
                'pre_analysis', 'firewall_review', 'ready', 'in_session', 'post_session', 'approved',
            ])->default('pre_analysis')->after('status');
            $table->foreignId('firewall_approved_by')->nullable()->after('phase')->constrained('users')->nullOnDelete();
            $table->timestamp('firewall_approved_at')->nullable()->after('firewall_approved_by');
            $table->index(['stage_id', 'phase']);
        });
    }

    public function down(): void
    {
        Schema::table('requirement_sessions', function (Blueprint $table) {
            $table->dropIndex(['stage_id', 'phase']);
            $table->dropConstrainedForeignId('firewall_approved_by');
            $table->dropColumn(['phase', 'firewall_approved_at']);
        });
    }
};
