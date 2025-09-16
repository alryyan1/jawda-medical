<?php

namespace App\Providers;

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
        config(['services.realtime' => [
            'url' => env('REALTIME_EVENTS_URL', 'http://localhost:4001'),
            'token' => env('REALTIME_EVENTS_TOKEN', 'changeme'),
        ]]);
    }
}
