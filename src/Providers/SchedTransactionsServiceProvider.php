<?php

namespace Systruss\SchedTransactions\Providers;

use Illuminate\Support\ServiceProvider;
use Systruss\SchedTransactions\Commands\ScheduleJob;
use Systruss\SchedTransactions\Commands\Register;

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
                Register::class,
            ]);
        }
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        $this->app->booted(function () {
            $schedule = app(Schedule::class);
            $schedule->command('crypto:schedule_job')->hourly();
        });
    }
}