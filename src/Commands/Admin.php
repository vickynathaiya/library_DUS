<?php

namespace Systruss\SchedTransactions\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Systruss\SchedTransactions\Models\Senders;

class Admin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crypto:admin {action}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'crypto administration';

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
        
        //check is task scheduling active
        switch ($action) {
            case "delete_sender":
                $this->info("deleting sender");
                if (Schema::hasTable('senders')) {
                    $sender = Senders::first();
                    if ($sender) {
                        Senders::truncate();
                        $this->info("senders deleted"); 
                    } else {
                        $this->info("no senders in DB");                        
                    }
                } else {
                    $this->info("no senders table exist");
                }                
                break;
            case "delete_table":
                if (Schema::hasTable('senders')) {
                    Schema::drop('senders');
                    DB::table('migrations')->where('migration',"2021_05_19_125624_create_senders_table")->delete();
                    $this->info("senders table deleted"); 
                } else {
                    $this->info("nothing to delete");
                }     
                break;
            case "show_sender":
                if (Schema::hasTable('senders')) {
                    $sender = Senders::first();
                    if ($sender) {
                        Sthis->info("network : $sender->network");
                        Sthis->info("address : $sender->address");
                        Sthis->info("passphrase : $sender->passphrase");
                        Sthis->info("sched_active : $sender->sched_active");
                    } else {
                        $this->info("no senders in DB");                        
                    }
                } else {
                    $this->info("no senders table exist");
                }                
                break;
            default:
                $this->info('usage : php artisan crypto:admin delete_sender/delete_table ');
                $quit = 0;
            }
        return 0;
    }
}
