<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The study_program role was dropped from the MVP; users.role now only holds
 * "student". Converts any accounts registered with the old role.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', '!=', 'student')->update(['role' => 'student']);
    }

    public function down(): void
    {
        // Irreversible: the original role values are not kept.
    }
};
