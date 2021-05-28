<?php

namespace Systruss\SchedTransactions\Commands;

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
use Systruss\SchedTransactions\Services\Networks\MainnetExt;
use Systruss\SchedTransactions\Services\Voters;
use Systruss\SchedTransactions\Services\Delegate;
use Systruss\SchedTransactions\Services\Benificiary;
use Systruss\SchedTransactions\Services\Transactions;
use Systruss\SchedTransactions\Services\SchedTransaction;
use Systruss\SchedTransactions\Models\CryptoLog;


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
        
		
        $this->info("starting a new transaction");

		//Initialise Delegate
		$delegate = new Delegate();
        $success = $delegate->initFromDB();
        if (!$success) 
        {
            $this->info("Error initialising Delegate from DB ");
            return false;
        }

        // check if scheduler is active
        if (!$delegate->sched_active) {
            echo "\n Scheduler is not active \n";
            return;
        }

        // scheduler active , check counter last transactions
        $latest_transactions = CryptoLog::orderBy('id','DESC')->first();
        if ($latest_transactions) {
            if (($latest_transactions->succeed) && ($latest_transactions->hourCount < 24)) {
                $latest_transactions->hourCount = $latest_transactions->hourCount + 1;
                $latest_transactions->save();
                $this->info("Next Transactions in $latest_transactions->hourCount hours");
                return;
            }
        }
 
        //check delegate validity
        $valid = $delegate->checkDelegateValidity();

		// Check Delegate  Eligibility
        echo "\n checking delegate elegibility \n";
        $transactions = new Transactions();
        $success = $transactions->checkDelegateEligibility($delegate);
		if (!$success) {
			$this->info("delegate is not yet eligble trying after an hour");
			return false;
		}
        echo "\n delegate is eligible \n";

        // get benificiary and amount = (delegate balance - totalFee) * 20%
        $benificiary = new Benificiary();
        $success = $benificiary->initBenificiary($delegate);
        if (!$success) {
            $this->info("an issue happened with the benificiary");
			return false; 
        }
        $requiredMinimumBalance = $benificiary->requiredMinimumBalance;


        //init voters
        $this->info("initialising voters");
		$voters = new voters();
        $voters = $voters->initEligibleVoters($delegate->address,$requiredMinimumBalance);
		if (!($voters->totalVoters > 0)) {
			echo "\n error while initializing Eligible voters \n";
			return false;
		}
        $this->info("voters initialized successfully \n ");
        echo "\n Elegible voters \n";

        //build transactions
        echo "\n initializing transactions \n";
        $transactions = new Transactions();
        $transactions = $transactions->buildTransactions($voters,$delegate,$benificiary);
        if (!$transactions->buildSucceed) {
            echo "\n error while building transactions \n";
            return false;
        }
        //log transaction
        $trans_id = json_decode($transactions->transactions);
        $cryptoLog = new CryptoLog();
        $cryptoLog->rate = $benificiary->rate;
        $cryptoLog->delegate_balance = $delegate->balance;
        $cryptoLog->fee = $transactions->fee;
        $cryptoLog->amount = $transactions->amountToBeDistributed;
        $cryptoLog->totalVoters = $voters->totalVoters;
        $cryptoLog->transactions = $trans->id;
        $cryptoLog->hourCount = 0;
        $cryptoLog->succeed = false;

        $this->info("transaction initialized successfully");
        echo "\n ready to run the folowing transactions : \n";
        echo json_encode($transactions->transactions, JSON_PRETTY_PRINT);
        echo "\n";

        //for simulation transactions status
        $succeed = 1;
        if ($succeed) {
            $cryptoLog->succeed = true;
            $cryptoLog->save();
        }

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