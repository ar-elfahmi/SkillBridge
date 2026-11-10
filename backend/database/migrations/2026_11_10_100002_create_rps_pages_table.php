<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rps_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rps_document_id')->constrained()->cascadeOnDelete();
            $table->smallInteger('page_number');
            // Page text, used to verify evidence quotes.
            $table->text('text');
            $table->timestamps();

            $table->unique(['rps_document_id', 'page_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rps_pages');
    }
};
