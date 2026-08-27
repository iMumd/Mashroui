<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE proposals MODIFY description TEXT NULL');
        DB::statement('ALTER TABLE proposals MODIFY problems TEXT NULL');
        DB::statement('ALTER TABLE proposals MODIFY solutions TEXT NULL');
        DB::statement('ALTER TABLE proposals MODIFY features_value TEXT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE proposals MODIFY description TEXT NOT NULL");
        DB::statement("ALTER TABLE proposals MODIFY problems TEXT NOT NULL");
        DB::statement("ALTER TABLE proposals MODIFY solutions TEXT NOT NULL");
        DB::statement("ALTER TABLE proposals MODIFY features_value TEXT NOT NULL");
    }
};
