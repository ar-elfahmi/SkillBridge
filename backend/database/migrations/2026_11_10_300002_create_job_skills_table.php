<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_skills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('job_posting_id')->constrained()->cascadeOnDelete();
            // Always normalized (a new skills entry is created when missing).
            $table->foreignId('skill_id')->constrained()->restrictOnDelete();
            $table->string('skill');
            $table->text('requirement_text');
            $table->text('evidence');
            $table->jsonb('embedding')->nullable();
            $table->string('embedding_model')->nullable();
            $table->smallInteger('embedding_dimensions')->nullable();
            $table->string('text_hash', 64)->nullable();
            $table->timestamps();

            $table->index('job_posting_id');
            $table->index('skill_id');
            $table->index(['text_hash', 'embedding_model', 'embedding_dimensions']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_skills');
    }
};
