<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Role → permission grants. Explicit deny beats permit (PRD §6.3). */
    public function up(): void
    {
        Schema::create('acl_role_permission', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained('acl_roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('acl_permissions')->cascadeOnDelete();
            $table->enum('effect', ['permit', 'deny'])->default('permit');
            $table->timestamps();

            $table->unique(['role_id', 'permission_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acl_role_permission');
    }
};
