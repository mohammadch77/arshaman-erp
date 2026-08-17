<?php

namespace App\Modules\Process\Policies;

use App\Modules\Core\Models\User;
use App\Modules\Core\Services\CompanyContext;
use App\Modules\Process\Models\ProcessDefinition;

class ProcessDefinitionPolicy
{
    /**
     * هر نقشی در شرکت — همه باید بتوانند فهرست فرایندهای قابل‌درخواست را ببینند
     * (Session های بعدی).
     */
    public function viewAny(User $user): bool
    {
        $companyId = app(CompanyContext::class)->id();

        return $companyId !== null && $user->hasRoleInCompany($companyId);
    }

    /**
     * شرکت از خودِ $definition خوانده می‌شود، نه از CompanyContext فعال —
     * دفاع‌درعمق (بند ۹ CLAUDE.md).
     */
    public function view(User $user, ProcessDefinition $definition): bool
    {
        return $user->hasRoleInCompany($definition->owner_company_id);
    }

    public function create(User $user, ?string $companyId = null): bool
    {
        $companyId ??= app(CompanyContext::class)->id();

        if ($companyId === null) {
            return false;
        }

        return $user->hasRoleInCompany($companyId, 'holding_admin');
    }

    public function update(User $user, ProcessDefinition $definition): bool
    {
        return $user->hasRoleInCompany($definition->owner_company_id, 'holding_admin');
    }

    public function toggleActive(User $user, ProcessDefinition $definition): bool
    {
        return $user->hasRoleInCompany($definition->owner_company_id, 'holding_admin');
    }

    public function delete(User $user, ProcessDefinition $definition): bool
    {
        return $user->hasRoleInCompany($definition->owner_company_id, 'holding_admin');
    }
}
