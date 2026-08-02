<?php

namespace App\Livewire\Core\Parties;

use App\Modules\Core\Actions\CreatePartyRecord;
use App\Modules\Core\Actions\UpdatePartyRecord;
use App\Modules\Core\Enums\PartyType;
use App\Modules\Core\Models\Party;
use App\Modules\Core\Services\CompanyContext;
use Livewire\Component;
use Mary\Traits\Toast;

class PartyForm extends Component
{
    use Toast;

    public ?Party $record = null;

    public string $name = '';

    public string $party_type = 'individual';

    public bool $is_customer = false;

    public bool $is_supplier = false;

    public string $phone = '';

    public string $email = '';

    public string $economic_code = '';

    public string $address = '';

    public function mount(?string $party = null): void
    {
        if ($party) {
            $this->record = Party::findOrFail($party);
            $this->authorize('update', $this->record);

            $this->name = $this->record->name;
            $this->party_type = $this->record->party_type->value;
            $this->is_customer = $this->record->is_customer;
            $this->is_supplier = $this->record->is_supplier;
            $this->phone = (string) $this->record->phone;
            $this->email = (string) $this->record->email;
            $this->economic_code = (string) $this->record->economic_code;
            $this->address = (string) $this->record->address;

            return;
        }

        $this->authorize('create', Party::class);
    }

    public function getPartyTypeOptionsProperty(): array
    {
        return array_map(fn (PartyType $case) => ['id' => $case->value, 'name' => $case->label()], PartyType::cases());
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'party_type' => ['required', 'string'],
            'is_customer' => ['boolean'],
            'is_supplier' => ['boolean'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:200'],
            'economic_code' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string'],
        ];
    }

    public function save(CreatePartyRecord $createAction, UpdatePartyRecord $updateAction, CompanyContext $companyContext): void
    {
        $data = $this->validate();

        if (! $data['is_customer'] && ! $data['is_supplier']) {
            $this->addError('is_customer', 'حداقل یکی از مشتری یا تأمین‌کننده باید انتخاب شود.');

            return;
        }

        $data['phone'] = $data['phone'] ?: null;
        $data['email'] = $data['email'] ?: null;
        $data['economic_code'] = $data['economic_code'] ?: null;
        $data['address'] = $data['address'] ?: null;

        if ($this->record) {
            $updateAction->handle($this->record, $data, auth()->user());
            $this->success('اطلاعات طرف‌حساب به‌روزرسانی شد.', redirectTo: route('parties.index'));

            return;
        }

        $data['owner_company_id'] = $companyContext->id();

        $createAction->handle($data, auth()->user());
        $this->success('طرف‌حساب جدید ساخته شد.', redirectTo: route('parties.index'));
    }

    public function render()
    {
        return view('livewire.core.parties.party-form');
    }
}
