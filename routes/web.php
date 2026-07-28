<?php

use App\Livewire\Core\Auth\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::livewire('/login', Login::class)->name('login');

Route::get('/logout', function () {
    Auth::logout();

    session()->invalidate();
    session()->regenerateToken();

    return redirect()->route('login');
})->name('logout');

Route::livewire('/', 'pages::home')->middleware('auth')->name('home');

Route::livewire('/theme-showcase', 'pages::theme-showcase');
