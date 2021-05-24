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

const FEE = 101000;
const MAIN_WALLET = "GL9RMRJ7RtANhuu66iq2ZGnP2J9yDWS3xe";
const failed = 0;
const succeed = 1;



class SchedTransaction
{
	public $fee;
	public $nonce;
	public $balance;
	public $wallet_valid;
	public $address;
	public $amount;
	public $passphrase;
	public $network;
	public $voters;
	public $peer_ip;
	public $peer_port;
	public $peers;
	public $transactions;


	public function checkSender($passphrase,$network)
	{
		//get the registered sender address,network and passphrase 
			
		$this->network = $network;
		$this->passphrase = $passphrase;

		$rep = $this->initPeers();
		if ($rep) {
			$rep = $this->checkSenderValidity();
			if ($rep) {
				return true;
			}else{
				return false;
			}
		}else{
			return false;
		}
	}


	public function initSenderFromDb()
	{
		//get the registered sender address,network and passphrase 
		$response = [];
		if (!Schema::hasTable('senders')) {
			echo "\n table senders does not exist, did you run php artisan migrate ? \n";
			return;
		}
		$sender = Senders::first();
		if ($sender) {
			//sender exist
			echo "\n sender exist \n";
			$this->network = $sender->network;
			$this->passphrase = $sender->passphrase;
			$this->address = $sender->address;
		} else {
			//no senders
			echo "\n there is no sender defined, did you run php artisan crypto:register ? \n";
			return failed;

		}
		return succeed;
	}


	public function initPeers()
	{
		//return the list of peer corresponding to the network
		$main_net = MainnetExt::new();
		$api_url = $main_net->peer($this->network);

		$client = new Client();
		$res = $client->get($api_url);

		$data =  json_decode($res->getBody()->getContents());  
	
		// total number of peers
		$totalCount = $data->meta->totalCount;
		$this->peers = array('data' => $data->data, 'count' => $totalCount);
		return succeed;
	}

	public function checkSenderValidity()
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
		if (!$this->peers) {
			echo "\n checkSenderValidity : no peers found \n";
			return failed;
		}

		$peer_list = $this->peers['data'];
		$count = $this->peers['count'];
		
		// Generate wallet address from passphrase
		$main_net = MainnetExt::new();
		$wallet_address = Address::fromPassphrase($this->passphrase,$main_net);

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
		
		echo "\n wallt valid : $valid \n";
		$this->wallet_valid = $valid;
		$this->peer_ip = $ip_add;
		$this->peer_port = $port;
 		$this->nonce = $nonce;
		$this->balance = $balance;
		
		return $valid;
	}



	public function initFee()
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
					$this->fee = $data->data->edge->transfer->min;
					break;
				case "infi" : 
					$this->fee = $data->data->infi->transfer->min;
					break;
				default:
					return failed;
			}
		}	
		return succeed;
	}

	public function initVoters() 
	{
		$voters = [];
		// get fees from api
		$client = new Client();
		$res = $client->get(api_voters_url);
		if ($data = $res->getBody()->getContents()) 
		{
			$data = json_decode($data);
			$totalVoters = $data->meta->totalCount;
			if ($totalVoters > 0) {
				$list_voters = $data->data;
				foreach ($list_voters as $voter) {
					$this->voters[] = $voter->address;
				}
			}
		}
		return succeed;
	}

	public function buildTransaction()
	{	
		$transactions = [];

		if ($this->wallet_valid)
		{
			// check sender balance
			if ($this->balance <= 0) 
			{
				info("balance null");
				return;
			}

			// calculate amount
			$amount = 1000;
			Network::set(new MainnetExt());

			// Generate transaction
			if ($this->voters)
			{
				$generated = MultiPaymentBuilder::new();
				foreach ($this->voters as $voter_address) {
					var_dump($voter_address);
					$generated = $generated->add($voter_address, $amount);
				}
				$generated = $generated->withFee($this->fee);
				$generated = $generated->withNonce($this->nonce);
				$generated = $generated->sign($this->passphrase);
				$this->transactions = [ 'transactions' => [$generated->transaction->data] ];
			} else {
				// there is no voters
				return failed;	
			}
		} else {
			//invalid sender
			return failed;
		}
		return succeed;
	}

	public function sendTransaction()
	{
	
		if ($this->transactions) 
		{
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