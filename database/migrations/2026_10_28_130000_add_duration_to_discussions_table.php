<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discussions', function (Blueprint $table) {
            // مدة المناقشة بالدقائق — يعتمدها حارس الجدول لفحص تداخل الفترات (قاعة/مناقش)
            $table->unsignedSmallInteger('duration')->default(30)->after('discussion_time');
        });
    }

    public function down(): void
    {
        Schema::table('discussions', function (Blueprint $table) {
            $table->dropColumn('duration');
        });
    }
};
