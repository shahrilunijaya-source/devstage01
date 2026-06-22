<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monthly_reports', function (Blueprint $table) {
            // PM approval gate before a report can be emailed to the client.
            // Approved = status is 'finalised' AND approved_at is set. Tracked as
            // a timestamp (not a new status enum value) so the migration stays
            // portable across sqlite (tests) and MySQL (prod).
            $table->timestamp('approved_at')->nullable()->after('finalised_at');
            $table->foreignId('approved_by')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('monthly_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
        });
    }
};
