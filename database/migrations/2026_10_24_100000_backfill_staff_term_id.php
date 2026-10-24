<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ربط حسابات المشرفين وأعضاء لجنة الإشراف القدامى بالفصل الحالي:
 * كانت هذه الحسابات تُنشأ بلا term_id (عابرة للفصول)، وبعد أن صارت كل الفصول تملك
 * مشرفيها ولجنتها بشكل مستقل يجب ألا تبقى بلا فصل حتى لا تختفي من قوائم الفصل الحالي.
 */
return new class extends Migration
{
    public function up(): void
    {
        $termId = DB::table('academic_terms')->where('is_current', true)->value('id');

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
        // لا رجوع: فصل الحسابات مرة أخرى عن الفصل الحالي يفقدها ظهورها في القوائم
    }
};
