<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Attribute-based object rules (PRD §6.3): refine rights by type/status/classification. */
    public function up(): void
    {
        Schema::create('acl_object_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('object_type');                 // or '*'
            $table->string('action');
            $table->string('match_classification')->nullable();
            $table->string('match_status')->nullable();
            $table->enum('effect', ['permit', 'deny']);
            $table->string('required_role_key')->nullable();
            $table->smallInteger('priority')->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'object_type', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acl_object_rules');
    }
};
