<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Machine-readable failure reason (T8.1). `error_code` is `quota_exhausted` or null.
 * `job_postings` also gets a user-facing `error_message`; the other tables already have one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_postings', function (Blueprint $table): void {
            $table->string('error_code', 50)->nullable();
            $table->text('error_message')->nullable();
        });

        Schema::table('rps_documents', function (Blueprint $table): void {
            $table->string('error_code', 50)->nullable();
        });

        Schema::table('analyses', function (Blueprint $table): void {
            $table->string('error_code', 50)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('analyses', function (Blueprint $table): void {
            $table->dropColumn('error_code');
        });

        Schema::table('rps_documents', function (Blueprint $table): void {
            $table->dropColumn('error_code');
        });

        Schema::table('job_postings', function (Blueprint $table): void {
            $table->dropColumn(['error_code', 'error_message']);
        });
    }
};
