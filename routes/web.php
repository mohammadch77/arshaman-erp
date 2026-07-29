<?php

use App\Livewire\Core\Auth\AcceptInvitation;
use App\Livewire\Core\Auth\Login;
use App\Livewire\Core\Users\AssignRole;
use App\Livewire\Core\Users\InviteUser;
use App\Livewire\Core\Users\UserCreate;
use App\Livewire\Core\Users\UserIndex;
use App\Livewire\HR\AttendanceIndex;
use App\Livewire\HR\EmployeeForm;
use App\Livewire\HR\EmployeeIndex;
use App\Livewire\HR\LeaveIndex;
use App\Livewire\HR\MonthlyAttendanceReport;
use App\Livewire\HR\SelfService\MyAttendance;
use App\Livewire\HR\SelfService\MyLeaves;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::livewire('/login', Login::class)->name('login');

Route::livewire('/invitations/{token}/accept', AcceptInvitation::class)->name('invitations.accept');

Route::get('/logout', function () {
    Auth::logout();

    session()->invalidate();
    session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

Route::livewire('/', 'pages::home')->middleware('auth')->name('home');

Route::livewire('/users', UserIndex::class)->middleware('auth')->name('users.index');
Route::livewire('/users/create', UserCreate::class)->middleware('auth')->name('users.create');
Route::livewire('/users/roles', AssignRole::class)->middleware('auth')->name('users.assign-role');
Route::livewire('/users/invite', InviteUser::class)->middleware('auth')->name('users.invite');

Route::livewire('/employees', EmployeeIndex::class)->middleware('auth')->name('employees.index');
Route::livewire('/employees/create', EmployeeForm::class)->middleware('auth')->name('employees.create');
Route::livewire('/employees/{employee}/edit', EmployeeForm::class)->middleware('auth')->name('employees.edit');

Route::livewire('/attendance', AttendanceIndex::class)->middleware('auth')->name('attendance.index');
Route::livewire('/attendance/monthly-summary', MonthlyAttendanceReport::class)->middleware('auth')->name('attendance.monthly-summary');
Route::livewire('/my/attendance', MyAttendance::class)->middleware('auth')->name('my-attendance');

Route::livewire('/leaves', LeaveIndex::class)->middleware('auth')->name('leaves.index');
Route::livewire('/my/leaves', MyLeaves::class)->middleware('auth')->name('my-leaves');

Route::livewire('/theme-showcase', 'pages::theme-showcase');
