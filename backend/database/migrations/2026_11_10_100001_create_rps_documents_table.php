<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rps_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Filled after metadata is confirmed.
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_name');
            $table->string('file_path');
            $table->string('file_hash', 64);
            $table->integer('file_size');
            $table->smallInteger('page_count')->nullable();
            $table->string('parse_status')->default('pending');
            $table->string('extraction_status')->default('pending');
            $table->string('review_status')->default('unreviewed');
            $table->timestamp('reviewed_at')->nullable();
            // Safe, user-facing message only; no internal details.
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->unique(['user_id', 'file_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rps_documents');
    }
};
