<?php

use App\Livewire\Core\Auth\Login;
use App\Modules\Core\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

it('logs in with correct credentials', function () {
    $user = User::factory()->create([
        'email' => 'admin@arshaman.test',
        'password' => Hash::make('correct-password'),
    ]);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'correct-password')
        ->call('authenticate')
        ->assertRedirect('/');

    expect(auth()->check())->toBeTrue();
    expect(auth()->id())->toBe($user->id);
});

it('shows a generic error message with the wrong password', function () {
    $user = User::factory()->create([
        'email' => 'admin@arshaman.test',
        'password' => Hash::make('correct-password'),
    ]);

    Livewire::test(Login::class)
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('authenticate')
        ->assertHasErrors(['email' => 'ایمیل یا رمز عبور نادرست است.']);

    expect(auth()->check())->toBeFalse();
});
