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

        $action->handle($data, auth()->user(), $matcher);

        $this->success('مخاطب ثبت شد.', redirectTo: route('contacts.index'));
    }

    public function render()
    {
        return view('livewire.crm.contact-form');
    }
}
