<?php

namespace InfinitySoftwareLTD\Library_Dus\Commands;

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
use InfinitySoftwareLTD\Library_Dus\Services\Networks\MainnetExt;
use InfinitySoftwareLTD\Library_Dus\Models\Senders;
use InfinitySoftwareLTD\Library_Dus\Services\SchedTransaction;


// https://raw.githubusercontent.com/InfinitySoftwareLTD/common/main/fees/fee.json
// https://api.infinitysolutions.io/api/delegates/024844fa4b301ae6f9c514c963c18540630f1755dcca02ea9e91bae4b11d3dd1f1/voters


const FEE = 101000;
const MAIN_WALLET = "GL9RMRJ7RtANhuu66iq2ZGnP2J9yDWS3xe";

class ScheduleJob extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crypto:schedule_job';

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
        $disabled = 0;
		
		//get wallet address and network from senders table
		$delegate = new Delegate();
        $delegate->initFromDB();

		//init sender
		echo "\n initialising sender \n";
		$rep = $schedTransaction->initSenderFromDb();
		if (!$rep) {
			echo "\n error seting sender from DB \n";
			return 0;
		}
        echo "\n $schedTransaction->passphrase \n";

        //get peers
        echo "\n initialising peers \n";
		$rep = $schedTransaction->initPeers();
		if (!$rep) {
			echo "\n error seting peers \n";
			return 0;
		}


		// Check Sender Vailidity
		echo "\n checking sender validity \n";
		$valid = $schedTransaction->checkSenderValidity();
		if (!$valid) {
			echo "\n error sender in DB is not valid \n";
			return 0;
		}
        $this->info("\n sender is valid \n");

        //init voters
        echo "\n initialising voters \n";
		$rep = $schedTransaction->initVoters();
		if (!$rep) {
			echo "\n error while initializing voters \n";
			return 0;
		}
        $this->info("voters initialized successfully \n ");
        
        
        //init FEE
        echo "\n initialising FEE \n";
		$rep = $schedTransaction->initFee();
		if (!$rep) {
			echo "\n error while initializing FEE \n";
			return 0;
		}
        $this->info("FEE initialized successfully $schedTransaction->fee ");

        //build transactions
        echo "\n initialising transactions \n";
        $rep = $schedTransaction->buildTransaction();
        if (!$rep) {
            echo "\n error while building transactions \n";
            return 0;
        }
        $this->info("transaction initialized successfully \n");
        echo "\n ready to run the folowing transactions : \n";
        echo json_encode($schedTransaction->transactions);
        echo "\n";

        if (!$disabled) {
            //execute transactions
            echo "\n performing the transactions \n";
            $rep = $schedTransaction->sendTransaction();
            if (!$rep) {
                echo "\n error while building transactions \n";
                return 0;
            }
            $this->info("performing transactions");
            echo json_encode($schedTransaction->transactions );
        } 
	}

}
