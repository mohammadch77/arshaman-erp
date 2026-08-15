<?php

namespace App\Modules\CRM\Actions;

use App\Modules\Core\Models\Company;
use App\Modules\CRM\Models\ContactSubmission;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\RateLimiter;

/**
 * ثبت فرم «ثبت‌نام مشتری» ویجت سایت‌ساز customer_signup_form — کپی ساختاری
 * SubmitContactForm (بند ۹ CLAUDE.md: مسیر عمومی/مهمان، بدون Gate/actor).
 * روی همان contact_submissions می‌نویسد (source='site_signup')، نه
 * Contact/Lead — نگاه کن یادداشت بخش ۴ Session ۹ در CLAUDE.md برای دلیل کامل:
 * هر Action واقعی CRM (ContactMatcher/CreateLead/RecordInteraction) یک
 * User $actor غیرقابل‌حذف می‌خواهد که بازدیدکننده مهمان ندارد.
 */
class CaptureCustomerSignup
{
    public const MAX_ATTEMPTS_PER_WINDOW = 3;

    public const DECAY_SECONDS = 600;

    /**
     * @param  array{full_name: string, phone: string, email: ?string, honeypot: string}  $data
     * @return ContactSubmission|null  null یعنی honeypot پر بود — بدون خطا، بدون ثبت.
     */
    public function handle(Company $company, array $data, string $ipAddress): ?ContactSubmission
    {
        if (filled($data['honeypot'] ?? null)) {
            return null;
        }

        $this->throttle($ipAddress, $company->id);

        return ContactSubmission::create([
            'owner_company_id' => $company->id,
            'full_name' => $data['full_name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'subject' => 'ثبت‌نام مشتری از سایت',
            'message' => 'ثبت‌نام از طریق فرم عضویت سایت.',
            'ip_address' => $ipAddress,
            'source' => 'site_signup',
        ]);
    }

    protected function throttle(string $ipAddress, string $companyId): void
    {
        $key = "customer-signup:{$ipAddress}:{$companyId}";

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS_PER_WINDOW)) {
            throw new ThrottleRequestsException('تعداد درخواست‌های شما بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.');
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);
    }
}
