<?php

namespace App\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        Log::debug('AppServiceProvider: registering services');
    }

    public function boot(): void
    {
        Log::info('AppServiceProvider: application booted');
    }
}
