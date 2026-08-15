<?php

namespace App\Livewire\CRM\Public;

use App\Modules\Core\Models\Company;
use App\Modules\CRM\Actions\CaptureCustomerSignup;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * فرم عمومی «ثبت‌نام مشتری» ویجت سایت‌ساز customer_signup_form — عیناً همان
 * الگوی App\Livewire\CRM\Public\ContactForm (بدون middleware auth، بدون
 * CompanyContext). به‌جای Contact/Lead روی contact_submissions می‌نویسد —
 * نگاه کن CaptureCustomerSignup برای دلیل کامل.
 */
#[Layout('layouts.guest')]
class CustomerSignupForm extends Component
{
    public Company $company;

    public string $full_name = '';

    public string $phone = '';

    public string $email = '';

    /**
     * تله ضدربات — همان الگوی ContactForm.
     */
    public string $website = '';

    public bool $submitted = false;

    public function mount(string $companySlug): void
    {
        $this->company = Company::where('slug', $companySlug)->firstOrFail();
    }

    protected function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'regex:/^09[0-9]{9}$/'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }

    protected function messages(): array
    {
        return [
            'full_name.required' => 'نام و نام‌خانوادگی را وارد کنید.',
            'full_name.max' => 'نام و نام‌خانوادگی نباید بیشتر از ۸۰ کاراکتر باشد.',
            'phone.required' => 'شماره موبایل را وارد کنید.',
            'phone.regex' => 'شماره موبایل معتبر نیست (مثال: ۰۹۱۲۳۴۵۶۷۸۹).',
            'email.email' => 'ایمیل معتبر نیست.',
        ];
    }

    public function submit(): void
    {
        $this->validate();

        try {
            $result = app(CaptureCustomerSignup::class)->handle($this->company, [
                'full_name' => $this->full_name,
                'phone' => $this->phone,
                'email' => $this->email ?: null,
                'honeypot' => $this->website,
            ], request()->ip());
        } catch (ThrottleRequestsException) {
            $this->addError('phone', 'تعداد درخواست‌های شما بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.');

            return;
        }

        // نتیجه null یعنی honeypot پر بود (بات) — بدون افشای این موضوع، همان
        // پیام موفقیت به کاربر (یا بات) نشان داده می‌شود.
        unset($result);

        $this->reset(['full_name', 'phone', 'email', 'website']);
        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.crm.public.customer-signup-form');
    }
}
