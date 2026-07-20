<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class WhatsappNumber implements ValidationRule
{
    public const PATTERN = '/^(970|972)5\d{8}$/';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match(self::PATTERN, $value)) {
            $fail('رقم الواتساب يجب أن يبدأ بـ 970 أو 972 ويتبعه رقم محمول صحيح.');
        }
    }
}
