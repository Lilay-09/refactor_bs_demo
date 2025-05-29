<?php

namespace App\Providers;

use App\Exceptions\Handler;
use App\Services\UserNotificationService;
use App\Services\UserNotificationServiceImpl;
use Helper;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Debug\ExceptionHandler as ExceptionHandlerContract;
use Log;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if(!config('app.use_redis')){
            config(['cache.default' => 'file']);
        }
        $this->app->singleton(ExceptionHandlerContract::class, Handler::class);
        $this->app->bind(UserNotificationService::class,UserNotificationServiceImpl::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (!request()->is('api/*')) {
            config([
                'cache.default' => 'file',
                'session.driver' => 'file',
            ]);
        }

    }
}
