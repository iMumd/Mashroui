<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// توسيع أنواع دفعات الاستيراد لتشمل استيراد مواعيد المناقشات (بجانب كشف الطلبة والفرق)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->enum('kind', ['student_roster', 'teams', 'discussions'])->default('student_roster')->change();
        });
    }

    public function down(): void
    {
        Schema::table('import_batches', function (Blueprint $table) {
            $table->enum('kind', ['student_roster', 'teams'])->default('student_roster')->change();
        });
    }
};
