<?php

namespace Systruss\CryptoWallet\Services;

use Illuminate\Console\Command;

use ArkEcosystem\Crypto\Configuration\Network;
use ArkEcosystem\Crypto\Identities\Address;
use Systruss\CryptoWallet\Services\Networks\MainnetExt;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use ArkEcosystem\Crypto\Transactions\Builder\TransferBuilder;
use ArkEcosystem\Crypto\Transactions\Builder\MultiPaymentBuilder;
use Systruss\CryptoWallet\Services\Voters;
use Systruss\CryptoWallet\Services\Delegate;
use Systruss\CryptoWallet\Services\Server;


const api_fee_url = "https://raw.githubusercontent.com/InfinitySoftwareLTD/common/main/fees/fee.json";
const api_delegates_url ="https://api.hedge.infinitysolutions.io/api/delegates";

const failed = 0;
const succeed = 1;

class Transactions
{
	public $fee;
	public $nonce;
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
		if ($delegate->balance < min_balance) {
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
						$rank = $delegate->rank;
						$found = true;
						break;
					}
				}
				if ($found) {
					if ($rank >= min_rank && $rank <= max_rank){
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


	public function getFee()
	{	
		$fee = '';
		// get fees from api
		$client = new Client();
		$res = $client->get(api_fee_url);
		if ($data = $res->getBody()->getContents()) 
		{
			$data = json_decode($data);
			switch ($this->network) {
				case "edge" : 
					$fee = $data->data->edge->transfer->min;
					break;
				case "infi" : 
					$fee = $data->data->infi->transfer->min;
					break;
				default:
					echo "\n network provide is not infi or edge \n";
			}
		}	
		return fee;
	}

    public function initScheduler() 
    {
            //schedule task
            $this->info('schdeduling crypto:perform_transactions task hourly');
            $filePath = "/var/log/schedule_job.log";
            $schedule = app(Schedule::class);
            $sched_hourly = $schedule->command('crypto:perform_transactions')->hourly()->appendOutputTo($filePath);
    }



	public function buildTransactions(Voters $voters, Delegate $delegate)
	{	
		$transactions = [];

        $valid = $this->checkDelegateEligibility($delegate);
		if ($valid)
		{
			// delegate rank is between 1 and 25 and balance as required

            // get fee
            $fee = (int)getFee();
			
            // calculate voters amount
            $voter_list = $voters->calculatePortion($delegate->balance);
			
			
			Network::set(new MainnetExt());

			// Generate transaction
			if ($voter_list)
			{
				$generated = MultiPaymentBuilder::new();
				foreach ($voter_list as $voter) {
					$generated = $generated->add($voter->address, $voter->amount);
				}
				$generated = $generated->withFee($this->fee);
				$generated = $generated->withNonce($this->nonce);
				$generated = $generated->sign($this->delegatePassphrase);
				$this->transactions = [ 'transactions' => [$generated->transaction->data] ];
                $this->peer_ip = $delegate->peer_ip;
                $this->peer_port = $delegate->peer_port;
			} else {
				// there is no voters
				return failed;	
			}
		} else {
			//invalid sender
			return failed;
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
					echo "\n**********************\n";
					var_dump($data);
					echo "\n**********************\n";
					//treating data errors
					if (isset($data->errors))
					{
						foreach ($data->errors as $error) {
							$response['http_data'][] = is_object($error) ? $error : $error[0];
						}
						echo "\n(Failed) Return Funds to Main Wallet";
						echo "\n(Failed) to connect to the node server.";
						$this->transaction_result = json_encode($response);
						return failed;
					}
					echo "(success) Return Funds to Main Wallet";
					echo "Successfully returned the funds to the main wallet";
					return succeed;
				}
			} catch (\Exception $e) {
				info("(Failed) Return Funds to Main Wallet. Unable to connect to the node.");
				//echo "\njson_encode($e->getMessage() . $e->getLine() . $e->getFile())\n";
				return failed;
			}
		} else {
			echo "\n transactions are not set \n";
			return failed;
		}
	}	
}