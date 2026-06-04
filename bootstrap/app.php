<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.auth' => \App\Http\Middleware\AuthenticateApiAccessToken::class,
            'api.admin' => \App\Http\Middleware\EnsureApiAdmin::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'connection/access',
            'connection/logout',
            'connection/session',
        ]);

        $middleware->web(prepend: [
            HandleCors::class,
        ]);

        $middleware->api(prepend: [
            HandleCors::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
