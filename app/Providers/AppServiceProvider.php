<?php

// свой код проекта
namespace App\Providers;

use App\Models\RequestSubmission;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }
    public function boot(): void
    {
        \Illuminate\Support\Facades\App::setLocale('ru');
        Carbon::setLocale('ru');

        Route::model('submission', RequestSubmission::class);
    }
}
