<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * مناقش خارجي دائم (ليس مشرفًا ولا عضو لجنة ولا حساب مستخدم) — سجلّ قابل لإعادة الاستخدام
 * يظهر في قائمة اختيار المناقشين. الاسم الكامل (مع اللقب) هو ما يُضاف إلى نص لجنة المناقشة.
 */
#[Fillable([
    'name',
    'title',
    'whatsapp',
    'active',
])]
class Examiner extends Model
{
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    /** الاسم كما يظهر في لجنة المناقشة: اللقب + الاسم (مثل «د. سامر خليل») */
    public function displayName(): string
    {
        $title = trim((string) $this->title);

        return trim($title === '' ? $this->name : $title.' '.$this->name);
    }
}
