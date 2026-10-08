<?php

namespace App\Listeners;

use App\Enums\ProposalStatusEnum;
use App\Events\ProposalReviewed;
use App\Models\Notification;

class SendProposalReviewedNotification
{
    public function handle(ProposalReviewed $event): void
    {
        $proposal = $event->proposal;

        if ($proposal->status === ProposalStatusEnum::Approved) {
            Notification::create([
                'user_id' => $proposal->submitted_by,
                'type' => 'proposal_approved',
                'message' => "تمت الموافقة على مقترح \"{$proposal->name}\".",
            ]);

            return;
        }

        $message = "تم رفض مقترح \"{$proposal->name}\": {$proposal->rejection_reason}";

        if ($proposal->rejection_count === 2) {
            $message .= ' — تنبيه: هذه آخر فرصة متاحة لإعادة التقديم.';
        } elseif ($proposal->rejection_count >= 3) {
            $message .= ' — تم استنفاد جميع محاولات إعادة التقديم المسموحة.';
        }

        Notification::create([
            'user_id' => $proposal->submitted_by,
            'type' => 'proposal_rejected',
            'message' => $message,
        ]);
    }
}
