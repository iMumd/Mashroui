@props(['title' => 'مسار'])
<!DOCTYPE html>
<html lang="ar" dir="rtl" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $title }}</title>
</head>
<body style="margin:0; padding:0; background-color:#f6f8fb; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%; font-family:'Segoe UI', Tahoma, Arial, 'Helvetica Neue', sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f6f8fb;">
        <tr>
            <td align="center" style="padding:24px 12px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px; max-width:100%; background-color:#ffffff; border:1px solid #e7ecf3; border-radius:4px;">
                    <!-- شريط الهوية العلوي -->
                    <tr>
                        <td style="height:6px; line-height:6px; font-size:6px; background-color:#005BAA; background:linear-gradient(90deg, #005BAA 0%, #0E699C 55%, #62BB46 100%);">&nbsp;</td>
                    </tr>
                    <!-- الترويسة -->
                    <tr>
                        <td align="right" style="padding:26px 32px 6px 32px;">
                            <div style="font-size:26px; font-weight:800; color:#005BAA; letter-spacing:.5px;">مسار</div>
                            <div style="font-size:13px; color:#64748b; margin-top:3px;">منصة إدارة مشاريع التخرج — الكلية الجامعية للعلوم التطبيقية</div>
                        </td>
                    </tr>
                    <!-- المحتوى -->
                    <tr>
                        <td align="right" style="padding:14px 32px 28px 32px; color:#334155; font-size:15px; line-height:1.9;">
                            {{ $slot }}
                        </td>
                    </tr>
                    <!-- التذييل -->
                    <tr>
                        <td align="right" style="padding:18px 32px; background-color:#f6f8fb; border-top:1px solid #e7ecf3; color:#94a3b8; font-size:12px; line-height:1.8;">
                            هذه رسالة آلية من منصة مسار، فضلًا لا تردّ عليها.<br>
                            © {{ date('Y') }} الكلية الجامعية للعلوم التطبيقية — جميع الحقوق محفوظة.
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
