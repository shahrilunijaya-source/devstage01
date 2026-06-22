<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the `system_role` column the User model already relies on
 * (isAdmin/isDirector/isRegular/isClient). Resolves the long-standing
 * users.role vs system_role drift by backfilling from the legacy `role`.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'system_role')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('system_role')->default('regular')->after('role');
        });

        // Backfill: legacy admins stay admin, everyone else regular.
        DB::table('users')->where('role', 'admin')->update(['system_role' => 'admin']);
        DB::table('users')->where('role', '!=', 'admin')->update(['system_role' => 'regular']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'system_role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('system_role');
            });
        }
    }
};
