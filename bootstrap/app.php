<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureTwoFactorEnabled;
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
    ->withBroadcasting(__DIR__.'/../routes/channels.php')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\EnsurePasswordChanged::class,
        ]);

        $middleware->alias([
            'role' => CheckRole::class,
            '2fa' => EnsureTwoFactorEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->stopIgnoring(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $exceptions->stopIgnoring(\Illuminate\Auth\AuthenticationException::class);
        $exceptions->stopIgnoring(\Illuminate\Auth\Access\AuthorizationException::class);

        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, \Illuminate\Http\Request $request) {
            if ($request->header('X-Inertia') || (! $request->expectsJson() && ! $request->isJson())) {
                return back()->with('error', 'Muitas tentativas em pouco tempo. Aguarde alguns segundos e tente novamente.');
            }

            return null;
        });

        $exceptions->report(function (\Throwable $e) {
            if (! app()->bound('request')) {
                return true;
            }
            $handled = app(\App\Services\Logging\AccessIncidentLogger::class)
                ->log($e, request());
            return ! $handled;
        });
    })->create();
