<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\Announcement;
use App\Models\AnnouncementAttachment;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\Concerns\SeedsJourney;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AnnouncementSeeder extends Seeder
{
    use SeedsJourney;

    public function run(): void
    {
        $term = \App\Models\AcademicTerm::where('is_current', true)->firstOrFail();
        $this->termId = $term->id;

        $admin = User::where('role', RoleEnum::SuperAdmin)->first();
        $committee = User::where('role', RoleEnum::Committee)->get();
        $supervisors = User::where('role', RoleEnum::Supervisor)->get();
        $currentTeams = Team::withoutGlobalScopes()->where('term_id', $this->termId)->get();

        // Committee announcements to all committee (kind user)
        $items = [
            ['title' => 'جدول اجتماعات لجنة الإشراف', 'body' => 'يرجى الاطلاع على جدول اجتماعات اللجنة لهذا الأسبوع وحضور الاجتماع في الموعد المحدد.'],
            ['title' => 'تذكير: مراجعة مقترحات المشاريع', 'body' => 'الرجاء مراجعة المقترحات المعلقة في لوحة اللجنة وإبداء الملاحظات في أقرب وقت ممكن.'],
            ['title' => 'استمارة تقييم لجان المناقشة', 'body' => 'تم رفع استمارة تقييم المناقشات، يرجى الاطلاع والالتزام بمعايير التقييم.'],
            ['title' => 'إرشادات تجهيز التقارير النهائية', 'body' => 'يرجى توجيه المجموعات للالتزام بإرشادات إعداد التقرير النهائي وقواعد التوثيق.'],
        ];

        foreach ($items as $i => $it) {
            if (Announcement::withoutGlobalScopes()->where('kind', 'user')->where('title', $it['title'])->exists()) {
                continue;
            }

            // رسائل اللجنة تُزرع صفًا لكل عضو (target_user_id) مثل إرسال الإدارة العامة
            $batch = (string) Str::uuid();
            $path = $i >= 2 ? $this->placeholderPdf('announcements', 'ارشادات-'.$i.'.pdf') : null;

            foreach ($committee as $member) {
                $ann = Announcement::create([
                    'kind' => 'user',
                    'team_id' => null,
                    'author_id' => $admin?->id ?? $committee->first()?->id,
                    'target_user_id' => $member->id,
                    'title' => $it['title'],
                    'body' => $it['body'],
                    'type' => $i >= 2 ? 'file' : 'announcement',
                    'term_id' => $this->termId,
                    'batch' => $batch,
                    'channels' => ['platform'],
                ]);
                $at = now()->subDays($i + 1);
                $ann->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
            }

            if ($path) {
                AnnouncementAttachment::create([
                    'batch' => $batch,
                    'name' => 'ارشادات-لجنة-الإشراف.pdf',
                    'path' => $path,
                    'size' => 32768,
                ]);
            }
        }

        // Supervisor ratings
        $notes = [
            'أداء مميز في متابعة المجموعات وحثها على الإنجاز.',
            'متابعة جيدة، ويُلاحظ الاهتمام بالتوجيه والإشراف.',
            'أداء جيد جدًا، يساهم في رفع مستوى المشاريع.',
            'متابعة فعالة ودعم مستمر للمجموعات.',
            'يُظهر حرصًا كبيرًا على مخرجات الطلاب وجودتها.',
        ];
        foreach ($supervisors as $i => $sup) {
            if (Announcement::withoutGlobalScopes()->where('kind', 'supervisor')->where('author_id', $admin?->id)->where('target_user_id', $sup->id)->exists()) {
                continue;
            }
            $ann = Announcement::create([
                'kind' => 'supervisor',
                'team_id' => null,
                'author_id' => $admin?->id,
                'target_user_id' => $sup->id,
                'title' => null,
                'body' => $notes[$i % count($notes)],
                'type' => 'rating',
                'rating' => 3 + ($i % 3),
                'term_id' => $this->termId,
                'channels' => ['platform'],
            ]);
            $ann->forceFill(['created_at' => now()->subDays($i + 2), 'updated_at' => now()->subDays($i + 2)])->save();
        }

        // Add more team announcements from committee
        foreach ($currentTeams->take(10) as $i => $team) {
            if (Announcement::withoutGlobalScopes()->where('kind', 'team')->where('team_id', $team->id)->where('title', 'ملاحظات اللجنة على سير العمل')->exists()) {
                continue;
            }
            $author = $committee->first();
            $ann = Announcement::create([
                'kind' => 'team',
                'team_id' => $team->id,
                'author_id' => $author?->id,
                'title' => 'ملاحظات اللجنة على سير العمل',
                'body' => 'يرجى الالتزام بجدول المهام، رفع الملفات بانتظام، ومراجعة ملاحظات المشرف قبل كل اجتماع.',
                'type' => 'announcement',
                'term_id' => $this->termId,
            ]);
            $ann->forceFill(['created_at' => now()->subDays($i + 3), 'updated_at' => now()->subDays($i + 3)])->save();
        }
    }
}
