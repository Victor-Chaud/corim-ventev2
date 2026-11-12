<?php

use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\LogoutInactive;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\StripIndexPhp;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(StripIndexPhp::class);
        $middleware->web(append: [LogoutInactive::class, SecurityHeaders::class]);
        $middleware->redirectGuestsTo('/connexion');
        $middleware->redirectUsersTo('/');
        $middleware->alias(['admin' => EnsureAdmin::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {})->create();
