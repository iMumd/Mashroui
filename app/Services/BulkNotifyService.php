<?php

namespace App\Services;

use App\Enums\DeliveryStatusEnum;
use App\Enums\NotificationChannelEnum;
use App\Jobs\SendCredentialsMessage;
use App\Models\InviteLink;
use App\Models\MessageDelivery;
use App\Models\User;

class BulkNotifyService
{
    public function preview(array $userIds, NotificationChannelEnum $channel): array
    {
        $users = User::whereIn('id', $userIds)->get();
        $valid = [];
        $invalid = [];

        foreach ($users as $user) {
            $reason = $this->ineligibilityReason($user, $channel);

            if ($reason) {
                $invalid[] = ['user_id' => $user->id, 'name' => $user->name, 'reason' => $reason];

                continue;
            }

            $valid[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'contact' => $channel === NotificationChannelEnum::Email ? $user->email : $user->whatsapp,
            ];
        }

        return [
            'total' => count($userIds),
            'valid_count' => count($valid),
            'invalid_count' => count($invalid),
            'sample' => array_slice($valid, 0, 5),
            'invalid' => $invalid,
        ];
    }

    public function send(array $userIds, NotificationChannelEnum $channel, string $context = 'credentials'): array
    {
        $users = User::whereIn('id', $userIds)->get();
        $dispatched = [];
        $skipped = [];

        foreach ($users as $user) {
            if ($this->ineligibilityReason($user, $channel)) {
                $skipped[] = $user->id;

                continue;
            }

            $delivery = MessageDelivery::create([
                'user_id' => $user->id,
                'context' => $context,
                'channel' => $channel,
                'status' => DeliveryStatusEnum::Pending,
                'retries' => 0,
            ]);

            SendCredentialsMessage::dispatch($delivery, $this->activeInviteFor($user));

            $dispatched[] = $delivery->id;
        }

        return ['dispatched' => $dispatched, 'skipped' => $skipped];
    }

    public function activeInviteFor(User $user): ?InviteLink
    {
        return InviteLink::where('user_id', $user->id)->whereNull('used_at')->latest()->first();
    }

    private function ineligibilityReason(User $user, NotificationChannelEnum $channel): ?string
    {
        if (! $this->activeInviteFor($user)) {
            return 'لا يوجد رابط دعوة نشط لهذا المستخدم.';
        }

        if ($channel === NotificationChannelEnum::Whatsapp && ! $user->whatsapp) {
            return 'لا يوجد رقم واتساب مسجّل لهذا المستخدم.';
        }

        if ($channel === NotificationChannelEnum::Email && ! $user->email) {
            return 'لا يوجد بريد إلكتروني مسجّل لهذا المستخدم.';
        }

        return null;
    }
}
