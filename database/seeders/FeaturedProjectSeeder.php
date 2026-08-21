<?php

namespace Database\Seeders;

use App\Enums\DiscussionStatusEnum;
use App\Enums\ProjectStatusEnum;
use App\Enums\ProposalStatusEnum;
use App\Enums\RoleEnum;
use App\Enums\TaskStatusEnum;
use App\Models\AcademicTerm;
use App\Models\Discussion;
use App\Models\FinalReport;
use App\Models\Project;
use App\Models\Proposal;
use App\Models\Specialization;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

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
                'whatsapp' => '970599234567',
                'employee_number' => 'EMP-1004',
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
                'whatsapp' => '970562348871',
                'university_number' => '12110234',
            ]
        );

        $team = Team::create([
            'name' => 'فريق العرض المميز',
            'supervisor_id' => $supervisor->id,
            'specialization_id' => $specialization->id,
            'term_id' => $termId,
            'leader_id' => $leader->id,
        ]);

        $member = User::firstOrCreate(
            ['email' => 'ali.featured@mashroui.local'],
            [
                'name' => 'علي منصور',
                'password' => 'password',
                'role' => RoleEnum::Student,
                'specialization_id' => $specialization->id,
                'term_id' => $termId,
                'whatsapp' => '970598812245',
                'university_number' => '12110567',
            ]
        );

        TeamMember::create([
            'team_id' => $team->id,
            'student_id' => $leader->id,
            'is_leader' => true,
        ]);
        TeamMember::create([
            'team_id' => $team->id,
            'student_id' => $member->id,
            'is_leader' => false,
        ]);

        $project = Project::create([
            'team_id' => $team->id,
            'supervisor_id' => $supervisor->id,
            'name' => 'نظام ذكي لتسجيل حضور الطلاب بتقنية التعرف على الوجه',
            'description' => 'تطبيق ويب متكامل يتيح رصد حضور الطلاب تلقائياً داخل القاعات باستخدام تقنيات الرؤية الحاسوبية، مع لوحة تحكم لإدارة التقارير والإحصائيات لحظياً.',
            'department_id' => $specialization->department_id,
            'specialization_id' => $specialization->id,
            'term_id' => $termId,
            'status' => ProjectStatusEnum::Completed,
            'completed_at' => now()->subDays(18),
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

        $pdfPath = 'proposals/'.uniqid().'.pdf';
        Storage::put($pdfPath, "%PDF-1.4\n% مقترح — نظام تسجيل الحضور بالتعرف على الوجه\n%%EOF");

        Proposal::create([
            'project_id' => $project->id,
            'name' => $project->name,
            'description' => $project->description,
            'problems' => 'رصد الحضور اليدوي بالقاعات الكبيرة يستهلك وقت المحاضرة، وعرضة للتلاعب بتسجيل حضور زملاء غائبين.',
            'solutions' => 'نظام تعرّف على الوجه يرصد حضور الطلاب تلقائياً عند دخول القاعة عبر كاميرا واحدة، ويرسل تقرير الحضور مباشرة للمدرّس.',
            'features_value' => 'رصد تلقائي بالتعرف على الوجه، تقارير حضور لحظية، تنبيه عند نسبة غياب مرتفعة.',
            'pdf_path' => $pdfPath,
            'status' => ProposalStatusEnum::Approved,
            'submitted_by' => $leader->id,
            'reviewed_by' => $supervisor->id,
        ]);

        $reportPath = 'final_reports/'.uniqid().'.pdf';
        Storage::put($reportPath, "%PDF-1.4\n% التقرير النهائي — نظام تسجيل الحضور بالتعرف على الوجه\n%%EOF");

        FinalReport::create([
            'project_id' => $project->id,
            'pdf_path' => $reportPath,
            'video_url' => 'https://www.youtube.com/watch?v=YE7VzlLtp-4',
            'uploaded_by' => $leader->id,
        ]);

        Discussion::create([
            'project_id' => $project->id,
            'supervisor_id' => $supervisor->id,
            'place' => 'قاعة المناقشات الرئيسية - مبنى A',
            'discussion_date' => now()->subDays(15)->toDateString(),
            'discussion_time' => '10:00:00',
            'committee' => 'د. سامر خليل، أ. هبة الزعبي، أ. رامي قاسم',
            'whatsapp' => null,
            'status' => DiscussionStatusEnum::Confirmed,
            'term_id' => $termId,
        ]);
    }
}
