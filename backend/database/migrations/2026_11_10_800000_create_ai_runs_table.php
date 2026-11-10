<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('purpose');
            $table->string('model');
            $table->string('prompt_version')->nullable();
            $table->string('input_hash', 64);
            $table->string('status');
            $table->unsignedInteger('latency_ms')->nullable();
            $table->jsonb('usage')->nullable();
            $table->text('error')->nullable();
            $table->nullableMorphs('subject');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_runs');
    }
};
