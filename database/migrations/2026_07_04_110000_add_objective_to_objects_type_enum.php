<?php

use App\Enums\ObjectType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The objects.type enum was built from ObjectType::cases() at create time —
     * fresh installs pick the new OBJECTIVE case up automatically, but existing
     * databases need the column re-declared against the current case list.
     */
    public function up(): void
    {
        Schema::table('objects', function (Blueprint $table) {
            $table->enum('type', array_column(ObjectType::cases(), 'value'))->change();
        });
    }

    public function down(): void
    {
        // No-op: removing an enum value would fail on rows that use it.
    }
};
