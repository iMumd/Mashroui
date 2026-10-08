<?php

namespace App\Console\Commands;

use App\Enums\DiscussionStatusEnum;
use App\Enums\ProjectStatusEnum;
use App\Models\Discussion;
use App\Services\ProjectService;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('projects:auto-complete')]
#[Description('Mark projects as completed once their confirmed final discussion date/time has passed')]
class AutoCompleteProjects extends Command
{
    private const LOCAL_TIMEZONE = 'Asia/Hebron';

    public function handle(ProjectService $service): void
    {
        $due = Discussion::query()
            ->where('status', DiscussionStatusEnum::Confirmed)
            ->whereHas('project', fn ($q) => $q->where('status', ProjectStatusEnum::InProgress))
            ->with('project')
            ->get()
            ->filter(function (Discussion $discussion) {
                $at = Carbon::parse(
                    $discussion->discussion_date->toDateString().' '.$discussion->discussion_time,
                    self::LOCAL_TIMEZONE
                );

                return $at->isPast();
            });

        foreach ($due as $discussion) {
            $service->complete($discussion->project);
        }

        $this->info("Auto-completed {$due->count()} project(s).");
    }
}
