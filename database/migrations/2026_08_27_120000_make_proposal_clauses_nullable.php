<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * نستخدم Schema builder بدل DB::statement('ALTER ... MODIFY ...') المخصص لـ MySQL
     * حتى تعمل الهجرة على جميع المحركات (MySQL في الإنتاج وSQLite في الاختبارات).
     */
    public function up(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->text('description')->nullable()->change();
            $table->text('problems')->nullable()->change();
            $table->text('solutions')->nullable()->change();
            $table->text('features_value')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->text('description')->nullable(false)->change();
            $table->text('problems')->nullable(false)->change();
            $table->text('solutions')->nullable(false)->change();
            $table->text('features_value')->nullable(false)->change();
        });
    }
};
