<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            SpecializationSeeder::class,
            AcademicTermSeeder::class,
            SuperAdminSeeder::class,
            TestUsersSeeder::class,
            FeaturedProjectSeeder::class,
            DemoDataSeeder::class,
            DemoDataExpansionSeeder::class,
            PastTermDemoSeeder::class,
            NextTermDemoSeeder::class,
            DiscussionScheduleSeeder::class,
            EvaluationResultsSeeder::class,
            ArchiveDemoSeeder::class,
            TicketSeeder::class,
            CommitteeInboxSeeder::class,
            ComposeTemplateSeeder::class,
            MembersDemoSeeder::class,
            // آخر أمر: تكملة جنس/تخصص/منطقة لكل المستخدمين (بما فيهم الحسابات القديمة)
            UserCompletionSeeder::class,
        ]);
    }
}
