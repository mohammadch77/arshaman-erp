<?php

namespace App\Modules\CRM\Actions;

use App\Modules\Core\Models\User;
use App\Modules\CRM\Enums\TicketStatus;
use App\Modules\CRM\Models\Ticket;
use Illuminate\Support\Facades\Gate;

class ChangeTicketStatus
{
    public function handle(Ticket $ticket, string $newStatus, User $actor): Ticket
    {
        Gate::forUser($actor)->authorize('update', $ticket);

        $status = TicketStatus::tryFrom($newStatus);

        if ($status === null) {
            throw new \InvalidArgumentException('وضعیت تیکت نامعتبر است.');
        }

        $ticket->update(['status' => $status->value]);

        return $ticket;
    }
}
