<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Data classification for object- and field-level access control (PRD §6.1). */
    public function up(): void
    {
        Schema::table('objects', function (Blueprint $table) {
            $table->enum('classification', ['public', 'internal', 'confidential', 'restricted'])
                ->default('internal')->after('impact');
            $table->index(['project_id', 'classification']);
        });
    }

    public function down(): void
    {
        Schema::table('objects', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'classification']);
            $table->dropColumn('classification');
        });
    }
};
