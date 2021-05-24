<?php

namespace Systruss\SchedTransactions\Commands;

use Illuminate\Console\Command;

use ArkEcosystem\Crypto\Configuration\Network;
use ArkEcosystem\Crypto\Identities\Address;
use Systruss\SchedTransactions\Services\Networks\MainnetExt;
use Systruss\SchedTransactions\Services\SchedTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Console\Scheduling\Schedule;
use Systruss\SchedTransactions\Models\Senders;

const failed = 0;
const succeed = 1;

class Register extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crypto:register';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Schedule Job for authorized users';

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
	//
	// user select netowrk (infi or hedge)
	//
	$quit=1;
	while (1 == 1) {
		$network = $this->ask('select network [ (1) infi or (2) Hedge]: ');
		echo "\n";
		switch ($network) {
  			case "1":
    			echo "you selected infi\n";
				$network = 'infi';
				$quit=1;
    				break;
  			case "2":
    			echo "you selected Hedge\n";
				$network = 'edge';
				$quit=1;
    				break;
  			case "q":
    			echo "you selected to quit\n";
				$quit=1;
    				break;
  			default:
    				echo "please select 1 for infi or 2 for Hedge or q to quit \n";
				$quit = 0;
			}

		if ($quit == 1) {
			break;
		}
	}
	
	//
	//network is select now provide passphrase
	//
	while (1 == 1) {
		$passphrase = $this->ask('please enter a passphrase : ');
		$this->info("you provided the following passphrase :  $passphrase ");
		$confirm = $this->confirm('Is it correct [y/n] or q to quit : ');
		if ($confirm) { break;}
	}


	//check wallet validity with passphrase and network
	$sched_tran = new SchedTransaction;
	$valid = $sched_tran->checkSender($passphrase,$network);

	if ($valid) {
		//insert wallet into Senders Table
		$main_net = new MainnetExt;
		$wallet_address = Address::fromPassphrase($passphrase,$main_net);

		//check if senders table exist
		if (!Schema::hasTable('senders')) {
			$this->info('table senders does not exist, run php artisan migrate');
			return;
		}
		//check if wallet address exist in sender table
		$sender = Senders::all();
		if (!$sender->isEmpty()) {
			//sender exist
			$this->info("There is already a sender registered!");
			return;
		} else {
			//create sender
			try {
				$sender = Senders::create([
					'address' => $wallet_address,
					'passphrase' => $passphrase,
					'network' => $network,
					'sched_active' => false,
				]);
				$registered = succeed;
				$this->info("wallet registered successfully");
			} catch (QueryException $e) {
				$this->info(" error : ");
				$registered = failed; 
				var_dump($e->errorInfo);
			}
		}

		if ($registered) {
			//schedule task
			$this->info('schdeduling crypto:schedule_job task hourly');
			$filePath = "/var/log/schedule_job.log";
			$schedule = app(Schedule::class);
			$sched_hourly = $schedule->command('crypto:schedule_job')->hourly();
			$sched_hourly->appendOutputTo($filePath);
			$sched_hourly->when(function () {
				//check if task active in sender table
				if (Schema::hasTable('senders')) {
					$sender = Senders::first();
					if ($sender) {
						//check is task scheduling active
						if ($sender->sched_active) {
							return true;
						}else {
							$this->info('scheduling disabled, to activate run : php artisan crypto:register --activate');
							return false;
						}
					} else {
						$this->info('No delegate registeres, to register a delegate run : php artisan crypto:register');
						return false;
					}
				} else {
					$this->info('No delegate table, did you forget to run : php artisan migrate');
					return false;
				}
			});
		}
	} else {
		$this->info("this delegate is not valid")
	}
	}
}