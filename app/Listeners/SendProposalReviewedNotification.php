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

        Notification::create([
            'user_id' => $proposal->submitted_by,
            'type' => 'proposal_rejected',
            'message' => "تم رفض مقترح \"{$proposal->name}\": {$proposal->rejection_reason}",
        ]);
    }
}
