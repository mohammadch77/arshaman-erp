<?php

namespace App\Modules\Core\Actions;

use App\Modules\Core\Models\FiscalPeriod;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * فقط قفل ساختاری (is_closed) — منطق واقعی انتقال مانده حسابداری در فاز ۶
 * اضافه می‌شود. طبق طراحی، بستن غیرقابل بازگشت است؛ برخلاف حقوق (بند ۵.۵/۹
 * CLAUDE.md) هیچ Action «بازگشایی» برای این جدول وجود ندارد.
 */
class CloseFiscalPeriod
{
    public function handle(FiscalPeriod $fiscalPeriod, User $actor): FiscalPeriod
    {
        Gate::forUser($actor)->authorize('close', $fiscalPeriod);

        if ($fiscalPeriod->is_closed) {
            throw ValidationException::withMessages([
                'is_closed' => 'این سال مالی قبلاً بسته شده است.',
            ]);
        }

        return DB::transaction(function () use ($fiscalPeriod, $actor) {
            $fiscalPeriod->update([
                'is_closed' => true,
                'closed_at' => now(),
                'closed_by_user_id' => $actor->id,
            ]);

            activity()
                ->causedBy($actor)
                ->performedOn($fiscalPeriod)
                ->withProperties([
                    'owner_company_id' => $fiscalPeriod->owner_company_id,
                    'name' => $fiscalPeriod->name,
                ])
                ->log('بستن سال مالی');

            return $fiscalPeriod->fresh();
        });
    }
}
