<?php

namespace App\Livewire\CRM\Public;

use App\Modules\Core\Models\Company;
use App\Modules\CRM\Actions\SubmitContactForm;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * فرم عمومی «تماس با ما» — بدون middleware auth، بدون تکیه به CompanyContext
 * (کاربر مهمان است). عمداً در فضای نام Public جدا از App\Livewire\CRM\ContactForm
 * (فرم ادمین ساخت ContactSiteProfile) قرار گرفته تا با آن تصادم نکند.
 */
#[Layout('layouts.guest')]
class ContactForm extends Component
{
    public Company $company;

    public string $full_name = '';

    public string $phone = '';

    public string $email = '';

    public string $subject = '';

    public string $message = '';

    /**
     * تله ضدربات — کاربر واقعی هرگز این فیلد را نمی‌بیند (در view مخفی است).
     * فقط بات‌هایی که فرم را خودکار پر می‌کنند این را هم پر می‌کنند.
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
            'subject' => ['nullable', 'string', 'max:100'],
            'message' => ['required', 'string'],
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
            'message.required' => 'متن پیام را وارد کنید.',
        ];
    }

    public function submit(): void
    {
        $this->validate();

        try {
            $result = app(SubmitContactForm::class)->handle($this->company, [
                'full_name' => $this->full_name,
                'phone' => $this->phone,
                'email' => $this->email ?: null,
                'subject' => $this->subject ?: null,
                'message' => $this->message,
                'honeypot' => $this->website,
            ], request()->ip());
        } catch (ThrottleRequestsException) {
            $this->addError('message', 'تعداد درخواست‌های شما بیش از حد مجاز است. کمی بعد دوباره تلاش کنید.');

            return;
        }

        // نتیجه null یعنی honeypot پر بود (بات) — بدون افشای این موضوع، همان
        // پیام موفقیت به کاربر (یا بات) نشان داده می‌شود تا بات تشخیص ندهد رد شد.
        unset($result);

        $this->reset(['full_name', 'phone', 'email', 'subject', 'message', 'website']);
        $this->submitted = true;
    }

    public function render()
    {
        return view('livewire.crm.public.contact-form');
    }
}
