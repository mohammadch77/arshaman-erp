<?php

use App\Livewire\Core\Auth\AcceptInvitation;
use App\Livewire\Core\Auth\Login;
use App\Livewire\Core\Users\AssignRole;
use App\Livewire\Core\Users\InviteUser;
use App\Livewire\Core\Users\UserCreate;
use App\Livewire\Core\Users\UserIndex;
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

Route::livewire('/theme-showcase', 'pages::theme-showcase');
