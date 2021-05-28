<?php

namespace Systruss\SchedTransactions\Services;

use Illuminate\Console\Command;

use ArkEcosystem\Crypto\Configuration\Network;
use ArkEcosystem\Crypto\Identities\Address;
use Systruss\SchedTransactions\Services\Networks\MainnetExt;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Illuminate\Console\Scheduling\Schedule;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use ArkEcosystem\Crypto\Transactions\Builder\TransferBuilder;
use ArkEcosystem\Crypto\Transactions\Builder\MultiPaymentBuilder;
use Systruss\SchedTransactions\Services\Voters;
use Systruss\SchedTransactions\Services\Delegate;
use Systruss\SchedTransactions\Services\Benificiary;
use Systruss\SchedTransactions\Services\Server;


const api_fee_url = "https://raw.githubusercontent.com/InfinitySoftwareLTD/common/main/fees/fee.json";
const api_delegates_url ="https://api.hedge.infinitysolutions.io/api/delegates";
const MinDelegateBalance = 100000;
const MinDelegateRank = 1;
const MaxDelegateRank = 25;
const nonce = 1;

class Transactions
{
	public $fee;
	public $nonce = 1;
	public $balance;
	public $wallet_valid;
	public $delegateAddress;
	public $address;
	public $amount;
	public $delegatePassphrase;
	public $network;
	public $voters;
	public $peer_ip;
	public $peer_port;
	public $peers;
	public $transactions;


    public function checkDelegateEligibility(Delegate $delegate) 
	{
		$found = false;
		// check if delegate balance is grater than the minimum required
		echo "\n delegate balance : $delegate->balance \n";
		if ($delegate->balance < MinDelegateBalance) {
			echo "\n insufficient balance \n";
			return false;
		}
		// get list of delegate
		$client = new Client();
		$res = $client->get(api_delegates_url);
		if ($data = $res->getBody()->getContents()) 
		{
			$data = json_decode($data);
			$totalDelegates = $data->meta->totalCount;
			if ($totalDelegates > 0) {
				$listDelegates = $data->data;
				foreach ($listDelegates as $delegate_elem) {
					if ($delegate_elem->address == $delegate->address) {
						$rank = (int)$delegate_elem->rank;
						$found = true;
						break;
					}
				}
				if ($found) {
					$rank = 24;
					echo "\n delegate rank : $rank \n";					
					if ($rank >= MinDelegateRank && $rank <= MaxDelegateRank){
						return true;
					}else{
						return false;
					}
				} else {
					echo "\n delegate not found !!! \n";
					return false;
				}
			} else {
				echo "\n  number of delegate 0 !!! \n";
				return false;
			} 			
		} else {
				echo "\n no data returned from the api delagate url !!! \n";
				return false;
			}
	}


	public function getFee($network,$totalVoters)
	{	
		$fee = '';
		$totalFee = 0;
		// get fees from api
		$client = new Client();
		$res = $client->get(api_fee_url);
		if ($data = $res->getBody()->getContents()) 
		{
			$data = json_decode($data);
			switch ($network) {
				case "edge" : 
					$fee = $data->data->edge->transfer->min;
					break;
				case "infi" : 
					$fee = $data->data->infi->transfer->min;
					break;
				default:
					echo "\n network provided is not infi or edge \n";
			}
			// total fee = rounded(number of voters + 1  / 300) * minimum fee according network
			if ($totalVoters > 0) 
			{
				$totalVoters = $totalVoters +1; //add benificiary
				$FeeQuotient =  floor($totalVoters/300)+1;
				$totalFee = $FeeQuotient * $fee;
			} else 
			{
				echo "\n total voters is 0 !!!\n";
			}
		}	
		return $totalFee;
	}

    public function initScheduler() 
    {
            //schedule task
            echo "\n schdeduling crypto:perform_transactions task hourly \n";
            $logFile = storage_path() . "/logs/schedule_job.log";
			echo "\n $logFile \n";
            $schedule = app(Schedule::class);
            $schedule->command('crypto:perform_transactions')->hourly()->appendOutputTo($logFile);
			return 1;
    }



	public function buildTransactions(Voters $voters, Delegate $delegate, Benificiary $benificiary)
	{	
		$transactions = [];
        $valid = $this->checkDelegateEligibility($delegate);
		if ($valid)
		{
			// delegate rank is between 1 and 25 and balance as required

            // get fee
            $totalFee = $this->getFee($delegate->network, $voters->totalVoters);

			// get Benificiary address and amount
			$benificiaryAddress = $benificiary->address;
			$tmp = ($delegate->balance - $totalFee) * $benificiary->rate; 
			$benificiaryAmount = $tmp / 100; 
						
            // calculate voters amount
			// to be distributed = balance - (total fee + benificiary)
			$amountToBeDistributed = $delegate->balance - ($totalFee + $benificiaryAmount);
			$votersList = $voters->calculatePortion($amountToBeDistributed);

			Network::set(new MainnetExt());

			// Generate transaction
			if ($votersList->eligibleVoters)
			{
				$generated = MultiPaymentBuilder::new();
				foreach ($votersList->eligibleVoters as $voter) {
					$amount = ($voter['portion'] * $delegate->balance) / 100;
					$generated = $generated->add($voter['address'], (int)$amount);
				}
				// add benificiary
				$generated = $generated->add($benificiaryAddress,floor($benificiaryAmount));
				$generated = $generated->withFee($totalFee);
				$generated = $generated->withNonce(nonce);
				$generated = $generated->sign($delegate->passphrase);
				$this->transactions = [ 'transactions' => [$generated->transaction->data] ];
                $this->peer_ip = $delegate->peer_ip;
                $this->peer_port = $delegate->peer_port;
			} else {
				// there is no voters
				return false;	
			}
		} else {
			//invalid sender
			return false;
		}
		return $this;
	}

	public function sendTransactions()
	{
		if ($this->transactions) 
		{
            $peer_ip = $delegate->peer_ip;
            $peer_port = $delegate->peer_port;
			$response = [];
			$api_url = "http://$peer_ip:$peer_port/api".'/transactions';
		
			try {
				$req = $client->post($api_url,['json'=> $transactions]);
				$data = $req->getBody()->getContents();
				if ($data)
				{
					$data = json_decode($data);
					//treating data errors
					if (isset($data->errors))
					{
						foreach ($data->errors as $error) {
							$response['http_data'][] = is_object($error) ? $error : $error[0];
						}
						echo "\n(Failed) Return Funds to Main Wallet";
						echo "\n(Failed) to connect to the node server.";
						$this->transaction_result = json_encode($response);
						return false;
					}
					echo "(success) Return Funds to Main Wallet";
					echo "Successfully returned the funds to the main wallet";
					return true;
				}
			} catch (\Exception $e) {
				echo "\n (Failed) Return Funds to Main Wallet. Unable to connect to the node. \n";
				//echo "\njson_encode($e->getMessage() . $e->getLine() . $e->getFile())\n";
				return false;
			}
		} else {
			echo "\n transactions are not set \n";
			return false;
		}
	}	
}