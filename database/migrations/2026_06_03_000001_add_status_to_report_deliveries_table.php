<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_deliveries', function (Blueprint $table) {
            // logged = manual delivery only; sent = email delivered; failed = SMTP error
            $table->string('status', 16)->default('logged')->after('delivery_method');
            $table->text('error')->nullable()->after('attachment_hash');
        });
    }

    public function down(): void
    {
        Schema::table('report_deliveries', function (Blueprint $table) {
            $table->dropColumn(['status', 'error']);
        });
    }
};
