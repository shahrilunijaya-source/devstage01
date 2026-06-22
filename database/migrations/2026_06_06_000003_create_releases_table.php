<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->string('version')->unique();   // e.g. 1.0.0-122
            $table->string('title')->nullable();   // e.g. "v1.0.0 · build 122"
            $table->json('notes');                 // {new:[], fixed:[], improved:[]}
            $table->string('git_sha', 40)->nullable();
            $table->timestamp('released_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('releases');
    }
};
