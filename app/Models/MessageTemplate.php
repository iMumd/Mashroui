<?php

namespace App\Models;

use App\Enums\NotificationChannelEnum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['name', 'channel', 'subject', 'category', 'body', 'created_by'])]
class MessageTemplate extends Model
{
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannelEnum::class,
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** يستبدل {placeholders} بنص المتن بقيمها الفعلية — مثلاً {name}, {date}, {time}, {place} */
    public function render(array $vars): string
    {
        return $this->interpolate($this->body, $vars);
    }

    /** نفس الاستبدال لكن على عنوان الرسالة (قنوات البريد فقط) */
    public function renderSubject(array $vars): string
    {
        return $this->interpolate($this->subject ?? '', $vars);
    }

    private function interpolate(string $text, array $vars): string
    {
        foreach ($vars as $key => $value) {
            $text = str_replace('{'.$key.'}', (string) $value, $text);
        }

        return $text;
    }
}
