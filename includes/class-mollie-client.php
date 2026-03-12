<?php
/**
 * Mollie Client Wrapper
 *
 * Initialises the Mollie PHP SDK with the stored API key, providing
 * a configured MollieApiClient to MolliePayment and MollieWebhook.
 *
 * @package Rondo\Finance
 */

namespace Rondo\Finance;

use Mollie\Api\MollieApiClient;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper around MollieApiClient that reads the API key from FinanceConfig.
 *
 * Not a singleton — each instantiation reads a fresh key from the options store.
 * Instantiate only when you need to make a Mollie API call.
 */
class MollieClient {

	/**
	 * Configured Mollie API client instance.
	 *
	 * @var MollieApiClient
	 */
	private MollieApiClient $client;

	/**
	 * Constructor
	 *
	 * Initialises a configured MollieApiClient.
	 *
	 * @param string $api_key Mollie API key for the target account.
	 * @throws \Mollie\Api\Exceptions\ApiException If the API key is invalid or rejected by Mollie.
	 */
	public function __construct( string $api_key ) {
		$this->client = new MollieApiClient();
		$this->client->setApiKey( $api_key );
	}

	/**
	 * Get the configured MollieApiClient instance.
	 *
	 * @return MollieApiClient Configured client ready for API calls.
	 */
	public function get(): MollieApiClient {
		return $this->client;
	}
}
