<?php

namespace Systruss\SchedTransactions\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Systruss\SchedTransactions\Models\Senders;

class Cron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crypto:cron {action}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'managing cron';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $action = $this->argument('action');
        if (Schema::hasTable('senders')) {
            $sender = Senders::first();
            if ($sender) {
                //check is task scheduling active
                switch ($action) {
                    case "start":
                        $this->info("starting transactions tasks");
                        $sender->sched_active = true;
                        break;
                    case "stop":
                        $this->info("stoping transactions tasks");
                        $sender->sched_active = false;
                        break;
                    default:
                        $this->info('usage : php artisan cron:transactions stop/start ');
                        $quit = 0;
                }
            } else {
                $this->info('No delegate registered, to register a delegate run : php artisan crypto:register');
                return false;
            }
        } else {
            $this->info('No delegate table, did you forget to run : php artisan migrate');
            return false;
        }
    }        
}
