<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdministrator::class,
            'site_foreman' => \App\Http\Middleware\EnsureUserIsSiteForeman::class,
            'applications' => \App\Http\Middleware\EnsureUserCanAccessApplications::class,
            'supply_head' => \App\Http\Middleware\EnsureUserIsSupplyHead::class,
            'report_layout_designer' => \App\Http\Middleware\EnsureUserIsReportLayoutDesigner::class,
            'report_layout_catalog' => \App\Http\Middleware\EnsureUserCanViewReportLayoutCatalog::class,
            'layout_application_reports' => \App\Http\Middleware\EnsureUserCanLayoutApplicationReports::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
