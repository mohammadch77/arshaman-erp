<?php

namespace App\Modules\Inventory\Policies;

use App\Modules\Core\Models\User;

// Warehouse منبع مشترک هلدینگ است (بند ۵.۸ CLAUDE.md)، نه مقید به یک شرکت —
// شبیه FiscalPeriodPolicy: مشاهده آزاد برای هر کاربر واردشده، مدیریت فقط holding_admin.
class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('holding_admin');
    }

    public function update(User $user): bool
    {
        return $user->hasRole('holding_admin');
    }
}
