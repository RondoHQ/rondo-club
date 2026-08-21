<?php

namespace Tests\Wpunit;

use Rondo\Fees\FeeCache;
use Rondo\Fees\FeeServices;
use Rondo\Fees\PersonFeeContext;
use Rondo\Finance\BulkInvoiceCreator;
use Rondo\REST\Fees;
use Tests\Support\RondoTestCase;

/**
 * Former-member fees require a verified overlap with the requested season.
 */
class FormerMemberFeeEligibilityTest extends RondoTestCase {

	private const SEASON = '2026-2027';

	private function create_former_member( string $lid_sinds, ?string $lid_tot ): int {
		$fields = [
			'former_member' => true,
			'lid_sinds'     => $lid_sinds,
		];

		if ( $lid_tot !== null ) {
			$fields['lid_tot'] = $lid_tot;
		}

		return $this->createPerson( [], $fields );
	}

	public function test_former_member_requires_both_membership_dates(): void {
		$person_id = $this->create_former_member( '1946-08-07', null );

		$this->assertFalse( ( new PersonFeeContext() )->is_former_member_in_season( $person_id, self::SEASON ) );
	}

	public function test_former_member_must_overlap_the_requested_season(): void {
		$before_season = $this->create_former_member( '2020-01-01', '2026-06-30' );
		$during_season = $this->create_former_member( '2020-01-01', '2026-08-19' );
		$after_season  = $this->create_former_member( '2027-07-01', '2027-08-01' );
		$context       = new PersonFeeContext();

		$this->assertFalse( $context->is_former_member_in_season( $before_season, self::SEASON ) );
		$this->assertTrue( $context->is_former_member_in_season( $during_season, self::SEASON ) );
		$this->assertFalse( $context->is_former_member_in_season( $after_season, self::SEASON ) );
	}

	public function test_bulk_invoicing_skips_former_member_without_end_date_before_using_cache(): void {
		$person_id = $this->create_former_member( '1946-08-07', null );
		FeeServices::fee_cache()->save_fee_cache(
			$person_id,
			[
				'category'  => 'donateur',
				'base_fee'  => 57,
				'final_fee' => 57,
			],
			self::SEASON
		);

		$this->assertSame( 'skipped', ( new BulkInvoiceCreator() )->create_membership_invoice( $person_id, self::SEASON ) );
	}

	public function test_fee_list_and_summary_exclude_unverified_former_member_cache(): void {
		$person_id = $this->create_former_member( '1946-08-07', null );
		FeeServices::fee_cache()->save_fee_cache(
			$person_id,
			[
				'category'               => 'donateur',
				'base_fee'               => 57,
				'family_discount_amount' => 0,
				'final_fee'              => 57,
			],
			self::SEASON
		);
		$request = new \WP_REST_Request( 'GET' );
		$request->set_param( 'season', self::SEASON );
		$request->set_param( 'forecast', false );
		$fees = new Fees();

		$this->assertSame( 0, $fees->get_fee_list( $request )->get_data()['total'] );
		$this->assertSame( 0, $fees->get_fee_summary( $request )->get_data()['total'] );
	}

	public function test_fee_cache_expires_after_a_work_history_end_date_passes(): void {
		$yesterday = current_datetime()->modify( '-1 day' )->format( 'Y-m-d' );
		$person_id = $this->createPerson(
			[],
			[
				'work_history' => [
					[
						'job_title'  => 'Donateur',
						'is_current' => false,
						'end_date'   => $yesterday,
					],
				],
			]
		);
		$calls     = 0;
		$cache     = new FeeCache(
			static function () use ( &$calls ) {
				++$calls;
				return null;
			}
		);
		$meta_key  = $cache->get_fee_cache_meta_key( self::SEASON );

		update_post_meta(
			$person_id,
			$meta_key,
			[
				'category'      => 'donateur',
				'final_fee'     => 57,
				'calculated_at' => $yesterday . ' 12:00:00',
			]
		);

		$this->assertNull( $cache->get_fee_for_person_cached( $person_id, self::SEASON ) );
		$this->assertSame( 1, $calls );
		$this->assertSame( '', get_post_meta( $person_id, $meta_key, true ) );
	}
}
