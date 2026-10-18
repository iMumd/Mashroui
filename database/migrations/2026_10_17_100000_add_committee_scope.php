<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** نطاق عضو لجنة الإشراف: حتى 5 تخصصات (committee_specializations) أو قسم كامل (users.scope_department_id) */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('scope_department_id')->nullable()->after('specialization_id')->constrained('departments')->nullOnDelete();
        });

        Schema::create('committee_specializations', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specialization_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'specialization_id']);
        });

        // نقل التخصص الواحد الحالي لأعضاء اللجنة إلى الجدول الجديد
        DB::table('committee_specializations')->insertUsing(
            ['user_id', 'specialization_id'],
            DB::table('users')->select('id', 'specialization_id')->where('role', 'committee')->whereNotNull('specialization_id')
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_specializations');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('scope_department_id');
        });
    }
};
