<?php

namespace Database\Seeders\Concerns;

use App\Models\User;

/**
 * أدوات موحّدة لزرع المستخدمين بشكل آمن لإعادة التشغيل:
 * - upsertUser(): مطابقة بالبريد أولاً، ثم بالجزء المحلي قبل @ (بريدات قديمة @mashroui.local)،
 *   ثم برقم الموظف — وعند المطابقة تُملأ الحقول الفارغة فقط (لا تُستبدل القيم الموجودة أبداً).
 * - genderOf(): استنتاج الجنس من الاسم الأول (لائحة أسماء أنثوية + افتراضي ذكر).
 */
trait UpsertsUsers
{
    /** أسماء أولى أنثوية شائعة في بيانات الزرع (طلاب وطالبات وأعضاء لجنة) */
    private const FEMALE_NAMES = [
        'آية', 'سارة', 'مريم', 'نور', 'رهف', 'دعاء', 'هبة', 'ملك', 'شهد', 'لينا', 'بيان', 'إسراء',
        'رغد', 'هديل', 'تسنيم', 'دانة', 'جود', 'تالا', 'جنى', 'رنيم', 'نادين', 'ديما', 'لارا', 'رزان',
        'ريم', 'ميس', 'يارا', 'نغم', 'حلا', 'ميار', 'وعد', 'ياسمين', 'سيرين', 'رانيا', 'رنا', 'هالة',
        'إيمان', 'منى', 'أسماء', 'منار',
    ];

    /** استنتاج الجنس من الاسم الكامل (يتجاهل الألقاب د./م./أ.) */
    protected function genderOf(string $name): string
    {
        $parts = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);
        $first = '';

        foreach ($parts as $part) {
            if (! in_array($part, ['د.', 'م.', 'أ.', 'د', 'م', 'أ'], true)) {
                $first = $part;
                break;
            }
        }

        if ($first === 'نور' && isset($parts[1]) && $parts[1] === 'الدين') {
            return 'male';
        }

        return in_array($first, self::FEMALE_NAMES, true) ? 'female' : 'male';
    }

    /** البحث عن مستخدم: بريده أولاً، ثم الجزء المحلي قبل @ (لبريدات @mashroui.local القديمة)، ثم رقم الموظف */
    protected function findUser(string $email, ?string $employeeNumber = null): ?User
    {
        $user = User::where('email', $email)->first();

        if (! $user) {
            $local = \Illuminate\Support\Str::before($email, '@');
            $pattern = addcslashes($local, '%_\\').'@%';
            $user = User::whereRaw('email LIKE ?', [$pattern])->first();
        }

        if (! $user && $employeeNumber) {
            $user = User::where('employee_number', $employeeNumber)->first();
        }

        return $user;
    }

    /**
     * إنشاء/تحديث مستخدم بشكل آمن لإعادة التشغيل:
     * يملأ الحقول الفارغة فقط عند المطابقة (لا يمسح اسم/بريد/كلمة مرور/واتساب موجودة)،
     * ويعيد المستخدم المطابق حتى لو كان بريده القديم بمجال مختلف.
     */
    protected function upsertUser(string $email, array $values, ?string $employeeNumber = null): User
    {
        $user = $this->findUser($email, $employeeNumber);

        if ($user) {
            $fill = [];

            foreach ($values as $key => $value) {
                if ($value === null || in_array($key, ['email', 'created_at', 'updated_at'], true)) {
                    continue;
                }

                $current = $user->getAttributeValue($key);

                if ($current === null || $current === '') {
                    $fill[$key] = $value;
                }
            }

            if ($fill) {
                $user->update($fill);
            }

            $user = $user->fresh();
        } else {
            $values['email'] = $email;
            $user = User::create($values);
        }

        // أعضاء لجنة الإشراف: ربط التخصص في pivot حتى يعمل SpecializationScope بشكل متسق
        if ($user->role?->value === 'committee' && $user->specialization_id) {
            $user->specializations()->syncWithoutDetaching([$user->specialization_id]);
        }

        return $user;
    }
}
