<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * دمج قائد الفريق في الطالب: القيادة صارت صلاحية داخل الفريق (teams.leader_id +
 * team_members.is_leader) لا دورًا مستقلًا، فنحوّل كل من دوره team_leader إلى student.
 * عمود الدور يُبقي القيمة 'team_leader' ضمن تعريف الـenum احتياطًا، لكن لا يُسند بعد الآن.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('role', 'team_leader')->update(['role' => 'student']);
    }

    public function down(): void
    {
        // إعادة الدور لقادة الفرق الحاليين اعتمادًا على teams.leader_id
        $leaderIds = DB::table('teams')->whereNotNull('leader_id')->pluck('leader_id')->unique()->all();

        if ($leaderIds !== []) {
            DB::table('users')->whereIn('id', $leaderIds)->update(['role' => 'team_leader']);
        }
    }
};
