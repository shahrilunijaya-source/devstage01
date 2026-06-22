<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ensure a default tenant exists and attach every unscoped project to it.
     * Idempotent: safe to re-run (firstOrCreate on slug, only updates null rows).
     */
    public function up(): void
    {
        $tenantId = DB::table('tenants')->where('slug', 'default')->value('id');

        if ($tenantId === null) {
            $now = now();
            $tenantId = DB::table('tenants')->insertGetId([
                'name' => 'Default Tenant',
                'slug' => 'default',
                'type' => 'agency',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        DB::table('projects')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
    }

    public function down(): void
    {
        // Reversible: detach projects from the default tenant.
        $tenantId = DB::table('tenants')->where('slug', 'default')->value('id');

        if ($tenantId !== null) {
            DB::table('projects')->where('tenant_id', $tenantId)->update(['tenant_id' => null]);
        }
    }
};
