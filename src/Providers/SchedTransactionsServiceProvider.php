<?php

namespace Systruss\SchedTransactions\Providers;

use Illuminate\Support\ServiceProvider;
use Systruss\SchedTransactions\Commands\ScheduleJob;

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
            ]);
        }
        // $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
        \Artisan::call('migrate', array('--path' => 'database/migrations','--force' => true)));
    }
}