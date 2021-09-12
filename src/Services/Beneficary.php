<?php

namespace InfinitySoftwareLTD\Library_Dus\Services;

use Illuminate\Console\Command;

use ArkEcosystem\Crypto\Configuration\Network;
use ArkEcosystem\Crypto\Identities\Address;
use InfinitySoftwareLTD\Library_Dus\Services\Networks\MainnetExt;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7;
use GuzzleHttp\Exception\RequestException;
use ArkEcosystem\Crypto\Transactions\Builder\TransferBuilder;
use ArkEcosystem\Crypto\Transactions\Builder\MultiPaymentBuilder;
use InfinitySoftwareLTD\Library_Dus\Services\Delegate;
use InfinitySoftwareLTD\Library_Dus\Services\Server;

# const api_settings_delegate_url = "https://raw.githubusercontent.com/InfinitySoftwareLTD/common/main/test_api/settings_delegate";
const api_settings_delegate_url = "https://smartmarket.infinitysolutions.io/api/delegates";
		

class Beneficary 
{
	public $address;
	public $requiredMinimumBalance; //required minimum balance for voters
	public $maintainMinimumBalance;
	public $multiPaymentLimit;
	public $rate;
	public $amount;
	public $result;
	
	public function initBeneficary(Delegate $delegate) 
	{
		// get delegate address and network
		$delegate_address = $delegate->address;
		$delegate_network = $delegate->network;
		$post_params = array('delegate_address'=>$delegate_address, 'network'=>$delegate_network);

		// prepare api request to settings delegate
		$client = new Client();
		try {
			$req = $client->post(api_settings_delegate_url,['json'=> $post_params]);
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
					echo "\n (Benificiary) Failed to get delegate settings api \n";
					$this->result = json_encode($response);
					return false;
				}
				$this->address = $data->beneficiaryAddress;
				$this->rate = $data->beneficiaryRate;
				$this->requiredMinimumBalance = $data->requiredMinimumBalance;
				$this->maintainMinimumBalance = $data->maintainMinimumBalance;
				$this->multiPaymentLimit = $data->multiPaymentLimit;
				return true;
			}
		} catch (RequestException $e) {
			echo "\n (Benificiary) Failed to connect to delegate settings api . \n";
			$response = $e->getResponse();
			$this->result = $response->getBody()->getContents();
			return false;
		}
	}
}