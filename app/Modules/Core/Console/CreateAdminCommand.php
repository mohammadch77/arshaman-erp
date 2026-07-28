<?php

namespace App\Modules\Core\Console;

use App\Modules\Core\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class CreateAdminCommand extends Command
{
    protected $signature = 'erp:create-admin';

    protected $description = 'ساخت ادمین کل با دسترسی به همه شرکت‌ها';

    public function handle(): int
    {
        $fullName = text('نام کامل', required: true);

        $email = text('ایمیل', required: true, validate: fn (string $value) => Validator::make(
            ['email' => $value],
            ['email' => 'required|email|unique:users,email']
        )->errors()->first('email'));

        $password = password('رمز عبور (حداقل ۸ کاراکتر)', validate: fn (string $value) => Validator::make(
            ['password' => $value],
            ['password' => 'required|min:8']
        )->errors()->first('password'));

        $confirmation = password('تکرار رمز عبور');

        if ($password !== $confirmation) {
            $this->error('رمز عبور و تکرار آن یکسان نیستند.');

            return self::FAILURE;
        }

        $admin = User::create([
            'full_name' => $fullName,
            'email' => $email,
            'password' => Hash::make($password),
            'is_active' => true,
            'is_super_admin' => true,
        ]);

        $this->info("ادمین کل با ایمیل «{$admin->email}» ساخته شد.");

        return self::SUCCESS;
    }
}
