<?php

namespace Systruss\CryptoWallet\Commands;

use Illuminate\Console\Command;

use ArkEcosystem\Crypto\Configuration\Network;
use ArkEcosystem\Crypto\Identities\Address;
use Illuminate\Database\QueryException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use ArkEcosystem\Crypto\Transactions\Builder\TransferBuilder;
use ArkEcosystem\Crypto\Transactions\Builder\MultiPaymentBuilder;
use Systruss\CryptoWallet\Services\Networks\MainnetExt;
use Systruss\CryptoWallet\Services\Voters;
use Systruss\CryptoWallet\Services\Delegate;
use Systruss\CryptoWallet\Services\Transactions;
use Systruss\CryptoWallet\Services\SchedTransaction;


// https://raw.githubusercontent.com/InfinitySoftwareLTD/common/main/fees/fee.json
// https://api.infinitysolutions.io/api/delegates/024844fa4b301ae6f9c514c963c18540630f1755dcca02ea9e91bae4b11d3dd1f1/voters


class PerformTransactions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crypto:perform_transactions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'perform transactions every 24 hours';

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
        $disabled = 1;
		
		//Initialise Delegate
		$delegate = new Delegate();
        $success = $delegate->initFromDB();
        if (!$success) 
        {
            $this->info("Error initialising Delegate from DB ");
            return false;
        }

        //check if scheduler is active
        // if (!$delegate->sched_active) {
        //    $this->info("Scheduler is not yet active, enable it : php artisan crypto:admin start_sched");
        //    return false;
        // }

		// Check Delegate  Vailidity
        echo "\n checking delegate elegibility \n";
        $transactions = new Transactions();
        $success = $transactions->checkDelegateEligibility($delegate);
		if (!$success) {
			$this->info("delegate is not yet eligble trying after an hour");
			return false;
		}
        echo "\n delegate is eligible \n";

        //init voters
        $this->info("initialising voters");
		$voters = new voters();
        $success = $voters->initEligibleVoters($delegate->address);
		if (!$success) {
			echo "\n error while initializing Eligible voters \n";
			return false;
		}
        $this->info("voters initialized successfully \n ");
        echo "\n Elegible voters \n";
        var_dump($voters->eligibleVoters);

        //build transactions
        echo "\n initializing transactions \n";
        $transactions = new Transactions();
        $success = $transactions->buildTransactions();
        if (!$success) {
            echo "\n error while building transactions \n";
            return flase;
        }
        $this->info("transaction initialized successfully \n");
        echo "\n ready to run the folowing transactions : \n";
        echo json_encode($transacrions->transactions);
        echo "\n";

        if (!$disabled) {
            //perform transactions
            echo "\n performing the transactions \n";
            $success = $transactions->sendTransactions();
            if (!$success) {
                echo "\n error while sending transactions \n";
                return 0;
            }
            $this->info("transactions performed successefully");
            echo json_encode($transactions->transactions );
        } 
	}

}
