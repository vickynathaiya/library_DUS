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
use Systruss\SchedTransactions\Services\Delegate;
use Systruss\SchedTransactions\Services\Server;

const api_settings_delegate_url = "https://raw.githubusercontent.com/InfinitySoftwareLTD/common/main/test_api/settings_delegate";

const VoterMinBalance = 100000;
const DelegateMinBalance = 100000;
const minBalance = 100000;
const DelegateAddress = "GeocWzPKN1kLWN4xCr4KWr75EBnkRS4ds1";
		

class Beneficary 
{
	public $address;
	public $requiredMinimumBalance; //required minimum balance for voters
	public $maintainMinimumBalance;
	public $rate;
	public $amount;
	
	public function initBeneficary(Delegate $delegate) 
	{
		$found = false;
		// get delegate address and network
		$delegate_address = $delegate->address;
		$delegate_network = $delegate->network;

		// prepare api request to settings delegate
		$client = new Client();
		$res = $client->get(api_settings_delegate_url);
		if ($data = $res->getBody()->getContents()) 
		{
			$data = json_decode($data);
			switch ($delegate_network) {
				case "infi":
					$list_infi = $data->data->infi;
					foreach ($list_infi as $delegate_address => $settings) {
						if ($delegate_address == DelegateAddress) {
							$this->address = $settings->beneficaryAddress;
							$this->rate = $settings->beneficaryRate;
							$this->requiredMinimumBalance = $settings->requiredMinimumBalance;
							$this->maintainMinimumBalance = $settings->maintainMinimumBalance;
							$found = true;
							break;
						}
					}
					break;
				case "edge":
					$list_edge = $data->data->edge;

					foreach ($list_edge as $delegate_address => $settings) {
						if ($delegate_address == DelegateAddress) {
							$this->address = $settings->beneficaryAddress;
							$this->rate = $settings->beneficaryRate;
							$this->requiredMinimumBalance = $settings->requiredMinimumBalance;
							$found = true;
							break;
						}
					}
					break;
				default:
					echo "\n the delegate network is not correct \n";
					$found = false;
			} 
		}

		return $found;
	}
}