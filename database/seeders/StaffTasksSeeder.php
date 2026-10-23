<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Models\TaskNote;
use App\Models\User;
use Illuminate\Database\Seeder;

class StaffTasksSeeder extends Seeder
{
    public function run(): void
    {
        $committee = User::where('role', RoleEnum::Committee)->get();
        $supervisors = User::where('role', RoleEnum::Supervisor)->get();
        $admin = User::where('role', RoleEnum::SuperAdmin)->first();
        $creator = $admin ?? $committee->first();

        if (! $creator) return;

        $term = \App\Models\AcademicTerm::where('is_current', true)->first();
        $termId = $term?->id;

        // Committee tasks
        $ctasks = [
            ['title' => 'مراجعة مقترحات المشاريع المعلقة', 'status' => TaskStatusEnum::InProgress, 'due_days' => 4, 'note' => 'مراجعة المقترحات الواردة والتأكد من مطابقتها للشروط.'],
            ['title' => 'إعداد جدول مناقشات مشروع التخرج', 'status' => TaskStatusEnum::InProgress, 'due_days' => 7, 'note' => 'تنسيق مواعيد المناقشات، القاعات، ولجان التحكيم.'],
            ['title' => 'متابعة المجموعات المتأخرة في الإنجاز', 'status' => TaskStatusEnum::Review, 'due_days' => -1, 'note' => 'متابعة الفرق المتأخرة وإبلاغ المشرفين المعنيين.'],
            ['title' => 'تدقيق نماذج تقييم لجان المناقشة', 'status' => TaskStatusEnum::Pending, 'due_days' => 9, 'note' => 'مراجعة استمارات التقييم والتأكد من اكتمالها.'],
            ['title' => 'اعتماد التقارير النهائية الجاهزة للمناقشة', 'status' => TaskStatusEnum::Done, 'due_days' => -3, 'note' => 'مراجعة التقارير النهائية والتأكد من استيفائها للمتطلبات.'],
            ['title' => 'إعداد تقرير إنجازات لجنة الإشراف لنهاية الفصل', 'status' => TaskStatusEnum::Pending, 'due_days' => 12, 'note' => 'جمع الإحصائيات وإعداد تقرير شامل.'],
        ];

        foreach ($ctasks as $i => $t) {
            $task = Task::withTrashed()->where('team_id', null)->where('title', $t['title'])->first();
            if (! $task) {
                $task = Task::create([
                    'team_id' => null,
                    'term_id' => $termId,
                    'title' => $t['title'],
                    'description' => $t['note'],
                    'status' => $t['status'],
                    'due_date' => now()->addDays($t['due_days'])->toDateString(),
                    'created_by' => $creator->id,
                ]);
            }
            $ass = $committee->take(min(3, $committee->count()))->pluck('id')->all();
            if ($i % 2 === 1 && $committee->count() >= 4) {
                $ass = $committee->skip(1)->take(3)->pluck('id')->all();
            }
            if (! empty($ass)) {
                $task->assignees()->sync($ass);
            }
            if (! $task->notes()->exists()) {
                $n = TaskNote::create([
                    'task_id' => $task->id,
                    'user_id' => $ass[0] ?? $creator->id,
                    'note' => $t['note'].' يتم المتابعة بشكل دوري ورفع المستجدات للجنة.',
                ]);
                $n->forceFill(['created_at' => now()->subDays(2), 'updated_at' => now()->subDays(2)])->save();
            }
        }

        // Supervisor tasks
        $stasks = [
            ['title' => 'تحديث خطة المتابعة الأسبوعية لمجموعات المشرف', 'status' => TaskStatusEnum::Done, 'due_days' => -5],
            ['title' => 'مراجعة التقارير النهائية المرفوعة من المجموعات', 'status' => TaskStatusEnum::InProgress, 'due_days' => 3],
            ['title' => 'رفع تقرير الغياب والإنجاز للشهر الحالي', 'status' => TaskStatusEnum::Pending, 'due_days' => 8],
            ['title' => 'ترشيح مشاريع متميزة للمشاركة في معرض المشاريع', 'status' => TaskStatusEnum::Review, 'due_days' => 1],
        ];

        foreach ($stasks as $i => $t) {
            $task = Task::withTrashed()->where('team_id', null)->where('title', $t['title'])->first();
            if (! $task) {
                $task = Task::create([
                    'team_id' => null,
                    'term_id' => $termId,
                    'title' => $t['title'],
                    'description' => 'مهمة موجهة للمشرفين لمتابعة مجموعاتهم وضمان سير العمل وفق الخطة.',
                    'status' => $t['status'],
                    'due_date' => now()->addDays($t['due_days'])->toDateString(),
                    'created_by' => $creator->id,
                ]);
            }
            $ass = $supervisors->take(min(4, $supervisors->count()))->pluck('id')->all();
            if ($i === 1 && $supervisors->count() >= 6) {
                $ass = $supervisors->skip(2)->take(4)->pluck('id')->all();
            }
            if (! empty($ass)) {
                $task->assignees()->sync($ass);
            }
            if (! $task->notes()->exists()) {
                $n = TaskNote::create([
                    'task_id' => $task->id,
                    'user_id' => $ass[0] ?? $creator->id,
                    'note' => 'يرجى إنجاز المهمة في الموعد المحدد ورفع ما يلزم.',
                ]);
                $n->forceFill(['created_at' => now()->subDays(1), 'updated_at' => now()->subDays(1)])->save();
            }
        }
    }
}
