<?php

namespace App\Models;

use App\Enums\NotificationChannelEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['meeting_id', 'remind_at', 'channel', 'sent_at'])]
class MeetingReminder extends Model
{
    protected function casts(): array
    {
        return [
            'remind_at' => 'datetime',
            'channel' => NotificationChannelEnum::class,
            'sent_at' => 'datetime',
        ];
    }

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }
}
