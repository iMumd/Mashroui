<?php

namespace Database\Seeders;

use App\Enums\RoleEnum;
use App\Models\AcademicTerm;
use App\Models\Specialization;
use App\Models\User;
use Database\Seeders\Concerns\UpsertsUsers;

/**
 * تكملة حقول جميع المستخدمين القائمين: جنس، ومنطقة (للطلاب)، وتخصص (لكل غير super_admin)،
 * وpivot التخصصات لأعضاء لجنة الإشراف، وأرقام جامعية/موظفين وواتساب ناقصة.
 * آمن لإعادة التشغيل: لا يملأ إلا الحقول الفارغة ولا يمسح أي قيمة موجودة.
 */
class UserCompletionSeeder extends Seeder
{
    use UpsertsUsers;

    private const REGIONS = ['gaza', 'middle', 'south'];

    public function run(): void
    {
        $termId = AcademicTerm::where('is_current', true)->value('id');
        $specIds = Specialization::pluck('id')->all();

        User::withoutTrashed()->orderBy('id')->chunkById(100, function ($users) use ($termId, $specIds) {
            foreach ($users as $user) {
                $fill = [];

                // 1) الجنس لأي مستخدم (super_admin مشمول) — من الاسم
                if ($user->gender === null) {
                    $fill['gender'] = $this->genderOf($user->name);
                }

                // 2) المنطقة مطلوبة للطلاب
                if ($user->role === RoleEnum::Student && $user->region === null) {
                    $fill['region'] = self::REGIONS[$user->id % count(self::REGIONS)];
                }

                // 3) التخصص مطلوب لغير super_admin (يظهر في الكروت وتقييم المقترحات)
                if ($user->role !== RoleEnum::SuperAdmin && $user->specialization_id === null && $specIds) {
                    $fill['specialization_id'] = $specIds[$user->id % count($specIds)];
                }

                // 4) الفصل الحالي للطلاب
                if ($user->role === RoleEnum::Student && $user->term_id === null && $termId) {
                    $fill['term_id'] = $termId;
                }

                // 5) واتساب وأرقام ناقصة (قيم افتراضية واقعية غير متعارضة)
                if ($user->whatsapp === null) {
                    $fill['whatsapp'] = '97059'.str_pad((string) (7000000 + $user->id * 7919), 7, '0', STR_PAD_LEFT);
                }

                if ($user->role === RoleEnum::Student && $user->university_number === null) {
                    $fill['university_number'] = (string) (120200000 + $user->id * 7919);
                }

                if (in_array($user->role, [RoleEnum::Supervisor, RoleEnum::Committee], true) && $user->employee_number === null) {
                    $fill['employee_number'] = 'EMP-9'.str_pad((string) $user->id, 3, '0', STR_PAD_LEFT);
                }

                if ($fill) {
                    $user->update($fill);
                }

                // 6) ربط pivot التخصصات لأعضاء لجنة الإشراف حتى يعمل SpecializationScope
                if ($user->role === RoleEnum::Committee && $user->specialization_id) {
                    $user->specializations()->syncWithoutDetaching([$user->specialization_id]);
                }

                $user->effectiveSpecializationIdsCache = null;
            }
        });
    }
}
