<x-mail.layout title="إعادة تعيين كلمة المرور">
    <p style="margin:0 0 14px; font-size:17px; font-weight:700; color:#0f172a;">مرحبًا {{ $name }}،</p>
    <p style="margin:0 0 10px;">وصلنا طلب لإعادة تعيين كلمة مرور حسابك على منصة مسار.</p>
    <p style="margin:0 0 4px;">اضغط الزر التالي لتعيين كلمة مرور جديدة. الرابط صالح لمدة <strong>ساعتين</strong> فقط:</p>

    <x-mail.button :url="$url">تعيين كلمة مرور جديدة</x-mail.button>

    <p style="margin:0 0 6px; color:#64748b; font-size:13px;">إن لم يعمل الزر، انسخ الرابط التالي والصقه في المتصفح:</p>
    <p style="margin:0 0 18px; word-break:break-all;"><a href="{{ $url }}" style="color:#005BAA;">{{ $url }}</a></p>

    <p style="margin:0; color:#64748b; font-size:13px;">إذا لم تطلب هذا الإجراء، تجاهل هذه الرسالة ولن يتغيّر شيء في حسابك.</p>
</x-mail.layout>
