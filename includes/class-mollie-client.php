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

	/**
	 * Run a Mollie API call with retry on HTTP 429 (rate limited).
	 *
	 * Mollie is introducing API rate limits. The v3 SDK's built-in retry only
	 * covers curl/network errors (RetryableNetworkRequestException); a 429 is
	 * surfaced as a TooManyRequestsException and re-thrown immediately without
	 * retry. This helper closes that gap: it retries the callable with
	 * exponential backoff when — and only when — Mollie signals rate limiting.
	 * Every other ApiException (validation, auth, not-found) propagates on the
	 * first attempt, unchanged.
	 *
	 * Usage:
	 *   $link = MollieClient::with_retry(
	 *       fn() => $mollie->paymentLinks->create( $payload )
	 *   );
	 *
	 * @param callable $call        The Mollie API call to execute; its return value is passed through.
	 * @param int      $max_retries Number of retries after the first attempt (default 4).
	 * @return mixed The value returned by $call.
	 * @throws \Mollie\Api\Exceptions\ApiException The last 429 (after retries are exhausted) or any other API error.
	 */
	public static function with_retry( callable $call, int $max_retries = 4 ) {
		$attempt = 0;

		while ( true ) {
			try {
				return $call();
			} catch ( \Mollie\Api\Exceptions\TooManyRequestsException $e ) {
				if ( $attempt >= $max_retries ) {
					throw $e;
				}

				// Exponential backoff: 0.25s, 0.5s, 1s, 2s.
				$delay_ms = ( 1 << $attempt ) * 250;
				usleep( $delay_ms * 1000 );
				++$attempt;
			}
		}
	}
}
