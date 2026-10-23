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
            StaffSeeder::class,
            SuperAdminSeeder::class,
            TestUsersSeeder::class,
            JourneySeeder::class,
            PastTermJourneySeeder::class,
            NextTermJourneySeeder::class,
            StudentRosterSeeder::class,
            DiscussionScheduleSeeder::class,
            EvaluationResultsSeeder::class,
            ArchiveSeeder::class,
            TicketSeeder::class,
            StaffTasksSeeder::class,
            AnnouncementSeeder::class,
            NotificationSeeder::class,
            ComposeTemplateSeeder::class,
            UserCompletionSeeder::class,
        ]);
    }
}
