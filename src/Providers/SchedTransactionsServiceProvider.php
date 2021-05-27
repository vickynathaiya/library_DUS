<?php

namespace Systruss\SchedTransactions\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Systruss\SchedTransactions\Commands\ScheduleJob;
use Systruss\SchedTransactions\Commands\Register;
use Systruss\SchedTransactions\Commands\Admin;
use Systruss\SchedTransactions\Commands\Cron;
use Systruss\SchedTransactions\Commands\PerformTransactions;


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
                            ->everyMinute()
                            ->sendOutputTo(storage_path($logFile));
        });

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}