<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_postings', function (Blueprint $table): void {
            $table->id();
            // A career with postings cannot be deleted by accident.
            $table->foreignId('career_id')->constrained()->restrictOnDelete();
            // Null = curated dataset (read-only via API). Manual postings belong to the user.
            $table->foreignId('created_by')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('company');
            $table->text('description');
            $table->text('qualifications')->nullable();
            $table->string('source_name')->nullable();
            $table->string('source_url')->nullable();
            $table->date('collected_at')->nullable();
            $table->string('dataset_version')->nullable();
            $table->string('extraction_status')->default('pending');
            $table->timestamps();

            $table->index('career_id');
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_postings');
    }
};
