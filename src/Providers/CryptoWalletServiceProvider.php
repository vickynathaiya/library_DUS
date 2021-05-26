<?php

namespace Systruss\CryptoWallet\Providers;

use Illuminate\Support\ServiceProvider;
use Systruss\CryptoWallet\Commands\ScheduleJob;
use Systruss\CryptoWallet\Commands\Register;
use Systruss\CryptoWallet\Commands\Admin;
use Systruss\CryptoWallet\Commands\Cron;
use Systruss\CryptoWallet\Commands\PerformTransactions;


class CryptoWalletServiceProvider extends ServiceProvider
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
                PerformTransactions::class,
                Register::class,
                Admin::class,
                Cron::class,

            ]);
        }
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}