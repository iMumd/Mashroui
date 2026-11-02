<x-mail.layout :title="$heading ?: 'إشعار من منصة مسار'">
    @if(!empty($heading))
        <p style="margin:0 0 14px; font-size:18px; font-weight:800; color:#0f172a;">{{ $heading }}</p>
    @endif
    <div style="color:#334155; font-size:15px; line-height:1.9;">{!! nl2br(e($body)) !!}</div>
</x-mail.layout>
