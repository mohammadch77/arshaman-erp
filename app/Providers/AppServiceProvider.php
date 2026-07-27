<?php

namespace App\Providers;

use App\Support\Farsi;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::directive('toman', fn ($expression) => '<?php echo \\'.Farsi::class."::toToman({$expression}); ?>");
    }
}
