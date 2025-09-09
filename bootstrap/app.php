<?php

use App\Http\Middleware\CompanyAccess;
use App\Http\Middleware\IdentifyTenant;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
        'tenant.identify' => IdentifyTenant::class,
            'company.access' => CompanyAccess::class,
        ]);
        
        // CORS configuration
        // $middleware->web(append: [
        //     \App\Http\Middleware\HandleCors::class,
        // ]);
        
        // $middleware->api(append: [
        //     \App\Http\Middleware\HandleCors::class,
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
