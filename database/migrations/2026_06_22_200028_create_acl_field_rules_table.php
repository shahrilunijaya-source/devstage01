<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Field-level rules incl. redaction for unauthorised viewers (PRD §6.3 ACL-05). */
    public function up(): void
    {
        Schema::create('acl_field_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('object_type');                 // or '*'
            $table->string('field');
            $table->string('classification')->nullable();
            $table->enum('effect', ['permit', 'deny', 'redact']);
            $table->string('required_role_key')->nullable();
            $table->smallInteger('priority')->default(0);
            $table->timestamps();

            $table->index(['object_type', 'field']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acl_field_rules');
    }
};
