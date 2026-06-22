<?php

use App\Services\AccessControl\AclProvisioner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Seed default object/field rules now that their tables exist. Idempotent. */
    public function up(): void
    {
        app(AclProvisioner::class)->seedRules();
    }

    public function down(): void
    {
        DB::table('acl_object_rules')->delete();
        DB::table('acl_field_rules')->delete();
    }
};
