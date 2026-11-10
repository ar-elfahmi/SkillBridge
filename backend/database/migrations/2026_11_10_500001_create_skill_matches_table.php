<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_matches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('analysis_id')->constrained('analyses')->cascadeOnDelete();
            // Industry skill, unique per analysis.
            $table->foreignId('skill_id')->constrained()->restrictOnDelete();
            $table->smallInteger('demand_count');
            $table->jsonb('source_job_skill_ids')->default(DB::raw("'[]'::jsonb"));
            // Best RPS competency; null when missing (or when the user later deletes it).
            $table->foreignId('course_skill_id')->nullable()->constrained()->nullOnDelete();
            // Best candidate score: NOT a mastery percentage.
            $table->decimal('similarity_score', 6, 5)->nullable();
            $table->string('classification');
            $table->text('reason');
            $table->text('evidence')->nullable();
            $table->jsonb('candidates')->default(DB::raw("'[]'::jsonb"));
            $table->string('judged_by');
            $table->timestamps();

            $table->unique(['analysis_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_matches');
    }
};
