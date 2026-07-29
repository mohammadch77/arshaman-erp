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
use App\Livewire\HR\SelfService\MyAttendance;
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
Route::livewire('/my/attendance', MyAttendance::class)->middleware('auth')->name('my-attendance');

Route::livewire('/theme-showcase', 'pages::theme-showcase');
