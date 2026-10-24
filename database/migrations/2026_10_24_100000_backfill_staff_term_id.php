<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ربط حسابات المشرفين وأعضاء لجنة الإشراف القدامى بفصلهم:
 * كانت هذه الحسابات تُنشأ بلا term_id (عابرة للفصول)، وبعد أن صارت كل الفصول تملك
 * مشرفيها ولجنتها بشكل مستقل يجب ألا تبقى بلا فصل حتى لا تختفي من القوائم.
 *
 * الهدف ليس "الفصل الحالي" بالضرورة: لو أنشأ المستخدم فصلًا جديدًا قبل الترقية فسيكون
 * الحالي فارغًا، وضمّ الكوادر القديمة إليه يعيد نفس المشكلة (نفس المشرفين واللجنة).
 * لذلك نستخدم الفصل الذي يضم أكبر عدد من الفرق = الفصل الذي بُنيت فيه البيانات فعليًا.
 */
return new class extends Migration
{
    public function up(): void
    {
        $termId = DB::table('teams')
            ->whereNotNull('term_id')
            ->select('term_id', DB::raw('count(*) as teams_count'))
            ->groupBy('term_id')
            ->orderByDesc('teams_count')
            ->value('term_id');

        $termId ??= DB::table('academic_terms')->where('is_current', true)->value('id');

        if (! $termId) {
            return;
        }

        DB::table('users')
            ->whereIn('role', ['supervisor', 'committee'])
            ->whereNull('term_id')
            ->update(['term_id' => $termId, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // لا رجوع: فصل الحسابات مرة أخرى عن الفصل تفقدها ظهورها في القوائم
    }
};
