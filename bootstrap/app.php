<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'role' => CheckRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->stopIgnoring(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $exceptions->stopIgnoring(\Illuminate\Auth\AuthenticationException::class);
        $exceptions->stopIgnoring(\Illuminate\Auth\Access\AuthorizationException::class);

        $exceptions->report(function (\Throwable $e) {
            if (! app()->bound('request')) {
                return true;
            }
            $handled = app(\App\Services\Logging\AccessIncidentLogger::class)
                ->log($e, request());
            return ! $handled;
        });
    })->create();
