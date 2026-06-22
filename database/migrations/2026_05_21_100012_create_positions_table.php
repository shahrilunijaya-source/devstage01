<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('level_id')->constrained('position_levels')->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 50)->nullable();
            $table->boolean('is_delivery_role')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['department_id', 'level_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
