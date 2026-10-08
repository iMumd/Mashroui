<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // الموعد النهائي لتسليم المهمة
            $table->date('due_date')->nullable()->after('status');
        });

        // جدول ربط يسمح بتكليف أكثر من طالب بنفس المهمة (بدل assignee_id الواحد)
        Schema::create('task_assignee', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['task_id', 'user_id']);
        });

        // ترحيل التكليفات المفردة الموجودة إلى جدول الربط الجديد
        $rows = DB::table('tasks')->whereNotNull('assignee_id')->get(['id', 'assignee_id']);
        foreach ($rows as $row) {
            DB::table('task_assignee')->insert([
                'task_id' => $row->id,
                'user_id' => $row->assignee_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assignee_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('assignee_id')->nullable()->after('team_id')->constrained('users')->nullOnDelete();
        });

        // إرجاع أول مكلّف لكل مهمة إلى العمود المفرد
        $rows = DB::table('task_assignee')->orderBy('id')->get(['task_id', 'user_id']);
        $seen = [];
        foreach ($rows as $row) {
            if (isset($seen[$row->task_id])) {
                continue;
            }
            $seen[$row->task_id] = true;
            DB::table('tasks')->where('id', $row->task_id)->update(['assignee_id' => $row->user_id]);
        }

        Schema::dropIfExists('task_assignee');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('due_date');
        });
    }
};
