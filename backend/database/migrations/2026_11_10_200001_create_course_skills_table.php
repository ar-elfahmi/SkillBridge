<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_skills', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rps_document_id')->constrained()->cascadeOnDelete();
            // Result of normalization; null until normalized.
            $table->foreignId('skill_id')->nullable()->constrained()->nullOnDelete();
            $table->string('skill');
            $table->string('category')->nullable();
            $table->text('competency');
            // RPS quote; null only for origin = user.
            $table->text('evidence')->nullable();
            $table->string('source_type');
            $table->string('origin')->default('ai');
            $table->string('review_status')->default('pending');
            $table->boolean('user_edited')->default(false);
            $table->string('source_reference')->nullable();
            $table->smallInteger('week')->nullable();
            // Null when the page is unknown; never guessed.
            $table->smallInteger('page')->nullable();
            $table->string('verification_status')->default('unverified');
            $table->jsonb('embedding')->nullable();
            $table->string('embedding_model')->nullable();
            $table->smallInteger('embedding_dimensions')->nullable();
            $table->string('text_hash', 64)->nullable();
            $table->timestamps();

            $table->index('rps_document_id');
            $table->index('skill_id');
            $table->index(['text_hash', 'embedding_model', 'embedding_dimensions']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_skills');
    }
};
