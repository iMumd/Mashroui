<?php

namespace Database\Seeders;

use App\Enums\TaskStatusEnum;
use App\Models\Task;
use App\Models\TaskNote;
use Illuminate\Database\Seeder;

/** يضيف ملاحظات واقعية على مهام الفرق الموجودة (بعد DemoDataSeeder/DemoDataExpansionSeeder) — تشغيل لمرة واحدة فقط */
class TaskNoteSeeder extends Seeder
{
    protected array $notePool = [
        'done' => [
            'تم الانتهاء من هاي المهمة، جاهزة للمراجعة.',
            'خلصنا منها اليوم، تم اختبارها وشغالة تمام.',
            'تمام، أنجزتها وحدّثت التوثيق المرتبط فيها.',
            'خلصت، ودمجتها مع باقي الأجزاء بدون مشاكل.',
        ],
        'in_progress' => [
            'شغالين عليها حاليًا، توقعت نخلص خلال يومين.',
            'بدأت فيها، في جزئية بسيطة محتاجة نقاش مع الفريق.',
            'تقدّم جيد لحد الآن، رح أحدّث الحالة أول ما تخلص.',
            'قطعت أكتر من نص المهمة، باقي التفاصيل النهائية بس.',
        ],
        'review' => [
            'خلصت المهمة، بانتظار ملاحظات المشرف قبل الإغلاق.',
            'جاهزة للمراجعة، محتاجين تأكيد قبل الانتقال للمرحلة الجاية.',
            'راجعتها بنفسي مرتين، بس حابب رأي تاني قبل الاعتماد.',
        ],
        'pending' => [
            'بنبلش فيها الأسبوع الجاي بعد ما نخلص المهمة الحالية.',
            'محتاجين نحدد نطاقها بالتفصيل قبل البدء فيها.',
            'مجدولة، بس معلّقة لحد ما توصلنا الموارد المطلوبة.',
        ],
    ];

    public function run(): void
    {
        $tasks = Task::with(['team.leader', 'team.members.student'])->get();

        foreach ($tasks as $task) {
            $pool = $this->notePool[$task->status->value] ?? $this->notePool[TaskStatusEnum::Pending->value];

            $authors = collect([$task->team->leader])
                ->merge($task->team->members->pluck('student'))
                ->filter()
                ->unique('id')
                ->values();

            if ($authors->isEmpty()) {
                continue;
            }

            $noteCount = random_int(1, min(2, $authors->count()));
            $chosenAuthors = $authors->shuffle()->take($noteCount);

            foreach ($chosenAuthors as $author) {
                TaskNote::create([
                    'task_id' => $task->id,
                    'user_id' => $author->id,
                    'note' => $pool[array_rand($pool)],
                ]);
            }
        }
    }
}
