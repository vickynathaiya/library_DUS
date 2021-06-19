<?php

namespace Vickynathaiya\Dus\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Vickynathaiya\Dus\Commands\ScheduleJob;
use Vickynathaiya\Dus\Commands\Register;
use Vickynathaiya\Dus\Commands\Admin;
use Vickynathaiya\Dus\Commands\Cron;
use Vickynathaiya\Dus\Commands\PerformTransactions;


class SchedTransactionsServiceProvider extends ServiceProvider
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
            $logFile = "logs/schedule_job.log";
            $schedule = app(Schedule::class);
            $schedule->command('crypto:perform_transactions')
                            ->hourly()
                            ->appendOutputTo(storage_path($logFile));
        });

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}