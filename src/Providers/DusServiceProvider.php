<?php

namespace InfinitySoftwareLTD\Library_Dus\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use InfinitySoftwareLTD\Library_Dus\Commands\ScheduleJob;
use InfinitySoftwareLTD\Library_Dus\Commands\Register;
use InfinitySoftwareLTD\Library_Dus\Commands\Admin;
use InfinitySoftwareLTD\Library_Dus\Commands\Cron;
use InfinitySoftwareLTD\Library_Dus\Commands\PerformTransactions;


class Library_DusServiceProvider extends ServiceProvider
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
