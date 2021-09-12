<?php

namespace InfinitySoftwareLTD\Library_Dus\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use InfinitySoftwareLTD\Library_Dus\Models\DelegateDb;

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


    // check if shedule run command already exist in crontab
    protected function cronjob_exists($command){
        $cronjob_exists=false;
        exec('crontab -l', $crontab,$result);
        if(isset($crontab)&&is_array($crontab)){
            $crontab = array_flip($crontab);
            if(isset($crontab[$command])){
                $cronjob_exists=true;
            }
        }
        return $cronjob_exists;
    }

    // append the schedule run command to crontab
    protected function append_cronjob($command){
        $output = [];
        if(is_string($command)&&!empty($command)&&$this->cronjob_exists($command)==FALSE){
            //add job to crontab
            $output = shell_exec("crontab -l | { cat; echo '$command'; } |crontab -");
        }
        return $output;
    }

        // remove the schedule run command to crontab
    protected function remove_cronjob($command){
        $output = [];
        if(is_string($command)&&!empty($command)&&$this->cronjob_exists($command)==TRUE){
            //remove crontab
            exec("crontab -r", $output);
        }
        return $output;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $base_path = base_path();
        $output = [];
        $failed = false;
        $command = "* * * * * cd $base_path && php artisan schedule:run >> /dev/null 2>&1";
        $action = $this->argument('action');
        switch ($action) {
            case "add_cron":
                $this->info("adding command to cronjob");
                $output = $this->append_cronjob($command);               
                break;
            case "del_cron":
                $this->info("stoping transactions tasks");
                $output = $this->remove_cronjob($command);
                var_dump($output);
                break;
            case "show":
                exec("crontab -l", $output);
                var_dump($output);
                break;
            default:
                $this->info('usage : php artisan crypto:cron add_cron/del_cron/show ');
                $quit = 0;
        }
    }        
}
