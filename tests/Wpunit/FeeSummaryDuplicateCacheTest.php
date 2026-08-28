<?php

namespace Tests\Wpunit;

use Rondo\Fees\FeeServices;
use Rondo\REST\Fees;
use Tests\Support\RondoTestCase;

/**
 * Fee summaries count people, even if legacy data contains duplicate cache rows.
 */
class FeeSummaryDuplicateCacheTest extends RondoTestCase {

	private const SEASON = '2026-2027';

	public function test_duplicate_fee_cache_rows_count_as_one_member(): void {
		$person_id = $this->createPerson();
		$fee_data  = [
			'category'               => 'senior',
			'base_fee'               => 250,
			'family_discount_amount' => 0,
			'final_fee'              => 250,
		];

		FeeServices::fee_cache()->save_fee_cache( $person_id, $fee_data, self::SEASON );
		$cache_key    = FeeServices::fee_cache()->get_fee_cache_meta_key( self::SEASON );
		$cached_value = get_post_meta( $person_id, $cache_key, true );
		add_post_meta( $person_id, $cache_key, $cached_value );

		$request = new \WP_REST_Request( 'GET' );
		$request->set_param( 'season', self::SEASON );
		$request->set_param( 'forecast', false );
		$summary = ( new Fees() )->get_fee_summary( $request )->get_data();

		$this->assertSame( 1, $summary['total'] );
		$this->assertSame( 1, $summary['aggregates']['senior']['count'] );
	}
}
