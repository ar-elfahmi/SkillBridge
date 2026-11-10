<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Course codes identify a course globally (docs/DIAGRAMS.md §5 no. 7):
 * confirming RPS metadata reuses an existing course by code. NULL codes stay allowed
 * and do not collide (PostgreSQL treats NULLs as distinct in unique indexes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table): void {
            $table->dropUnique(['code']);
        });
    }
};
