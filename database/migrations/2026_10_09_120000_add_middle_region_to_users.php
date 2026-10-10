<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// منطقة ثالثة لمكان التواجد: وسط غزة (منفصلة عن غزة والجنوب في كل الفلاتر)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('region', ['gaza', 'middle', 'south'])->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('region', ['gaza', 'south'])->nullable()->change();
        });
    }
};
