<?php

namespace App\Models;

use App\Enums\NotificationChannelEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'context', 'channel', 'status', 'error', 'retries', 'sent_at'])]
class MessageDelivery extends Model
{
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannelEnum::class,
            'sent_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
