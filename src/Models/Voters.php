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
use Systruss\CryptoWallet\Models\Senders;
use Systruss\CryptoWallet\Services\Server;

const api_voters_url = "https://api.infinitysolutions.io/api/delegates/024844fa4b301ae6f9c514c963c18540630f1755dcca02ea9e91bae4b11d3dd1f1/voters";

const VoterMinBalance = 1000000;
const DelegateMinBalance = 1000000;
const minBalance = 1000000;
const MAIN_WALLET = "GL9RMRJ7RtANhuu66iq2ZGnP2J9yDWS3xe";
		

class Voters 
{
	public $eligibleVoters;

	public function initEligibleVoters($delegateAddress) 
	{
		$eligibleVoters = [];
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
					if ($delegateAddress != $voter->address && $voter->balance >= VoterMinBalance) 
					{
						$this->eligibleVoters['address'] = $voter->address;
						$this->eligibleVoters['balance'] = $voter->balance;
						$this->eligibleVoters['portion'] = 0;
						$this->eligibleVoters['amount'] = 0;
					}
				}
			}
		}
		return true;
	}
	
	public function calculatePortion($delegateAmount) 
	{
		//with eligibleVoters 
		//proportion (voter balance x 100) % sum of voters balance.
		
		$this->portionByVoter = [];
		//perform sum of eligible voters balance
		$totalVotersBalance = 0;
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