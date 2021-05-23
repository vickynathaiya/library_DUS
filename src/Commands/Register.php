<?php

namespace Systruss\SchedTransactions\Commands;

use Illuminate\Console\Command;

use ArkEcosystem\Crypto\Configuration\Network;
use ArkEcosystem\Crypto\Identities\Address;
use Systruss\SchedTransactions\Services\Networks\MainnetExt;
use Illuminate\Database\QueryException;
use Systruss\SchedTransactions\Models\Senders;


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
				$network = 1;
				$quit=1;
    				break;
  			case "2":
    				echo "you selected Hedge\n";
				$network = 2;
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
	$crypto_util = new CryptoUtils;
	$valid = $crypto_util->checkWalletValidity($passphrase,$network);

	if ($valid) {
		// migrate senders table
		\Artisan::call('migrate', array('--path' => 'database/migrations', '--force' => true));

		//insert wallet into Senders Table
		$main_net = new MainnetExt;
		$wallet_address = Address::fromPassphrase($passphrase,$main_net);

		//check if wallet address exist in sender table
		$sender = Senders::all();
		if ($sender) {
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
				]);
				$this->info("wallet registered successfully");
			} catch (QueryException $e) {
				$this->info(" error : "); 
				var_dump($e->errorInfo);
			}
		}
	}
	}
}