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
        Schema::create('skills', function (Blueprint $table): void {
            $table->id();
            $table->string('canonical_name')->unique();
            $table->string('category')->nullable();
            $table->jsonb('aliases')->default(DB::raw("'[]'::jsonb"));
            $table->timestamps();
        });

        // Normalization matches case-insensitively, so "git" and "Git" must not coexist.
        DB::statement('CREATE UNIQUE INDEX skills_canonical_name_lower_unique ON skills (LOWER(canonical_name))');
    }

    public function down(): void
    {
        Schema::dropIfExists('skills');
    }
};
