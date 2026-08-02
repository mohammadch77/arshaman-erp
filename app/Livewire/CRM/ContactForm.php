<?php

namespace App\Livewire\CRM;

use App\Modules\CRM\Actions\CreateContactSiteProfile;
use App\Modules\CRM\Models\ContactSiteProfile;
use App\Modules\CRM\Services\ContactMatcher;
use App\Modules\Core\Services\CompanyContext;
use Livewire\Component;
use Mary\Traits\Toast;

class ContactForm extends Component
{
    use Toast;

    public string $full_name = '';

    public string $phone = '';

    public string $email = '';

    public string $site_full_name = '';

    /**
     * وقتی موبایل/ایمیل واردشده به یک Contact موجود تعلق دارد که در همین شرکت
     * از قبل پروفایل دارد، این پر می‌شود تا view لینک مستقیم به همان پروفایل
     * را نشان دهد — به‌جای این‌که کاربر فقط یک پیام خطا ببیند و ندونه الان چه
     * کاری باید بکند.
     */
    public ?string $duplicateContactId = null;

    public function mount(): void
    {
        $this->authorize('create', ContactSiteProfile::class);
    }

    protected function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:200'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:200'],
            'site_full_name' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function save(CreateContactSiteProfile $action, CompanyContext $companyContext, ContactMatcher $matcher): void
    {
        $data = $this->validate();

        $data['email'] = $data['email'] ?: null;
        $data['site_full_name'] = $data['site_full_name'] ?: null;
        $data['owner_company_id'] = $companyContext->id();

        $this->duplicateContactId = null;

        $existingContact = $matcher->findExisting($data['phone'], $data['email']);

        if ($existingContact) {
            $hasProfileInThisCompany = ContactSiteProfile::withoutGlobalScopes()
                ->where('contact_id', $existingContact->id)
                ->where('owner_company_id', $data['owner_company_id'])
                ->exists();

            if ($hasProfileInThisCompany) {
                $this->duplicateContactId = $existingContact->id;
                $this->addError('phone', 'این مخاطب از قبل در این شرکت پروفایل دارد.');

                return;
            }
        }

        $action->handle($data, auth()->user(), $matcher);

        $this->success('مخاطب ثبت شد.', redirectTo: route('contacts.index'));
    }

    public function render()
    {
        return view('livewire.crm.contact-form');
    }
}
