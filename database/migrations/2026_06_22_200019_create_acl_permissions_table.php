<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Atomic action × object_type permissions (PRD §6.1). */
    public function up(): void
    {
        Schema::create('acl_permissions', function (Blueprint $table) {
            $table->id();
            $table->string('action');       // view, edit, validate, approve, baseline, export, retrieve, assign
            $table->string('object_type');  // project, module, stage, session, evidence, ... or *
            $table->string('key')->unique(); // "{action}:{object_type}"
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['action', 'object_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acl_permissions');
    }
};
