<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Attaches a role to a user at a scope node, optionally time-bound and
     * revocable (PRD §6.3). A grant covers narrower scopes beneath it.
     */
    public function up(): void
    {
        Schema::create('acl_scope_bindings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('acl_roles')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete(); // fast tenant intersection
            $table->enum('scope_type', ['tenant', 'project', 'module', 'stage', 'session']);
            $table->unsignedBigInteger('scope_id')->nullable(); // null when scope_type=tenant
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'tenant_id', 'scope_type', 'scope_id'], 'idx_binding_lookup');
            $table->index('ends_at');
            $table->index('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acl_scope_bindings');
    }
};
