<x-mail.layout title="تفعيل حسابك على منصة مسار">
    <p style="margin:0 0 14px; font-size:17px; font-weight:700; color:#0f172a;">مرحبًا {{ $name }}،</p>
    <p style="margin:0 0 10px;">تم إنشاء حساب لك على منصة <strong>مسار</strong> لإدارة مشاريع التخرج في الكلية الجامعية للعلوم التطبيقية.</p>
    <p style="margin:0 0 4px;">لتفعيل حسابك وتعيين كلمة المرور الخاصة بك، اضغط الزر التالي:</p>

    <x-mail.button :url="$url">تفعيل الحساب وتعيين كلمة المرور</x-mail.button>

    <p style="margin:0 0 6px; color:#64748b; font-size:13px;">إن لم يعمل الزر، انسخ الرابط التالي والصقه في المتصفح:</p>
    <p style="margin:0 0 18px; word-break:break-all;"><a href="{{ $url }}" style="color:#005BAA;">{{ $url }}</a></p>

    <p style="margin:0; color:#64748b; font-size:13px;">إن لم تكن تتوقّع هذه الرسالة يمكنك تجاهلها بأمان.</p>
</x-mail.layout>
