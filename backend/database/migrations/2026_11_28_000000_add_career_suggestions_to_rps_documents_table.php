<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Career suggestions generated once per RPS (one Gemini request) so the page does not call the model again.
 * Shape: {"status": "processing|completed|failed", "items": [{"career_id", "reason"}], "generated_at", "error_code"}.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rps_documents', function (Blueprint $table): void {
            $table->jsonb('career_suggestions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('rps_documents', function (Blueprint $table): void {
            $table->dropColumn('career_suggestions');
        });
    }
};
