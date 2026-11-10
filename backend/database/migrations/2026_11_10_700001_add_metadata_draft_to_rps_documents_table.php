<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Metadata extracted by AI is kept as a draft on the document until the user confirms it
 * (docs/DIAGRAMS.md §5 no. 10). Only confirmation resolves `courses` and sets
 * `metadata_confirmed_at`, which gates competency extraction.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rps_documents', function (Blueprint $table): void {
            $table->jsonb('detected_metadata')->nullable()->after('course_id');
            $table->timestamp('metadata_confirmed_at')->nullable()->after('detected_metadata');
        });
    }

    public function down(): void
    {
        Schema::table('rps_documents', function (Blueprint $table): void {
            $table->dropColumn(['detected_metadata', 'metadata_confirmed_at']);
        });
    }
};
