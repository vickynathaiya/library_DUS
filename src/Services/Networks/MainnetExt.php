<?php

declare(strict_types=1);

/*
 * This file is part of Ark PHP Crypto.
 *
 * (c) Ark Ecosystem <info@ark.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace InfinitySoftwareLTD\Library_Dus\Services\Networks;

use ArkEcosystem\Crypto\Networks\Mainnet;

/**
 * This is the mainnet network class.
 *
 * @author Brian Faust <brian@ark.io>
 */
class MainnetExt extends Mainnet
{
    /**
     * {@inheritdoc}
     *
     * @see Network::$base58PrefixMap
     */
    protected $base58PrefixMap = [
        self::BASE58_ADDRESS_P2PKH => '26',
        self::BASE58_ADDRESS_P2SH  => '00',
        self::BASE58_WIF           => 'aa',
    ];


    /**
     * {@inheritdoc}
     */
    public function pubKeyHash(): int
    {
        return 38;
    }


    /**
     * @return peer url
     */
	public function peer($network): string
	{
		if ($network == "infi") {
			return 'https://api.infinitysolutions.io/api/peers';
		}
			return 'https://api.hedge.infinitysolutions.io/api/peers';
	}
}
