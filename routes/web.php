<?php

use App\Livewire\Catalog\ProductForm;
use App\Livewire\Catalog\ProductIndex;
use App\Livewire\Core\Auth\AcceptInvitation;
use App\Livewire\Core\Auth\Login;
use App\Livewire\Core\Currencies\ExchangeRateForm;
use App\Livewire\Core\Currencies\ExchangeRateIndex;
use App\Livewire\Core\FiscalPeriods\FiscalPeriodIndex;
use App\Livewire\Core\Parties\PartyForm;
use App\Livewire\Core\Parties\PartyIndex;
use App\Livewire\Core\Users\AssignRole;
use App\Livewire\Core\Users\InviteUser;
use App\Livewire\Core\Users\UserCreate;
use App\Livewire\Core\Users\UserIndex;
use App\Livewire\CRM\ContactForm;
use App\Livewire\CRM\ContactIndex;
use App\Livewire\CRM\ContactProfile;
use App\Livewire\CRM\LeadBoard;
use App\Livewire\CRM\RfmSegmentIndex;
use App\Livewire\HR\AttendanceIndex;
use App\Livewire\HR\EmployeeForm;
use App\Livewire\HR\EmployeeIndex;
use App\Livewire\HR\LeaveIndex;
use App\Livewire\HR\MonthlyAttendanceReport;
use App\Livewire\HR\PayrollExpenseReport;
use App\Livewire\HR\PayrollIndex;
use App\Livewire\HR\SelfService\MyAttendance;
use App\Livewire\HR\SelfService\MyLeaves;
use App\Livewire\HR\SelfService\MyPayslips;
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

Route::livewire('/parties', PartyIndex::class)->middleware('auth')->name('parties.index');
Route::livewire('/parties/create', PartyForm::class)->middleware('auth')->name('parties.create');
Route::livewire('/parties/{party}/edit', PartyForm::class)->middleware('auth')->name('parties.edit');

Route::livewire('/exchange-rates', ExchangeRateIndex::class)->middleware('auth')->name('exchange-rates.index');
Route::livewire('/exchange-rates/create', ExchangeRateForm::class)->middleware('auth')->name('exchange-rates.create');

Route::livewire('/fiscal-periods', FiscalPeriodIndex::class)->middleware('auth')->name('fiscal-periods.index');

Route::livewire('/employees', EmployeeIndex::class)->middleware('auth')->name('employees.index');
Route::livewire('/employees/create', EmployeeForm::class)->middleware('auth')->name('employees.create');
Route::livewire('/employees/{employee}/edit', EmployeeForm::class)->middleware('auth')->name('employees.edit');

Route::livewire('/attendance', AttendanceIndex::class)->middleware('auth')->name('attendance.index');
Route::livewire('/attendance/monthly-summary', MonthlyAttendanceReport::class)->middleware('auth')->name('attendance.monthly-summary');
Route::livewire('/my/attendance', MyAttendance::class)->middleware('auth')->name('my-attendance');

Route::livewire('/leaves', LeaveIndex::class)->middleware('auth')->name('leaves.index');
Route::livewire('/my/leaves', MyLeaves::class)->middleware('auth')->name('my-leaves');

Route::livewire('/payroll', PayrollIndex::class)->middleware('auth')->name('payroll.index');
Route::livewire('/payroll/expense-report', PayrollExpenseReport::class)->middleware('auth')->name('payroll.expense-report');
Route::livewire('/my/payslips', MyPayslips::class)->middleware('auth')->name('my-payslips');

Route::livewire('/contacts', ContactIndex::class)->middleware('auth')->name('contacts.index');
Route::livewire('/contacts/create', ContactForm::class)->middleware('auth')->name('contacts.create');
Route::livewire('/contacts/{contactId}/profile', ContactProfile::class)->middleware('auth')->name('contacts.profile');

Route::livewire('/leads', LeadBoard::class)->middleware('auth')->name('leads.index');

Route::livewire('/rfm-segments', RfmSegmentIndex::class)->middleware('auth')->name('rfm-segments.index');

Route::livewire('/products', ProductIndex::class)->middleware('auth')->name('products.index');
Route::livewire('/products/create', ProductForm::class)->middleware('auth')->name('products.create');
Route::livewire('/products/{product}/edit', ProductForm::class)->middleware('auth')->name('products.edit');

// صفحه داخلی طراحی — مثل بقیه صفحات پشت auth است. تا پیش از این middleware
// نداشت و چون پوسته پیشخوان به کاربر لاگین‌شده نیاز دارد، برای مهمان به‌جای
// ریدایرکت به ورود، خطای ۵۰۰ می‌داد.
Route::livewire('/theme-showcase', 'pages::theme-showcase')->middleware('auth')->name('theme-showcase');
