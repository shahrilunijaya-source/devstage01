<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('monthly_report_id')->constrained()->cascadeOnDelete();
            $table->enum('delivery_method', ['in_app_email', 'manual']);
            $table->json('recipients_to')->nullable();
            $table->json('recipients_cc')->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->string('attachment_hash', 64)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('manual_delivery_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_deliveries');
    }
};
