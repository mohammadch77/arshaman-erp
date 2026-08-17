<?php

namespace App\Modules\Process\Actions;

use App\Modules\Core\Models\User;
use App\Modules\Process\Models\ProcessInstance;
use App\Modules\Process\Services\ProcessEngine;
use Illuminate\Support\Facades\Gate;

/**
 * ویرایش/بازگردانی آخرین تصمیم خودِ actor — فقط اگر مرحله‌ی بعدی هنوز هیچ
 * اقدامی نداشته (ProcessEngine::canReverseLastDecision). Authorization طبق
 * بند ۹ CLAUDE.md داخل خودِ Action است، مستقل از این‌که کامپوننت Livewire
 * قبلش authorize زده یا نه.
 */
class ReverseProcessDecision
{
    public function __construct(private readonly ProcessEngine $engine) {}

    public function handle(ProcessInstance $instance, User $actor): void
    {
        Gate::forUser($actor)->authorize('reverseLastDecision', $instance);

        $this->engine->reverseLastDecision($instance, $actor);
    }
}
