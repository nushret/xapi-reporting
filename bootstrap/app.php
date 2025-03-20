<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use App\Http\Middleware\XapiAuth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
	api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
	$middleware->append(HandleCors::class);
	$middleware->alias([
            'xapi.auth' => \App\Http\Middleware\XapiAuth::class,
		'admin' => \App\Http\Middleware\AdminMiddleware::class,
        'user' => \App\Http\Middleware\UserMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
