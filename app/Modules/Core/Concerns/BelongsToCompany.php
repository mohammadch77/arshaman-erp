<?php

namespace App\Modules\Core\Concerns;

use App\Modules\Core\Services\CompanyContext;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('owner_company', fn ($query) => $query->where(
            'owner_company_id',
            app(CompanyContext::class)->id()
        ));

        static::creating(fn ($model) => $model->owner_company_id ??= app(CompanyContext::class)->id());
    }
}
