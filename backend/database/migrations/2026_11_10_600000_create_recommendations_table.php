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
        Schema::create('recommendations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('analysis_id')->constrained('analyses')->cascadeOnDelete();
            $table->foreignId('skill_match_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('reason');
            $table->string('priority');
            // {topics[], learning_keywords[]}; never URLs.
            $table->jsonb('details')->default(DB::raw("'{}'::jsonb"));
            $table->timestamps();

            // One recommendation per skill match, so refresh can replace idempotently.
            $table->unique(['analysis_id', 'skill_match_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations');
    }
};
