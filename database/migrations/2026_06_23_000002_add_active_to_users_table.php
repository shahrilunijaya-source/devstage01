<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the `active` flag the User model and several services already rely on
 * (NotificationService triager/director queries, WorkloadController, etc.).
 * Resolves the second half of the users-table drift (see system_role).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'active')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('active')->default(true)->after('system_role');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'active')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('active');
            });
        }
    }
};
