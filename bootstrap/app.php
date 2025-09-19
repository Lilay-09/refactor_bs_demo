<?php

use App\Http\Middleware\CustomRateLimit;
use App\Http\Middleware\JwtAuthGenMiddlware;
use App\Http\Middleware\JwtAuthMiddleware;
use App\Http\Middleware\JwtDriverMiddleware;
use App\Http\Middleware\JwtMerchantMiddleware;
use App\Http\Middleware\Localization;
use App\Http\Middleware\UserAccess;
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
    ->withMiddleware(function (Middleware $middleware) {
        //
        $middleware->alias([
            'jwt' => JwtAuthMiddleware::class,
            'jwtDriver' => JwtDriverMiddleware::class,
            'jwtMerchant' => JwtMerchantMiddleware::class,
            'userAccess' => UserAccess::class,
            'jwtAuthGen' => JwtAuthGenMiddlware::class,
            'localize' => Localization::class,
            'rateLimit' => CustomRateLimit::class
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
