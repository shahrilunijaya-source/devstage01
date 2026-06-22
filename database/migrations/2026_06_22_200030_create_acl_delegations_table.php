<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Time-bound delegation of access from one user to another (PRD §6.3).
     * A delegation always carries an end date and auto-expires; it owns the
     * scope binding it creates so revocation/expiry can withdraw both as a unit.
     */
    public function up(): void
    {
        Schema::create('acl_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delegator_id')->constrained('users')->cascadeOnDelete(); // grants own access
            $table->foreignId('delegate_id')->constrained('users')->cascadeOnDelete();   // receives access
            $table->foreignId('role_id')->constrained('acl_roles')->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->enum('scope_type', ['tenant', 'project', 'module', 'stage', 'session']);
            $table->unsignedBigInteger('scope_id')->nullable(); // null when scope_type=tenant
            $table->foreignId('scope_binding_id')->nullable()->constrained('acl_scope_bindings')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at'); // delegations are always time-bound
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['delegate_id', 'tenant_id']);
            $table->index('ends_at');
            $table->index('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acl_delegations');
    }
};
