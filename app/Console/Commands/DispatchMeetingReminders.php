<?php

namespace App\Console\Commands;

use App\Jobs\SendMeetingReminder;
use App\Models\MeetingReminder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reminders:dispatch-meetings')]
#[Description('Dispatch due meeting reminders onto the queue')]
class DispatchMeetingReminders extends Command
{
    public function handle(): void
    {
        $due = MeetingReminder::whereNull('sent_at')
            ->where('remind_at', '<=', now())
            ->get();

        foreach ($due as $reminder) {
            SendMeetingReminder::dispatch($reminder);
        }

        $this->info("Dispatched {$due->count()} meeting reminder(s).");
    }
}
