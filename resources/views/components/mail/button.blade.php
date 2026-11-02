@props(['url'])
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:22px 0;">
    <tr>
        <td align="center" style="border-radius:4px; background-color:#005BAA; background:linear-gradient(135deg, #0E699C 0%, #005BAA 100%);">
            <a href="{{ $url }}" target="_blank" rel="noopener" style="display:inline-block; padding:13px 32px; font-size:15px; font-weight:700; color:#ffffff; text-decoration:none; border-radius:4px;">{{ $slot }}</a>
        </td>
    </tr>
</table>
