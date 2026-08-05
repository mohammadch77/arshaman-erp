<?php

namespace App\Modules\CRM\Actions;

use App\Modules\Core\Models\Company;
use App\Modules\CRM\Models\ContactSubmission;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\RateLimiter;

class SubmitContactForm
{
    public const MAX_ATTEMPTS_PER_WINDOW = 3;

    public const DECAY_SECONDS = 600;

    /**
     * مسیر عمومی/مهمان است — هیچ کاربر واردشده‌ای برای Gate::authorize وجود
     * ندارد (استثنای مستند بند ۹ CLAUDE.md، مثل bootstrapping). لایه دفاعی
     * این‌جا honeypot + rate limit + validation سطح کامپوننت است، نه Policy.
     *
     * @param  array{full_name: string, phone: string, email: ?string, subject: ?string, message: string, honeypot: string}  $data
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
            'subject' => $data['subject'] ?? null,
            'message' => $data['message'],
            'ip_address' => $ipAddress,
        ]);
    }

    protected function throttle(string $ipAddress, string $companyId): void
    {
        $key = "contact-submission:{$ipAddress}:{$companyId}";

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS_PER_WINDOW)) {
            throw new ThrottleRequestsException('تعداد درخواست‌های شما بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.');
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);
    }
}
