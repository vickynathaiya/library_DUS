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
use Systruss\SchedTransactions\Models\Delegate;
use Systruss\SchedTransactions\Models\Transactions;


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
    protected $description = 'Register Delegate';

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

		//register delegate 
		$delgate = new Delegate();
		$success = $delegate->register($passphrase,$network);
		
		if ($success) 
		{
			$this->info("Delegate registered Successfuly");

			//initializing scheduler
			$transactions = new Transactions();
			$success = $transactions->initScheduler();
			if (!$success) {
				$this->info("error while initialising scheduler");
				return false;
			}
			return true;
		} else {
			$this->info("error while registering delegate");
			return false;
		}
	}
}