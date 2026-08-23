<?php

namespace App\Modules\CRM\Actions;

use App\Modules\Core\Models\User;
use App\Modules\CRM\Models\Ticket;
use App\Modules\CRM\Models\TicketReply;
use Illuminate\Support\Facades\Gate;

/**
 * پاسخ به تیکت. عمداً روی تیکت بسته هم مسدود نمی‌شود — طبق درخواست صریح
 * کارفرما پاسخ به تیکت بسته باید امکان‌پذیر باشد؛ هشدار مربوطه فقط در UI
 * نشان داده می‌شود، نه اینجا.
 */
class ReplyToTicket
{
    public function handle(Ticket $ticket, string $message, User $actor): TicketReply
    {
        Gate::forUser($actor)->authorize('update', $ticket);

        return TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $actor->id,
            'message' => $message,
        ]);
    }
}
