<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Objective Guardian (spec §7): every change request carries an alignment
     * assessment against the approved project objective; approving past a
     * direct conflict requires a recorded override reason.
     */
    public function up(): void
    {
        Schema::table('change_requests', function (Blueprint $table) {
            $table->json('guardian_assessment')->nullable()->after('impact');
            $table->string('override_reason', 500)->nullable()->after('guardian_assessment');
        });
    }

    public function down(): void
    {
        Schema::table('change_requests', function (Blueprint $table) {
            $table->dropColumn(['guardian_assessment', 'override_reason']);
        });
    }
};
