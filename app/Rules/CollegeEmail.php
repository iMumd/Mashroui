<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * بريد الحساب لازم يكون من نطاق الكلية الرسمي — طلاب: student@smail.ucas.edu.ps،
 * مشرفين/لجنة إشراف: staff@ucas.edu.ps (بدون "smail.") — بدل جيميل/ياهو وغيرها.
 */
class CollegeEmail implements ValidationRule
{
    public const STUDENT_DOMAIN = 'smail.ucas.edu.ps';

    public const STAFF_DOMAIN = 'ucas.edu.ps';

    public function __construct(private readonly string $domain) {}

    public static function matches(string $email, string $domain): bool
    {
        return str_ends_with(strtolower($email), '@'.strtolower($domain));
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::matches($value, $this->domain)) {
            $fail("البريد الإلكتروني يجب أن ينتهي بـ @{$this->domain}.");
        }
    }
}
