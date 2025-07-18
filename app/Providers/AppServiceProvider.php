<?php

namespace App\Providers;

use App\Exceptions\Handler;
use App\Services\BranchService;
use App\Services\BranchServiceImpl;
use App\Services\CommentService;
use App\Services\CommentServiceImpl;
use App\Services\PickupCenterService;
use App\Services\PickupCenterServiceImpl;
use App\Services\TransferService;
use App\Services\TransferServiceImpl;
use App\Services\UserNotificationService;
use App\Services\UserNotificationServiceImpl;
use App\Services\WarehouseService;
use App\Services\WarehouseServiceImpl;
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
        $this->app->bind(BranchService::class,BranchServiceImpl::class);
        $this->app->bind(WarehouseService::class,WarehouseServiceImpl::class);
        $this->app->bind(PickupCenterService::class,PickupCenterServiceImpl::class);
        $this->app->bind(TransferService::class,TransferServiceImpl::class);
        $this->app->bind(CommentService::class,CommentServiceImpl::class);
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
