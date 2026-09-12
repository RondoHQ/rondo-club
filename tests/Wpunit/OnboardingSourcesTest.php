<?php

namespace Tests\Wpunit;

use Rondo\Onboarding\Sources;
use Rondo\Onboarding\Foundation;
use Rondo\REST\Onboarding;
use Tests\Support\RondoTestCase;

class OnboardingSourcesTest extends RondoTestCase {

	protected function set_up(): void {
		parent::set_up();
		delete_option( Sources::OPTION );
		Foundation::register();
	}

	private function source( string $id, string $version = 'one' ): array {
		return [
			'knvb_id'          => $id,
			'fingerprint'      => hash( 'sha256', $version ),
			'membership_state' => 'definitive',
		];
	}

	private function ingest( array $sources ) {
		return Sources::ingest(
			[
				'observed_at' => gmdate( 'c' ),
				'sources'     => $sources,
			]
			);
	}

	public function test_initial_population_is_baseline_and_absence_never_creates_new_identity(): void {
		$first = $this->ingest( [ $this->source( 'OLD' ) ] );
		$this->assertSame( 0, $first['pending_count'] );
		$next = $this->ingest( [ $this->source( 'NEW' ) ] );
		$this->assertSame( 'NEW', $next['checks'][0]['knvb_id'] );
		$this->assertFalse( $next['checks'][0]['baseline'] );
		$this->assertSame( 2, $next['baseline_count'] );
		$this->assertSame( 0, $this->ingest( [ $this->source( 'OLD' ) ] )['pending_count'] );
		$this->assertSame( 1, $this->ingest( [ $this->source( 'OLD', 'changed' ) ] )['pending_count'] );
	}

	public function test_new_registration_opens_once_but_recovery_of_old_members_does_not(): void {
		$this->ingest( [ $this->source( 'BASE' ) ] );
		$this->ingest( [ $this->source( 'NEW' ), $this->source( 'RECOVERY' ) ] );
		foreach ( [
			'NEW'      => current_datetime()->format( 'Y-m-d' ),
			'RECOVERY' => '2000-01-01',
		] as $knvb => $start ) {
			$id     = $this->createPerson(
				[],
				[
					'first_name' => 'Test',
					'knvb_id'    => $knvb,
					'lid_sinds'  => $start,
				]
				);
			$input  = [
				'observation_id'   => wp_generate_uuid4(),
				'knvb_id'          => $knvb,
				'observed_at'      => gmdate( 'c' ),
				'membership_state' => 'definitive',
				'snapshot_hash'    => Foundation::snapshot_hash( $id ),
				'coverage'         => array_fill_keys( Foundation::COVERAGE, true ),
			];
			$result = Foundation::observe( $id, $input );
			$this->assertIsArray( $result );
			$this->assertSame( $result, Foundation::observe( $id, $input ) );
			$this->assertSame( $knvb === 'NEW', $result['round_id'] > 0 );
		}
	}

	public function test_acknowledgement_requires_stored_complete_observation_and_current_fingerprint(): void {
		$this->ingest( [ $this->source( 'BASE' ) ] );
		$this->ingest( [ $this->source( 'NEW' ) ] );
		$input = $this->source( 'NEW' ) + [
			'person_id'      => 0,
			'observation_id' => 'missing-observation',
		];
		$this->assertFalse( Sources::finish( $input )['complete'] );
		$this->assertSame( 1, $this->ingest( [ $this->source( 'NEW' ) ] )['pending_count'] );
		$this->ingest( [ $this->source( 'NEW', 'changed' ) ] );
		$this->assertWPError( Sources::finish( $input ) );
	}

	public function test_duplicate_empty_stale_and_unauthorized_inventories_are_rejected(): void {
		$this->assertWPError( $this->ingest( [] ) );
		$this->assertWPError( $this->ingest( [ $this->source( 'A' ), $this->source( 'A' ) ] ) );
		$this->assertWPError(
			Sources::ingest(
			[
				'observed_at' => '2000-01-01T00:00:00Z',
				'sources'     => [ $this->source( 'A' ) ],
			]
			)
			);
		$this->bootRestControllers( [ Onboarding::class ] );
		wp_set_current_user( 0 );
		$request = new \WP_REST_Request( 'POST', '/rondo/v1/onboarding/sources' );
		$this->assertSame( 401, rest_do_request( $request )->get_status() );
		$this->assertFalse( get_option( Sources::OPTION ) );
	}

	public function test_complete_acknowledgement_clears_pending_until_source_changes_again(): void {
		$this->ingest( [ $this->source( 'BASE' ) ] );
		$this->ingest( [ $this->source( 'NEW' ) ] );
		$id    = $this->createPerson(
			[],
			[
				'first_name' => 'Test',
				'knvb_id'    => 'NEW',
				'lid_sinds'  => current_datetime()->format( 'Y-m-d' ),
			]
			);
		$input = [
			'observation_id'   => wp_generate_uuid4(),
			'knvb_id'          => 'NEW',
			'observed_at'      => gmdate( 'c' ),
			'membership_state' => 'definitive',
			'snapshot_hash'    => Foundation::snapshot_hash( $id ),
			'coverage'         => array_fill_keys( Foundation::COVERAGE, true ),
		];
		$this->assertIsArray( Foundation::observe( $id, $input ) );
		$this->assertTrue( Sources::is_pending( 'NEW' ) );
		$finish = $this->source( 'NEW' ) + [
			'person_id'      => $id,
			'observation_id' => $input['observation_id'],
		];
		$this->assertTrue( Sources::finish( $finish )['complete'] );
		$this->assertTrue( Sources::finish( $finish )['complete'] );
		$this->assertFalse( Sources::is_pending( 'NEW' ) );
		$this->assertSame( 0, $this->ingest( [ $this->source( 'NEW' ) ] )['pending_count'] );
		$this->assertSame( 1, $this->ingest( [ $this->source( 'NEW', 'changed' ) ] )['pending_count'] );
	}

	public function test_unconfirmed_initial_registration_can_become_new_but_initial_definitive_member_cannot(): void {
		$source                     = $this->source( 'PENDING' );
		$source['membership_state'] = 'unknown';
		$this->ingest( [ $source, $this->source( 'EXISTING' ) ] );
		$this->ingest( [ $this->source( 'PENDING', 'confirmed' ), $this->source( 'EXISTING', 'changed' ) ] );
		$start = new \DateTimeImmutable( current_datetime()->format( 'Y-m-d' ), wp_timezone() );
		$this->assertTrue( Sources::is_new_registration( 'PENDING', $start ) );
		$this->assertFalse( Sources::is_new_registration( 'EXISTING', $start ) );
	}

	public function test_explicit_check_keeps_existing_identity_baseline(): void {
		$this->ingest( [ $this->source( 'EXISTING' ) ] );
		$result = Sources::ingest(
			[
				'observed_at' => gmdate( 'c' ),
				'sources'     => [ $this->source( 'EXISTING' ) ],
				'check_ids'   => [ 'EXISTING' ],
			]
			);
		$this->assertSame( 1, $result['pending_count'] );
		$this->assertTrue( $result['checks'][0]['baseline'] );
		$this->assertFalse( Sources::is_new_registration( 'EXISTING', new \DateTimeImmutable( 'tomorrow' ) ) );
		$this->assertWPError(
			Sources::ingest(
			[
				'observed_at' => gmdate( 'c' ),
				'sources'     => [ $this->source( 'EXISTING' ) ],
				'check_ids'   => [ 'MISSING' ],
			]
			)
			);
	}
}
