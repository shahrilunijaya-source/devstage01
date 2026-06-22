<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Enum ALTER is MySQL-only. On SQLite (test env) project_role is plain
        // varchar, so 'client' is already accepted — no-op.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE project_assignments MODIFY COLUMN project_role ENUM('pm','pe','member','client') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        // Soft-remove active client assignments before shrinking the enum.
        DB::table('project_assignments')
            ->where('project_role', 'client')
            ->whereNull('removed_at')
            ->update(['removed_at' => now()]);

        DB::statement("ALTER TABLE project_assignments MODIFY COLUMN project_role ENUM('pm','pe','member') NOT NULL");
    }
};
