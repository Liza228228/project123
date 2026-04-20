<?php

namespace App\Providers;

use App\Models\RequestSubmission;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
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
        \Illuminate\Support\Facades\App::setLocale('ru');
        Carbon::setLocale('ru');

        Route::model('submission', RequestSubmission::class);
    }
}
