<?php

namespace Tests\Wpunit;

use Rondo\Core\UserRoles;
use Rondo\Dashboard\BoardDashboard;
use Rondo\Fees\SeasonKey;
use Rondo\REST\Api;
use Rondo\REST\People;
use Rondo\VOG\VOGEmail;
use Rondo\VOG\VOGRequirement;
use Rondo\REST\UserSettings;
use Tests\Support\RondoTestCase;

class BoardDashboardTest extends RondoTestCase {
	private int $user_id;

	protected function set_up(): void {
		parent::set_up();
		$this->user_id = $this->createRondoUser( [ 'role' => 'rondo_bestuur' ] );
		wp_set_current_user( $this->user_id );
		$this->bootRestControllers( [ Api::class, UserSettings::class ] );
		add_filter( 'pre_wp_mail', '__return_true' );
	}

	private function workspace(): array {
		$response = rest_do_request( new \WP_REST_Request( 'GET', '/rondo/v1/dashboard/workspace' ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'no-store, private', $response->get_headers()['Cache-Control'] );
		return $response->get_data();
	}

	public function test_board_has_six_blocks_and_membership_anniversaries_only_in_ninety_day_window(): void {
		$today = current_datetime();
		$ids   = [];
		foreach ( [ 0, 89, 90 ] as $days ) {
			$ids[ $days ] = $this->createPerson(
				[],
				[
					'first_name' => 'Jubilaris',
					'lid_sinds'  => $today->modify( "+{$days} days" )->modify( '-25 years' )->format( 'Y-m-d' ),
					'birthdate'  => $today->modify( '-40 years' )->format( 'Y-m-d' ),
				]
				);
		}
		$former = $this->createPerson(
			[],
			[
				'former_member' => true,
				'lid_sinds'     => $today->modify( '-25 years' )->format( 'Y-m-d' ),
			]
			);
		$data   = $this->workspace();
		$this->assertTrue( $data['context']['board'] );
		$this->assertFalse( $data['context']['secretary'] );
		$this->assertSame( [ 'birthdays', 'anniversaries', 'attention', 'membership', 'volunteers', 'vog' ], $data['layout']['order'] );
		$this->assertSame( $data['layout']['order'], $data['layout']['defaults'] );
		$this->assertSame( [ $ids[0], $ids[89] ], array_column( array_column( $data['anniversaries'], 'person' ), 'id' ) );
		$this->assertNotContains( $former, array_column( $data['birthdays'], 'id' ) );
		$this->assertCount( 3, $data['birthdays'] );
		$this->assertSame( [], $data['teams'] );
		$this->assertSame( 403, rest_do_request( new \WP_REST_Request( 'GET', '/rondo/v1/dashboard/matches' ) )->get_status() );
		$me = rest_do_request( new \WP_REST_Request( 'GET', '/rondo/v1/user/me' ) )->get_data();
		$this->assertTrue( $me['can_access_dashboard'] );
		$this->assertTrue( $me['dashboard_context']['board'] );
	}

	public function test_membership_counts_dates_through_today_and_includes_departed_former_members(): void {
		$create_member = fn( array $post, array $fields ) => $this->createPerson(
			$post,
			array_merge(
			[
				'type_lid'       => 'Bondslid',
				'spelactiviteit' => 'Veldvoetbal',
			],
			$fields
			)
			);
		$today         = current_datetime()->format( 'Y-m-d' );
		$start         = substr( SeasonKey::current(), 0, 4 ) . '-07-01';
		$create_member( [], [ 'lid_sinds' => $start ] );
		$create_member(
			[],
			[
				'lid_sinds'     => $today,
				'lid_tot'       => $today,
				'former_member' => true,
				'type_lid'      => 'Oud bondslid',
			]
			);
		$create_member(
			[],
			[
				'lid_sinds' => '2000-01-01',
				'lid_tot'   => current_datetime()->modify( '+1 day' )->format( 'Y-m-d' ),
			]
			);
		$create_member(
			[],
			[
				'lid_sinds'     => '2000-01-01',
				'lid_tot'       => '2001-01-01',
				'former_member' => true,
				'type_lid'      => 'Oud bondslid',
			]
			);
		$create_member( [], [ 'lid_sinds' => current_datetime()->modify( '+1 day' )->format( 'Y-m-d' ) ] );
		$data = $this->workspace()['membership'];
		$this->assertSame( 2, $data['joined'] );
		$this->assertSame( 1, $data['left'] );
		$this->assertSame( 0, $data['left_unknown'] );
		$this->assertSame( 2, $data['active'] );
		$this->assertSame( $start, $data['from'] );
		$this->assertSame( $today, $data['to'] );
	}

	public function test_all_membership_figures_exclude_non_playing_and_non_association_members(): void {
		$today = current_datetime()->format( 'Y-m-d' );
		foreach ( [
			[ 'Bondslid', 'Veldvoetbal', false ],
			[ 'Oud bondslid', 'Zaalvoetbal', true ],
			[ 'Bondslid', '', false ],
			[ 'Bondslid', '-', false ],
			[ 'Bondslid', '  ', false ],
			[ 'Bondslid', null, false ],
			[ 'Oud bondslid', '-', true ],
			[ 'Verenigingslid', 'Veldvoetbal', false ],
			[ 'Oud verenigingslid', 'Veldvoetbal', true ],
			[ '', 'Veldvoetbal', false ],
		] as [ $type, $activity, $former ] ) {
			$this->createPerson(
				[],
				[
					'type_lid'       => $type,
					'spelactiviteit' => $activity,
					'former_member'  => $former,
					'lid_sinds'      => $today,
					'lid_tot'        => $today,
				]
				);
		}
		$data = $this->workspace()['membership'];
		$this->assertSame( 1, $data['active'] );
		$this->assertSame( 2, $data['joined'] );
		$this->assertSame( 2, $data['left'] );
		$this->assertSame( 5, $data['left_unknown'] );
	}

	public function test_board_section_revocations_remove_both_data_and_saved_blocks(): void {
		update_user_meta(
			$this->user_id,
			'rondo_role_dashboard_layout',
			[
				'order'  => [ 'vog', 'anniversaries', 'volunteers', 'membership' ],
				'hidden' => [ 'vog' ],
			]
			);
		$user = get_userdata( $this->user_id );
		foreach ( [ 'jubilarissen', 'ledenadministratie', 'vrijwilligers', 'vog' ] as $capability ) {
			$user->add_cap( $capability, false );
		}
		wp_set_current_user( 0 );
		wp_set_current_user( $this->user_id );
		$data = $this->workspace();
		foreach ( [ 'anniversaries', 'membership', 'volunteers', 'vog' ] as $key ) {
			$this->assertArrayNotHasKey( $key, $data );
		}
		$this->assertSame( [ 'birthdays', 'attention' ], $data['layout']['order'] );
		$this->assertSame( [], $data['layout']['hidden'] );
		$user->set_role( 'rondo_user' );
		wp_set_current_user( 0 );
		wp_set_current_user( $this->user_id );
		$this->assertSame( [], BoardDashboard::overview() );
		$this->assertSame( 403, rest_do_request( new \WP_REST_Request( 'GET', '/rondo/v1/dashboard/workspace' ) )->get_status() );
	}

	public function test_combined_roles_keep_each_block_once_and_retain_personal_layout(): void {
		$coordinator = UserRoles::add_custom_role( 'Board test coordinator' );
		update_option( 'rondo_age_group_access', [ $coordinator => [ 'Onder 13' ] ] );
		$user = get_userdata( $this->user_id );
		$user->add_role( $coordinator );
		$user->add_cap( 'wedstrijdzaken' );
		wp_set_current_user( 0 );
		wp_set_current_user( $this->user_id );
		update_user_meta(
			$this->user_id,
			'rondo_role_dashboard_layout',
			[
				'order'  => [ 'teams', 'birthdays', 'attention', 'matches' ],
				'hidden' => [ 'matches' ],
			]
			);
		$data = $this->workspace();
		$this->assertTrue( $data['context']['coordinator'] );
		$this->assertTrue( $data['context']['secretary'] );
		$this->assertCount( 8, $data['layout']['order'] );
		$this->assertSame( 1, array_count_values( $data['layout']['order'] )['birthdays'] );
		$this->assertSame( 'teams', $data['layout']['order'][0] );
		$this->assertSame( [ 'matches' ], $data['layout']['hidden'] );
		$user->remove_role( 'rondo_bestuur' );
		wp_set_current_user( 0 );
		wp_set_current_user( $this->user_id );
		$data = $this->workspace();
		$this->assertFalse( $data['context']['board'] );
		$this->assertSame( [ 'teams', 'birthdays', 'attention', 'matches' ], $data['layout']['order'] );
		$this->assertArrayNotHasKey( 'membership', $data );
		UserRoles::remove_custom_role( $coordinator );
		delete_option( 'rondo_age_group_access' );
	}

	public function test_vog_counts_match_overview_with_role_and_committee_exemptions(): void {
		VOGRequirement::invalidate_cache();
		$team      = $this->createOrganization();
		$role      = [
			'team'        => $team,
			'entity_type' => 'team',
			'job_title'   => 'Trainer',
			'is_current'  => true,
		];
		$now       = current_datetime();
		$dated_ids = [];
		foreach ( [ null, $now->modify( '-3 years' )->format( 'Y-m-d' ), $now->modify( '+20 days -3 years' )->format( 'Y-m-d' ), $now->format( 'Y-m-d' ) ] as $index => $date ) {
			$id = $this->createPerson(
				[],
				[
					'datum_vog'    => $date,
					'work_history' => [ $role ],
				]
				);
			update_post_meta( $id, 'huidig-vrijwilliger', '1' );
			if ( $date ) {
				$dated_ids[ $id ] = $date;
			}
			if ( $index === 1 ) {
				update_post_meta( $id, 'vog_justis_submitted_date', $now->format( 'Y-m-d' ) );
			}
		}
		$id = $this->createPerson(
			[],
			[
				'former_member' => true,
				'work_history'  => [ $role ],
			]
			);
		update_post_meta( $id, 'huidig-vrijwilliger', '1' );
		$commissie = self::factory()->post->create(
			[
				'post_type'   => 'commissie',
				'post_status' => 'publish',
			]
			);
		( new VOGEmail() )->update_exempt_roles( [ 'Omroepster' ] );
		( new VOGEmail() )->update_exempt_commissies( [ $commissie ] );
		foreach ( [ null, $now->modify( '+20 days -3 years' )->format( 'Y-m-d' ) ] as $date ) {
			foreach ( [
				[],
				[ array_merge( $role, [ 'job_title' => 'Omroepster' ] ) ],
				[
					array_merge(
					$role,
					[
						'team'        => $commissie,
						'entity_type' => 'commissie',
					]
					),
				],
			] as $roles ) {
				$id = $this->createPerson(
					[],
					[
						'datum_vog'    => $date,
						'work_history' => $roles,
					]
					);
				update_post_meta( $id, 'huidig-vrijwilliger', '1' );
			}
		}
		$this->createPerson();
		VOGRequirement::invalidate_cache();
		$this->assertSame(
			[
				'not_submitted_to_justis' => 1,
				'submitted_to_justis'     => 1,
				'expiring_soon'           => 1,
			],
			$this->workspace()['vog']
			);
		$counts = BoardDashboard::vog_counts();
		foreach ( [
			'not_submitted' => 'not_submitted_to_justis',
			'submitted'     => 'submitted_to_justis',
			'soon'          => 'expiring_soon',
		] as $status => $key ) {
			$request = new \WP_REST_Request( 'GET' );
			foreach ( [
				'page'         => 1,
				'per_page'     => 100,
				'ownership'    => 'all',
				'orderby'      => 'first_name',
				'order'        => 'asc',
				'vog_required' => '1',
			] as $param => $value ) {
				$request->set_param( $param, $value );
			}
			if ( $status === 'soon' ) {
				$request->set_param( 'vog_expiring_within_days', 30 );
			} else {
				$request->set_param( 'vog_missing', '1' );
				$request->set_param( 'vog_older_than_years', 3 );
				$request->set_param( 'vog_justis_status', $status );
			}
			$response = ( new People() )->get_filtered_people( $request );
			$this->assertSame( $counts[ $key ], $response->get_data()['total'], $key );
			foreach ( $dated_ids as $id => $date ) {
				update_post_meta( $id, 'datum-vog', $date );
			}
			$response = ( new People() )->get_filtered_people( $request );
			$this->assertSame( $counts[ $key ], $response->get_data()['total'], $key . ' ISO storage' );
			foreach ( $dated_ids as $id => $date ) {
				update_post_meta( $id, 'datum-vog', str_replace( '-', '', $date ) );
			}
		}
		delete_option( VOGEmail::OPTION_EXEMPT_ROLES );
		delete_option( VOGEmail::OPTION_EXEMPT_COMMISSIES );
		VOGRequirement::invalidate_cache();
	}

	public function test_upgrade_grants_board_anniversaries_once_without_granting_other_sections(): void {
		$board = get_role( 'rondo_bestuur' );
		$board->remove_cap( 'jubilarissen' );
		$board->remove_cap( 'vog' );
		update_option( UserRoles::ROLES_VERSION_OPTION, 16 );
		( new UserRoles() )->maybe_upgrade_roles();
		$this->assertTrue( get_role( 'rondo_bestuur' )->has_cap( 'jubilarissen' ) );
		$this->assertFalse( get_role( 'rondo_bestuur' )->has_cap( 'vog' ) );
		$this->assertFalse( get_role( 'rondo_user' )->has_cap( 'jubilarissen' ) );
		get_role( 'rondo_bestuur' )->remove_cap( 'jubilarissen' );
		( new UserRoles() )->maybe_upgrade_roles();
		$this->assertFalse( get_role( 'rondo_bestuur' )->has_cap( 'jubilarissen' ) );
	}

	public function test_shortage_summary_excludes_full_cancelled_past_and_out_of_window_and_totals_before_limit(): void {
		$now    = current_datetime();
		$person = $this->createPerson();
		for ( $i = 0; $i < 17; ++$i ) {
			$id = self::factory()->post->create(
				[
					'post_type'   => 'dienst_shift',
					'post_status' => 'publish',
					'post_title'  => 'Dienst ' . $i,
				]
				);
			update_post_meta( $id, 'start_datetime', $now->modify( $i === 15 ? '-1 day' : ( $i === 16 ? '+31 days' : '+1 day' ) )->format( 'Y-m-d H:i:s' ) );
			update_post_meta( $id, 'status', $i === 14 ? 'geannuleerd' : 'open' );
			update_post_meta( $id, 'capacity', $i === 13 ? 1 : 3 );
			update_post_meta( $id, 'assigned_persons', [ $person, $person, 99999999 ] );
		}
		$data = $this->workspace()['volunteers'];
		$this->assertSame( 13, $data['total_shifts'] );
		$this->assertSame( 26, $data['open_spots'] );
		$this->assertCount( 12, $data['shifts'] );
		$this->assertArrayNotHasKey( 'assigned_persons', $data['shifts'][0] );
	}
}
