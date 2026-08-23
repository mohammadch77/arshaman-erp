<?php

namespace App\Modules\CRM\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\CRM\Models\Ticket;

/**
 * همان دو نقش بقیه Policy های CRM (holding_admin/operator) — عیناً الگوی
 * LeadPolicy. view/update عمداً شرکت را از خودِ $ticket می‌خوانند، نه از
 * CompanyContext فعال، تا از نمای ۳۶۰ هلدینگی مخاطب هم قابل‌دسترس باشد.
 */
class TicketPolicy
{
    protected function isAuthorizedForCompany(User $user, ?string $companyId): bool
    {
        if ($companyId === null) {
            return false;
        }

        return $user->hasRoleInCompany($companyId, ['holding_admin', 'operator']);
    }

    public function viewAny(User $user): bool
    {
        return $this->isAuthorizedForCompany($user, app(CompanyContext::class)->id());
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $this->isAuthorizedForCompany($user, $ticket->owner_company_id);
    }

    /**
     * بدون نمونه. $companyId اختیاری: CreateTicket شرکت هدف واقعی
     * ($data['owner_company_id']) را صریح پاس می‌دهد.
     */
    public function create(User $user, ?string $companyId = null): bool
    {
        return $this->isAuthorizedForCompany($user, $companyId ?? app(CompanyContext::class)->id());
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $this->isAuthorizedForCompany($user, $ticket->owner_company_id);
    }
}
