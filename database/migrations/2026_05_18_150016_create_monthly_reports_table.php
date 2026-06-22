<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monthly_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('reporting_month');
            $table->json('snapshot_data')->nullable();
            $table->text('executive_summary')->nullable();
            $table->text('diary_highlights')->nullable();
            $table->string('pdf_path')->nullable();
            $table->enum('status', ['draft', 'finalised'])->default('draft');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalised_at')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'reporting_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monthly_reports');
    }
};
