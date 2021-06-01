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
use Systruss\SchedTransactions\Services\Beneficary;
use Systruss\SchedTransactions\Services\Server;



const api_fee_url = "https://raw.githubusercontent.com/InfinitySoftwareLTD/common/main/fees/fee.json";
const nonce = 1;

class Transactions
{
	public $fee;
	public $nonce = 1;
	public $balance;
	public $rate;
	public $wallet_valid;
	public $buildSucceed;
	public $delegateAddress;
	public $address;
	public $amountToBeDistributed;
	public $delegatePassphrase;
	public $network;
	public $voters;
	public $peer_ip;
	public $peer_port;
	public $peers;
	public $transactions;
	public $api_delegates_url;
	public $publicKey;
	public $errMesg;

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
					$fee = $data->data->edge->multiPayment->min;
					break;
				case "infi" : 
					$fee = $data->data->infi->multiPayment->min;
					break;
				default:
					echo "\n network provided is not infi or edge \n";
			}
			// total fee = rounded(number of voters + 1  / 300) * minimum fee according network
			if ($totalVoters > 0) 
			{
				$totalVoters = $totalVoters +1; //add beneficary
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



	public function buildTransactions(Voters $voters, Delegate $delegate, Beneficary $beneficary)
	{	
		$transactions = [];
		

        $valid = $delegate->checkDelegateEligibility();
		if ($valid)
		{
			// delegate rank is between 1 and 25 and balance as required

            // get fee
            $totalFee = $this->getFee($delegate->network, $voters->totalVoters);
			if ($totalFee > $delegate->balance) {
				$this->buildSucceed = false;
				$this->errMesg = "buildTransactions : warning : Fee greater than delegate available balance";
				return $this;
			}
			$this->fee = $totalFee;

			// get Beneficary address and amount
			$beneficaryAddress = $beneficary->address;
			$tmp = ($delegate->balance - $totalFee) * $beneficary->rate; 
			$beneficaryAmount = $tmp / 100;
			$this->rate = $beneficary->rate;
			
						
            // calculate voters amount
			// to be distributed = balance - (total fee + beneficary)
			$amountToBeDistributed = $delegate->balance - ($totalFee + $beneficaryAmount);
			$votersList = $voters->calculatePortion($amountToBeDistributed);
			$this->balance = $delegate->balance;
			echo "\n amount to be distributed : $amountToBeDistributed";
			$this->amountToBeDistributed = $amountToBeDistributed;
			

			Network::set(new MainnetExt());
			$this->buildSucceed = false;
			// Generate transaction
			if ($votersList->eligibleVoters)
			{
				$generated = MultiPaymentBuilder::new();
				foreach ($votersList->eligibleVoters as $voter) {
					echo "\n ----------------- \n";
					echo "\n voter portion : $voter['portion']  \n";
					echo "\n delegate balance : $delegate->balance \n";
					$amount = ($voter['portion'] * $delegate->balance) / 100;
					echo "\n amount : $amount \n";
					echo "\n ----------------- \n";
					$generated = $generated->add($voter['address'], (int)$amount);
				}
				// add beneficary
				$generated = $generated->add($beneficaryAddress,floor($beneficaryAmount));
				$generated = $generated->withFee($totalFee);
				$generated = $generated->withNonce(nonce);
				$generated = $generated->sign($delegate->passphrase);
				$this->transactions = [ 'transactions' => [$generated->transaction->data] ];
                $this->peer_ip = $delegate->peer_ip;
                $this->peer_port = $delegate->peer_port;
				$this->buildSucceed = true;
			} else {
				// there is no voters
				$this->buildSucceed = false;	
			}
		} else {
			//invalid sender
			$this->buildSucceed = false;
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