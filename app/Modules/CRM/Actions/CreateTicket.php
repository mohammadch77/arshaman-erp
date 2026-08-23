<?php

namespace App\Modules\CRM\Actions;

use App\Modules\Core\Models\User;
use App\Modules\CRM\Enums\TicketPriority;
use App\Modules\CRM\Enums\TicketStatus;
use App\Modules\CRM\Models\Ticket;
use Illuminate\Support\Facades\Gate;

class CreateTicket
{
    /**
     * @param  array{owner_company_id: string, contact_site_profile_id: string, subject: string, description: ?string, priority: string}  $data
     */
    public function handle(array $data, User $actor): Ticket
    {
        Gate::forUser($actor)->authorize('create', [Ticket::class, $data['owner_company_id']]);

        $priority = TicketPriority::tryFrom($data['priority'] ?? '') ?? TicketPriority::Normal;

        return Ticket::create([
            'owner_company_id' => $data['owner_company_id'],
            'contact_site_profile_id' => $data['contact_site_profile_id'],
            'subject' => $data['subject'],
            'description' => $data['description'] ?? null,
            'status' => TicketStatus::Open->value,
            'priority' => $priority->value,
            'created_by_user_id' => $actor->id,
        ]);
    }
}
