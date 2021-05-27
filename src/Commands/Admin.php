<?php

namespace Systruss\SchedTransactions\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Systruss\SchedTransactions\Models\DelegateDb;

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
            case "delete_delegate":
                $this->info("deleting delegate");
                if (Schema::hasTable('delegate_dbs')) {
                    $delegate = DelegateDb::first();
                    if ($delegate) {
                        DelegateDb::truncate();
                        $this->info("delegate deleted"); 
                    } else {
                        $this->info("no delegate in DB");                        
                    }
                } else {
                    $this->info("no delegate table exist");
                }                
                break;
            case "delete_table":
                if (Schema::hasTable('delegate_dbs')) {
                    Schema::drop('delegate_dbs');
                    DB::table('migrations')->where('migration',"2021_05_25_080651_create_delegate_dbs_table.php")->delete();
                    $this->info("delegate table deleted"); 
                } else {
                    $this->info("nothing to delete");
                }     
                break;
            case "show_delegate":
                if (Schema::hasTable('delegate_dbs')) {
                    $delegate = DelegateDb::first();
                    if ($delegate) {
                        echo "\n network : $delegate->network \n";
                        echo "\n address : $delegate->address \n";
                        echo "\n passphrase : $delegate->passphrase \n";
                        echo "\n sched_active : $delegate->sched_active \n";
                    } else {
                        $this->info("no delegate in DB");                        
                    }
                } else {
                    $this->info("no delegate table exist");
                }                
                break;
                case "activate_sched":
                    if (Schema::hasTable('delegate_dbs')) {
                        $delegate = DelegateDb::first();
                        if ($delegate) {
                            $delegate->sched_active = true;
                            $delgate->save();
                        } else {
                            $this->info("no delegate in DB, scheduler cannot be activated");                        
                        }
                    } else {
                        $this->info("no delegate table exist, scheduler cannot be activated");
                    }                
                    break;
            default:
                $this->info('usage : php artisan crypto:admin delete_delegate/delete_table/show_delegate/activate_sched ');
                $quit = 0;
            }
        return 0;
    }
}
