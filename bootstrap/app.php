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
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (\ErrorException $e) {
            if (str_contains($e->getMessage(), 'tempnam(): file created in the system\'s temporary directory')) {
                return false;
            }
        });
    })
    ->registered(function ($app) {
        if (isset($_SERVER['VERCEL_URL'])) {
            $app->useStoragePath('/tmp');
        }
    })
    ->create();
