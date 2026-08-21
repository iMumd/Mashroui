<?php

namespace Database\Seeders;

use App\Enums\ProjectStatusEnum;
use App\Enums\RoleEnum;
use App\Enums\TaskStatusEnum;
use App\Models\AcademicTerm;
use App\Models\Project;
use App\Models\Specialization;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Seeder;

class FeaturedProjectSeeder extends Seeder
{
    public function run(): void
    {
        $termId = AcademicTerm::where('is_current', true)->value('id');
        $specialization = Specialization::first();

        $supervisor = User::firstOrCreate(
            ['email' => 'supervisor.featured@mashroui.local'],
            [
                'name' => 'م. سامر خليل',
                'password' => 'password',
                'role' => RoleEnum::Supervisor,
                'specialization_id' => $specialization->id,
            ]
        );

        $leader = User::firstOrCreate(
            ['email' => 'leader.featured@mashroui.local'],
            [
                'name' => 'أحمد عودة',
                'password' => 'password',
                'role' => RoleEnum::TeamLeader,
                'specialization_id' => $specialization->id,
                'term_id' => $termId,
            ]
        );

        $team = Team::create([
            'name' => 'فريق العرض المميز',
            'supervisor_id' => $supervisor->id,
            'specialization_id' => $specialization->id,
            'term_id' => $termId,
            'leader_id' => $leader->id,
        ]);

        TeamMember::create([
            'team_id' => $team->id,
            'student_id' => $leader->id,
            'is_leader' => true,
        ]);

        Project::create([
            'team_id' => $team->id,
            'supervisor_id' => $supervisor->id,
            'name' => 'نظام ذكي لتسجيل حضور الطلاب بتقنية التعرف على الوجه',
            'description' => 'تطبيق ويب متكامل يتيح رصد حضور الطلاب تلقائياً داخل القاعات باستخدام تقنيات الرؤية الحاسوبية، مع لوحة تحكم لإدارة التقارير والإحصائيات لحظياً.',
            'department_id' => $specialization->department_id,
            'specialization_id' => $specialization->id,
            'term_id' => $termId,
            'status' => ProjectStatusEnum::Completed,
            'completed_at' => now(),
            'is_featured' => true,
        ]);

        foreach ([
            'تصميم واجهات النظام',
            'بناء نموذج التعرف على الوجه',
            'تكامل لوحة التقارير والإحصائيات',
            'اختبار النظام والتسليم النهائي',
        ] as $title) {
            Task::create([
                'team_id' => $team->id,
                'title' => $title,
                'status' => TaskStatusEnum::Done,
                'created_by' => $supervisor->id,
            ]);
        }
    }
}
