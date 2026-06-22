<?php

use App\Services\AccessControl\AclProvisioner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Seed ACL roles/permissions/grants and mirror existing assignments. Idempotent. */
    public function up(): void
    {
        $provisioner = app(AclProvisioner::class);
        $provisioner->provision();
        $provisioner->backfillBindings();
    }

    public function down(): void
    {
        DB::table('acl_scope_bindings')->delete();
        DB::table('acl_role_permission')->delete();
        DB::table('acl_permissions')->delete();
        DB::table('acl_roles')->delete();
    }
};
