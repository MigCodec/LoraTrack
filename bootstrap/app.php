<?php

use App\Http\Middleware\AuditMutations;
use App\Http\Middleware\EnsureUserHasPermission;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetOrganizationContext;
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
        $middleware->append(SecurityHeaders::class);
        $middleware->alias(['admin' => EnsureUserIsAdmin::class, 'permission' => EnsureUserHasPermission::class]);
        $middleware->appendToGroup('web', SetOrganizationContext::class);
        $middleware->appendToGroup('web', AuditMutations::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
