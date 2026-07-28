<?php

namespace App\Modules\Core\Exceptions;

use RuntimeException;

class InvalidInvitationException extends RuntimeException
{
    public static function notFound(): self
    {
        return new self('دعوت‌نامه یافت نشد یا نامعتبر است.');
    }

    public static function expired(): self
    {
        return new self('این دعوت‌نامه منقضی شده است.');
    }

    public static function alreadyAccepted(): self
    {
        return new self('این دعوت‌نامه قبلاً استفاده شده است.');
    }
}
