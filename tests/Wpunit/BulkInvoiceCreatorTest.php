<?php

namespace Tests\Wpunit;

use Rondo\Fees\FeeServices;
use Rondo\Finance\BulkInvoiceCreator;
use Tests\Support\RondoTestCase;

/**
 * Bulk invoice jobs should only queue people who can receive a contribution invoice.
 */
class BulkInvoiceCreatorTest extends RondoTestCase {

	private const SEASON = '2026-2027';

	protected function setUp(): void {
		parent::setUp();
		delete_option( BulkInvoiceCreator::JOB_OPTION );
		delete_option( BulkInvoiceCreator::JOB_LOCK_OPTION );
	}

	protected function tearDown(): void {
		delete_option( BulkInvoiceCreator::JOB_OPTION );
		delete_option( BulkInvoiceCreator::JOB_LOCK_OPTION );
		parent::tearDown();
	}

	public function test_job_total_only_counts_invoice_eligible_people(): void {
		$eligible = $this->createPerson();
		$zero_fee = $this->createPerson();
		$this->createPerson();
		$former = $this->createPerson(
			[],
			[
				'former_member' => true,
				'lid_sinds'     => '2020-01-01',
				'lid_tot'       => '2026-06-30',
			]
		);

		FeeServices::fee_cache()->save_fee_cache(
			$eligible,
			[
				'category'  => 'senior',
				'base_fee'  => 250,
				'final_fee' => 250,
			],
			self::SEASON
		);
		FeeServices::fee_cache()->save_fee_cache(
			$zero_fee,
			[
				'category'  => 'contributievrij',
				'base_fee'  => 0,
				'final_fee' => 0,
			],
			self::SEASON
		);
		FeeServices::fee_cache()->save_fee_cache(
			$former,
			[
				'category'  => 'senior',
				'base_fee'  => 250,
				'final_fee' => 250,
			],
			self::SEASON
		);

		$result = BulkInvoiceCreator::start_job( self::SEASON );
		$state  = get_option( BulkInvoiceCreator::JOB_OPTION, [] );

		$this->assertIsArray( $result );
		$this->assertSame( 1, $result['total'] );
		$this->assertSame( [ $eligible ], $state['person_ids'] );
	}

	public function test_job_excludes_people_who_already_have_a_membership_invoice(): void {
		$already_invoiced = $this->createPerson();
		$pending          = $this->createPerson();

		foreach ( [ $already_invoiced, $pending ] as $person_id ) {
			FeeServices::fee_cache()->save_fee_cache(
				$person_id,
				[
					'category'  => 'senior',
					'base_fee'  => 250,
					'final_fee' => 250,
				],
				self::SEASON
			);
		}

		$invoice_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_invoice',
				'post_status' => 'rondo_paid',
			]
		);
		\Rondo\Fields\Fields::update_for_post( $invoice_id, 'person', $already_invoiced );
		\Rondo\Fields\Fields::update_for_post( $invoice_id, 'invoice_type', 'membership' );
		update_post_meta( $invoice_id, '_invoice_season', self::SEASON );

		$result = BulkInvoiceCreator::start_job( self::SEASON );
		$state  = get_option( BulkInvoiceCreator::JOB_OPTION, [] );

		$this->assertSame( 1, $result['total'] );
		$this->assertSame( [ $pending ], $state['person_ids'] );
		$this->assertSame( 1, BulkInvoiceCreator::count_people_without_invoice( [ $already_invoiced, $pending ], self::SEASON ) );
	}

	public function test_batch_lock_prevents_the_same_offset_from_being_processed_twice(): void {
		update_option(
			BulkInvoiceCreator::JOB_OPTION,
			[
				'season'      => self::SEASON,
				'status'      => 'running',
				'total'       => 0,
				'offset'      => 0,
				'created'     => 0,
				'skipped'     => 0,
				'errors'      => 0,
				'started_at'  => current_time( 'Y-m-d H:i:s' ),
				'finished_at' => null,
				'person_ids'  => [],
			],
			false
		);
		add_option( BulkInvoiceCreator::JOB_LOCK_OPTION, time(), '', false );

		( new BulkInvoiceCreator() )->run_batch();

		$state = get_option( BulkInvoiceCreator::JOB_OPTION, [] );
		$this->assertSame( 'running', $state['status'] );
		$this->assertSame( 0, $state['offset'] );
	}
}
