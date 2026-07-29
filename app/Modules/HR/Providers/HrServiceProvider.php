<?php

namespace App\Modules\HR\Providers;

use App\Modules\HR\Models\Attendance;
use App\Modules\HR\Models\Employee;
use App\Modules\HR\Models\Leave;
use App\Modules\HR\Models\MonthlyAttendanceSummary;
use App\Modules\HR\Policies\AttendancePolicy;
use App\Modules\HR\Policies\EmployeePolicy;
use App\Modules\HR\Policies\LeavePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class HrServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(Attendance::class, AttendancePolicy::class);
        Gate::policy(MonthlyAttendanceSummary::class, AttendancePolicy::class);
        Gate::policy(Leave::class, LeavePolicy::class);
    }
}
