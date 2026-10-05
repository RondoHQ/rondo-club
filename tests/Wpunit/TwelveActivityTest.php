<?php
namespace Tests\Wpunit;

use Rondo\Twelve\Activity;
use Rondo\Twelve\BrowserImport;
use Rondo\Twelve\MatchArchive;
use Rondo\Twelve\ProductClassification;
use Rondo\Twelve\ReportAggregator;
use Tests\Support\RondoTestCase;

class TwelveActivityTest extends RondoTestCase {
	private function data(): array {
		$products = [
			[
				'name'              => 'Snack',
				'cashCents'         => 200,
				'businessclubCents' => 0,
			],
			[
				'name'              => 'Drink',
				'cashCents'         => 100,
				'businessclubCents' => 100,
			],
		];
		return [
			'period_start'    => '2026-10-01 06:00',
			'source'          => [ 'coverage_end' => '2026-10-02 06:00' ],
			'product_revenue' => [
				'products' => array_map(
				static fn( $p ) => [
					'product'           => $p['name'],
					'cashCents'         => $p['cashCents'],
					'businessclubCents' => $p['businessclubCents'],
				],
				$products
				),
			],
			'activity'        => [
				'version'      => 1,
				'transactions' => [
					[
						'id'        => '1',
						'kind'      => 'sale',
						'localTime' => '2026-10-02 01:30',
						'products'  => $products,
					],
				],
			],
		];
	}
	public function test_baskets_and_product_revenue_are_different_partitions_and_unknown_is_not_zero(): void {
		update_option(
			ProductClassification::OPTION,
			[
				hash( 'sha256', 'Snack' ) => 'food',
				hash( 'sha256', 'Drink' ) => 'non_food',
			]
			);
		$data = $this->data();
		$this->assertTrue( Activity::validate( $data ) );
		$hours = array_column( Activity::hours( $data ), null, 'hour' );
		$this->assertSame( 1, $hours['01']['transactions']['mixed'] );
		$this->assertSame( 200, $hours['01']['revenue']['food'] );
		$this->assertSame( 200, $hours['01']['revenue']['non_food'] );
		$this->assertSame( 0, $hours['06']['transactions']['mixed'] );
		$this->assertNull( Activity::hours( [] ) );
		delete_option( ProductClassification::OPTION );
		$hours = array_column( Activity::hours( $data ), null, 'hour' );
		$this->assertSame( 1, $hours['01']['transactions']['unassigned'] );
	}
	public function test_activity_rejects_duplicates_invalid_times_and_a_single_cent_drift(): void {
		$data                               = $this->data();
		$data['activity']['transactions'][] = $data['activity']['transactions'][0];
		$this->assertFalse( Activity::validate( $data ) );
		$data = $this->data();
		++$data['activity']['transactions'][0]['products'][0]['cashCents'];
		$this->assertFalse( Activity::validate( $data ) );
		foreach ( [ '2026-10-02 24:00', '2026-10-01 05:59', '2026-10-02 06:00' ] as $time ) {
			$data = $this->data();
			$data['activity']['transactions'][0]['localTime'] = $time;
			$this->assertFalse( Activity::validate( $data ) );
		}
	}
	public function test_corrections_reduce_revenue_without_becoming_purchases_and_exclusions_apply(): void {
		$data                                        = $this->data();
		$data['activity']['transactions'][0]['kind'] = 'correction';
		foreach ( $data['activity']['transactions'][0]['products'] as &$p ) {
			$p['cashCents']         *= -1;
			$p['businessclubCents'] *= -1;
		}
		unset( $p );
		foreach ( $data['product_revenue']['products'] as &$p ) {
			$p['cashCents']         *= -1;
			$p['businessclubCents'] *= -1;
		}
		update_option(
			ProductClassification::OPTION,
			[
				hash( 'sha256', 'Snack' ) => 'merchandise',
				hash( 'sha256', 'Drink' ) => 'non_food',
			]
			);
		$hours = array_column( Activity::hours( $data ), null, 'hour' );
		$this->assertSame( 0, array_sum( $hours['01']['transactions'] ) );
		$this->assertSame( -200, array_sum( $hours['01']['revenue'] ) );
		$this->assertSame( 1, $hours['01']['corrections'] );
	}
	public function test_staffing_deduplicates_overlaps_and_preserves_partial_hour_changes(): void {
		$type = self::factory()->post->create(
			[
				'post_type'  => 'dienst_type',
				'meta_input' => [ '_rondo_seed_key' => 'kantine_bar' ],
			]
			);
		foreach ( [ [ '10:00', '10:30', [ 10 ] ], [ '10:15', '11:00', [ 10, 20 ] ], [ '10:00', '11:00', [ 30 ], 'geannuleerd' ] ] as $row ) {
			self::factory()->post->create(
				[
					'post_type'  => 'dienst_shift',
					'meta_input' => [
						'dienst_type_id'   => $type,
						'start_datetime'   => '2026-10-01 ' . $row[0] . ':00',
						'end_datetime'     => '2026-10-01 ' . $row[1] . ':00',
						'assigned_persons' => $row[2],
						'status'           => $row[3] ?? 'vol',
					],
				]
				);
		}
		$staff = Activity::staffing( '2026-10-01' );
		$this->assertSame(
			[
				'scheduled' => true,
				'min'       => 1,
				'max'       => 2,
			],
			$staff['10']
			);
		$this->assertFalse( $staff['11']['scheduled'] );
	}
	public function test_first_team_identity_and_venue_exclusions(): void {
		$source = new \Rondo\Narrowcasting\SportlinkMatchday( false );
		$config = [
			'venue'          => 'De Wijchert',
			'first_team_ids' => [ '641' ],
			'u23_team_ids'   => [ '568' ],
		];
		$row    = [
			'wedstrijdcode'  => 123,
			'wedstrijddatum' => '2026-10-03T15:00:00+02:00',
			'thuisteam'      => 'AWC 1',
			'uitteam'        => 'Test',
			'thuisteamid'    => 641,
			'accommodatie'   => 'Sportpark De Wijchert',
		];
		$this->assertSame( 'first', MatchArchive::normalize( $row, $source, $config )['special'] );
		$row['thuisteamid'] = 355146;
		$this->assertNull( MatchArchive::normalize( $row, $source, $config )['special'] );
		$row['status'] = 'Afgelast';
		$this->assertSame( 'Afgelast of vervallen', MatchArchive::normalize( $row, $source, $config )['reason'] );
		$this->assertNull( MatchArchive::summary( [] )['first_home'] );
		$this->assertFalse( MatchArchive::summary( [ 'complete' => true ] )['first_home'] );
	}
	public function test_comparison_requires_five_complete_same_weekday_days(): void {
		$reports = [];
		$archive = [];
		$target  = MatchArchive::summary( [ 'complete' => true ] );
		foreach ( [ '2026-08-29', '2026-09-05', '2026-09-12', '2026-09-19', '2026-09-26' ] as $index => $date ) {
			$reports[]        = [
				'period_start' => $date . ' 06:00',
				'data'         => [
					'source'          => [ 'complete' => true ],
					'product_revenue' => [ 'products' => [] ],
					'omzet'           => [
						[
							'section' => 'totaal',
							'label'   => 'Omzet (excl. no-sale)',
							'bedrag'  => ( $index + 1 ) * 100,
						],
					],
				],
			];
			$archive[ $date ] = [ 'complete' => true ];
		}
		$this->assertSame( 300.0, Activity::comparison( '2026-10-03', $target, $reports, $archive )['median'] );
		$archive['2026-08-29']['complete'] = false;
		$this->assertNull( Activity::comparison( '2026-10-03', $target, $reports, $archive )['median'] );
		$this->assertSame( 4, Activity::comparison( '2026-10-03', $target, $reports, $archive )['count'] );
	}
	public function test_activity_endpoint_requires_kassa_access_and_valid_date(): void {
		$server  = $this->bootRestControllers( [ \Rondo\REST\TwelveReports::class ] );
		$request = new \WP_REST_Request( 'GET', '/rondo/v1/twelve/activity' );
		$request->set_param( 'date', '2026-10-03' );
		$this->assertSame( 401, $server->dispatch( $request )->get_status() );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'rondo_user' ] ) );
		$this->assertSame( 403, $server->dispatch( $request )->get_status() );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertSame( 200, $server->dispatch( $request )->get_status() );
		$request->set_param( 'date', '2026-02-31' );
		$this->assertSame( 400, $server->dispatch( $request )->get_status() );
	}
}
