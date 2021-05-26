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
use Systruss\CryptoWallet\Models\Delegate;
use Systruss\CryptoWallet\Services\Server;

const api_voters_url = "https://api.infinitysolutions.io/api/delegates/024844fa4b301ae6f9c514c963c18540630f1755dcca02ea9e91bae4b11d3dd1f1/voters";

const VoterMinBalance = 100000;
const DelegateMinBalance = 100000;
const minBalance = 100000;
const MAIN_WALLET = "GL9RMRJ7RtANhuu66iq2ZGnP2J9yDWS3xe";
		

class Voters 
{
	public $eligibleVoters;
	public $totalVoters;

	public function initEligibleVoters($delegateAddress) 
	{
		$eligibleVoters = [];
		$this->totalVoters = 0;
		// get fees from api
		$client = new Client();
		$res = $client->get(api_voters_url);
		if ($data = $res->getBody()->getContents()) 
		{
			$data = json_decode($data);
			$this->totalVoters = $data->meta->totalCount;
			if ($this->totalVoters > 0) {
				$list_voters = $data->data;
				foreach ($list_voters as $voter) {
					$voter_balance = (int)$voter->balance;
					if ($delegateAddress != $voter->address && $voter_balance >= VoterMinBalance) 
					{
						$this->eligibleVoters[] = (object)array(
						'address' => $voter->address,
						'balance' => $voter->balance,
						'portion' => 0,
						'amount' => 0,
						);
						echo "\n $voter->address ----   $voter_balance \n";
					}
				}
			}
		}
		var_dump($this->eligibleVoters);
		return $this;
	}
	
	public function calculatePortion($delegateAmount) 
	{
		//with eligibleVoters 
		//proportion (voter balance x 100) % sum of voters balance.
		
		$this->portionByVoter = [];
		//perform sum of eligible voters balance
		$totalVotersBalance = 0;
		var_dump($this->eligibleVoters);
		foreach ($this->eligibleVoters as $voter) {
			$totalVotersBalance = $totalVotersBalance + $voter['balance'];
		}

		//perform portion for each voter
		foreach ($this->eligibleVoters as $voter) {
			$portion = ($voter->balance * 100) / $totalVotersBalance;
			$voter->portion = $portion;
		}
		return true;
	}
}