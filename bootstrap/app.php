<?php

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // Callback gateway dan MikroTik tidak membawa token CSRF; keamanannya dari signature / token webhook.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        // Pengunjung yang belum login diarahkan ke login yang sesuai: portal member VPN atau panel admin.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('vpn-tunnel/*') ? route('vpn-member.login') : route('login'));

        $middleware->alias([
            'role' => RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
