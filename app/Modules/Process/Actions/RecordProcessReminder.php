<?php

namespace App\Modules\Process\Actions;

use App\Modules\Core\Models\User;
use App\Modules\Process\Models\ProcessInstance;
use App\Modules\Process\Services\ProcessEngine;
use Illuminate\Support\Facades\Gate;

/**
 * یادآوری holding_admin به مسئول مرحله‌ی فعلی — بدون تأیید/رد، فقط یک لاگ جدید.
 * Authorization طبق بند ۹ CLAUDE.md داخل خودِ Action است.
 */
class RecordProcessReminder
{
    public function __construct(private readonly ProcessEngine $engine) {}

    public function handle(ProcessInstance $instance, User $actor, string $comment): void
    {
        Gate::forUser($actor)->authorize('remind', $instance);

        $this->engine->remind($instance, $actor, $comment);
    }
}
