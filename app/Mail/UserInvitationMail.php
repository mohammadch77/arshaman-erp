<?php

namespace App\Mail;

use App\Modules\Core\Models\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public UserInvitation $invitation, public string $acceptUrl) {}

    public function build(): self
    {
        return $this->subject('دعوت به سامانه آرشامان')
            ->markdown('emails.user-invitation');
    }
}
