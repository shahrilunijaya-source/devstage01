<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Baseline reopen (spec §9 "Reopened") + firewall send-back (spec §9
     * "Refinement Required"). Both were declared in the lifecycle but had no
     * storage or code path.
     */
    public function up(): void
    {
        Schema::table('stage_baselines', function (Blueprint $table) {
            $table->enum('status', ['draft', 'approved', 'superseded', 'reopened'])
                ->default('draft')->change();
            $table->foreignId('reopened_by')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('reopened_at')->nullable()->after('reopened_by');
            $table->string('reopen_reason', 500)->nullable()->after('reopened_at');
        });

        Schema::table('requirement_sessions', function (Blueprint $table) {
            $table->foreignId('firewall_rejected_by')->nullable()->after('firewall_approved_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('firewall_rejected_at')->nullable()->after('firewall_rejected_by');
            $table->string('firewall_rejected_reason', 500)->nullable()->after('firewall_rejected_at');
        });
    }

    public function down(): void
    {
        Schema::table('requirement_sessions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('firewall_rejected_by');
            $table->dropColumn(['firewall_rejected_at', 'firewall_rejected_reason']);
        });

        Schema::table('stage_baselines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reopened_by');
            $table->dropColumn(['reopened_at', 'reopen_reason']);
            $table->enum('status', ['draft', 'approved', 'superseded'])->default('draft')->change();
        });
    }
};
