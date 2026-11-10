<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analyses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Deleting an RPS (DELETE /api/rps/{id}) removes its analyses.
            $table->foreignId('rps_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('career_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('queued');
            // Safe, user-facing message only.
            $table->text('error_message')->nullable();
            $table->jsonb('summary')->nullable();
            $table->jsonb('model_versions')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('rps_document_id');
        });

        Schema::create('analysis_job_postings', function (Blueprint $table): void {
            $table->foreignId('analysis_id')->constrained('analyses')->cascadeOnDelete();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();

            $table->primary(['analysis_id', 'job_posting_id']);
            $table->index('job_posting_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_job_postings');
        Schema::dropIfExists('analyses');
    }
};
