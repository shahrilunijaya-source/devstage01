<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Immutable, append-only access decision log (PRD §6.4). */
    public function up(): void
    {
        Schema::create('acl_access_audit', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();   // subject
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();   // trigger
            $table->enum('decision', ['permit', 'deny']);
            $table->string('action');
            $table->string('object_type')->nullable();
            $table->unsignedBigInteger('object_id')->nullable();
            $table->string('field')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('scope_type')->nullable();
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('reason')->nullable();
            $table->string('matched_rule_type')->nullable();
            $table->unsignedBigInteger('matched_rule_id')->nullable();
            $table->string('pep')->nullable(); // gate, rag, session, document, change, export, admin
            $table->uuid('request_id')->nullable();
            $table->string('ip')->nullable();
            $table->timestamp('created_at')->useCurrent(); // no updated_at — immutable

            $table->index(['user_id', 'created_at']);
            $table->index(['object_type', 'object_id']);
            $table->index(['decision', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acl_access_audit');
    }
};
