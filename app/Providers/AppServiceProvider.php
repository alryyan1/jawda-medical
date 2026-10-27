<?php

namespace App\Providers;

use App\Models\SysmexResult;
use App\Observers\SysmexResultObserver;
use App\Services\AirtelSmsClient;
use App\Services\Contracts\SmsClient;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(SmsClient::class, function () {
            return new AirtelSmsClient;
        });

        // Sanctum::ignoreMigrations();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        config(['services.realtime' => [
            'url' => env('REALTIME_EVENTS_URL', 'http://localhost:4001'),
            'token' => env('REALTIME_EVENTS_TOKEN', 'changeme'),
        ]]);

        // Register model observers
        SysmexResult::observe(SysmexResultObserver::class);

        // The base schema migrations live under a subdirectory, which
        // `php artisan migrate` does not scan by default.
        $this->loadMigrationsFrom(database_path('migrations/system_setup'));
    }
}
