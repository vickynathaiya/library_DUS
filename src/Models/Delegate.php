<?php

namespace Systruss\SchedTransactions\Services;

use Illuminate\Console\Command;

use ArkEcosystem\Crypto\Configuration\Network;
use ArkEcosystem\Crypto\Identities\Address;
use Systruss\SchedTransactions\Services\Networks\MainnetExt;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use ArkEcosystem\Crypto\Transactions\Builder\TransferBuilder;
use ArkEcosystem\Crypto\Transactions\Builder\MultiPaymentBuilder;
use Systruss\SchedTransactions\Models\Senders;
use Systruss\SchedTransactions\Services\Server;


const api_fee_url = "https://raw.githubusercontent.com/InfinitySoftwareLTD/common/main/fees/fee.json";
const api_voters_url = "https://api.infinitysolutions.io/api/delegates/024844fa4b301ae6f9c514c963c18540630f1755dcca02ea9e91bae4b11d3dd1f1/voters";
const api_delegates_url ="https://api.hedge.infinitysolutions.io/api/delegates";

const FEE = 101000;
const VoterMinBalance = 1000000;
const DelegateMinBalance = 1000000;
const minBalance = 1000000;
const MAIN_WALLET = "GL9RMRJ7RtANhuu66iq2ZGnP2J9yDWS3xe";
const failed = 0;
const succeed = 1;

class SchedTransaction
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


	public function register($passphrase,$network)
	{
		//get the registered sender address,network and passphrase 
			
		$this->network = $network;
		$this->passphrase = $delegatePassphrase;

		$peers = $this->getPeers($network);
		if ($peers) {
			$valid = $this->checkDelegateAuth($peers,$passphrase);
			if ($valid) {
				// insert delegate into DB table
				$main_net = new MainnetExt;
				$address = Address::fromPassphrase($passphrase,$main_net);
		
				//check if senders table exist
				if (!Schema::hasTable('delegate')) {
					$this->info('table delegate does not exist, run php artisan migrate');
					return;
				}
				//check if there is a delegate entry in delegate table
				$delegate = Delegate::all();
				if (!$delegate->isEmpty()) {
					//delegate exist
					$this->info("There is already a delegate registered!");
					return;
				} else {
					//create delegate
					try {
						$delegate = Delegate::create([
							'address' => $address,
							'passphrase' => $passphrase,
							'network' => $network,
							'sched_active' => false,
						]);
						$registered = succeed;
						$this->info("Delegate registered successfully");
					} catch (QueryException $e) {
						$this->info(" error : ");
						$registered = failed; 
						var_dump($e->errorInfo);
					}
				}
				return true;
			}else{
				return false;
			}
		}else{
			return false;
		}
	}


	public function initFromDb()
	{
		//get the registered sender address,network and passphrase 
		$response = [];
		if (!Schema::hasTable('delegate')) {
			echo "\n table delegate does not exist, did you run php artisan migrate ? \n";
			return;
		}
		$delegate = Delegate::first();
		if ($delegate) {
			//sender exist
			echo "\n delegate exist \n";
			$this->network = $delegate->network;
			$this->passphrase = $delegate->passphrase;
			$this->address = $delegate->address;
		} else {
			//no delegate
			echo "\n there is no delegate defined, did you run php artisan crypto:register ? \n";
			return failed;

		}
		return true;
	}


	public function getPeers($network)
	{
		//return the list of peer corresponding to the network
		$main_net = MainnetExt::new();
		$api_url = $main_net->peer($network);

		$client = new Client();
		$res = $client->get($api_url);

		$data =  json_decode($res->getBody()->getContents());  
	
		// total number of peers
		$totalCount = $data->meta->totalCount;
		$peers = array('data' => $data->data, 'count' => $totalCount);
		return $peers;
	}

	public function checkDelegateAuth($peers,$passphrase)
	{
		// check if walet valid
		//
		// go through the peers and check the wallet
		//

		$valid = 0;
		$isDelegate = 0;
		$isResigned = 1;
		$nonce = 0;
		$balance = 0;

		// 
		// Check if peers are set
		// 
		if (!$peers) {
			echo "\n checkDelegateAuth : no peers found \n";
			return failed;
		}

		$peer_list = $peers['data'];
		$count = $peers['count'];
		
		// Generate wallet address from passphrase
		$main_net = MainnetExt::new();
		$wallet_address = Address::fromPassphrase($passphrase,$main_net);

		foreach ($peer_list as $peer) 
		{
			//build api url
			$ip_add = $peer->ip;
			$port = $peer->ports->{"@arkecosystem/core-wallet-api"};
			$api_url = "http://$ip_add:$port/api".'/wallets/'.$wallet_address;
							
			//get isDelegate, isResigned, nonce and balance
			try {
				$client = new Client();
				$res = $client->get($api_url);
				if ($data = $res->getBody()->getContents()) 
				{
					$data = json_decode($data);                
					$isDelegate = $data->data->isDelegate; 
					$isResigned = $data->data->isResigned; 
					$nonce = $data->data->nonce + 1; 
					$balance = $data->data->balance; 
					if ($isDelegate == 1) {
						if ($isResigned == 1) {
							break;
							$valid = false;
						}
					} else {
						$valid = false;
						break;

					}
					// here isDelegate is True and isResigned is False
					$valid = true;
					break;
				}
			} catch (ClientException $e) {
				if ($e->hasResponse()) 
				{
					$status_code =  json_decode($e->getResponse()->getBody())->statusCode;
					if ($status_code == "404") 
					{
						$valid = false;
						break;
					}
    			}
			}
		}
		
		$this->wallet_valid = $valid;
		$this->peer_ip = $ip_add;
		$this->peer_port = $port;
 		$this->nonce = $nonce;
		$this->balance = $balance;
		
		return $valid;
	}



	
	
}