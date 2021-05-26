<?php

namespace Systruss\CryptoWallet\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
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

        $this->app->booted(function () {
            $logFile = storage_path() . "/logs/schedule_job.log";
            $schedule = app(Schedule::class);
            $schedule->command('crypto:perform_transactions')->hourly()->appendOutputTo($logFile);
        });

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}