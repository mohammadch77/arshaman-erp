<?php

namespace Database\Seeders;

use App\Modules\Core\Models\Permission;
use App\Modules\Core\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed the system roles and a sample set of permissions.
     */
    public function run(): void
    {
        $permissions = [
            ['name' => 'orders.view', 'module' => 'sales', 'display_name' => 'مشاهده سفارش‌ها'],
            ['name' => 'orders.manage', 'module' => 'sales', 'display_name' => 'مدیریت سفارش‌ها'],
            ['name' => 'expenses.view', 'module' => 'finance', 'display_name' => 'مشاهده هزینه‌ها'],
            ['name' => 'expenses.approve', 'module' => 'finance', 'display_name' => 'تأیید هزینه‌ها'],
            ['name' => 'reports.view', 'module' => 'reporting', 'display_name' => 'مشاهده گزارش‌ها'],
            ['name' => 'users.manage', 'module' => 'core', 'display_name' => 'مدیریت کاربران'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['name' => $permission['name']], $permission);
        }

        $roles = [
            [
                'name' => 'holding_admin',
                'display_name' => 'ادمین هلدینگ',
                'description' => 'دسترسی کامل به همه ماژول‌های یک شرکت.',
                'is_system' => true,
                'permissions' => ['orders.view', 'orders.manage', 'expenses.view', 'expenses.approve', 'reports.view', 'users.manage'],
            ],
            [
                'name' => 'accountant',
                'display_name' => 'حسابدار',
                'description' => 'دسترسی به هزینه‌ها و گزارش‌های مالی.',
                'is_system' => true,
                'permissions' => ['expenses.view', 'expenses.approve', 'reports.view'],
            ],
            [
                'name' => 'operator',
                'display_name' => 'اپراتور',
                'description' => 'دسترسی عملیاتی به سفارش‌ها.',
                'is_system' => true,
                'permissions' => ['orders.view', 'orders.manage'],
            ],
            [
                'name' => 'viewer',
                'display_name' => 'بیننده',
                'description' => 'دسترسی فقط‌خواندنی.',
                'is_system' => true,
                'permissions' => ['orders.view', 'expenses.view', 'reports.view'],
            ],
        ];

        foreach ($roles as $roleData) {
            $permissionNames = $roleData['permissions'];
            unset($roleData['permissions']);

            $role = Role::updateOrCreate(['name' => $roleData['name']], $roleData);

            $role->permissions()->sync(
                Permission::whereIn('name', $permissionNames)->pluck('id')
            );
        }
    }
}
