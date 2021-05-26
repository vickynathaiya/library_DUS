<?php

namespace Systruss\CryproWallet\Providers;

use Illuminate\Support\ServiceProvider;
use Systruss\CryproWallet\Commands\ScheduleJob;
use Systruss\CryproWallet\Commands\Register;
use Systruss\CryproWallet\Commands\Admin;
use Systruss\CryproWallet\Commands\Cron;


class CryproWalletServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ScheduleJob::class,
                SendTransactions::class,
                Register::class,
                Admin::class,
                Cron::class,

            ]);
        }
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}