<x-mail::message>
# دعوت به سامانه آرشامان

{{ $invitation->invitedBy?->full_name }} شما را برای عضویت در سامانه ERP آرشامان دعوت کرده است.

برای تعیین نام کاربری و رمز عبور خود روی دکمه زیر کلیک کنید:

<x-mail::button :url="$acceptUrl">
قبول دعوت
</x-mail::button>

این لینک تا {{ $invitation->expires_at->translatedFormat('Y/m/d H:i') }} معتبر است.

اگر این دعوت را درخواست نکرده‌اید، این ایمیل را نادیده بگیرید.

با تشکر،<br>
{{ config('app.name') }}
</x-mail::message>
