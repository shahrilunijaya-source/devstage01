<?php

use App\Models\AuditLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        AuditLog::backfillProjectIds();
    }

    public function down(): void
    {
        // Reversible: clear the backfilled scope.
        DB::table('audit_logs')->update(['project_id' => null]);
    }
};
