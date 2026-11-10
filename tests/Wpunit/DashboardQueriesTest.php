<?php

namespace Tests\Wpunit;

use Rondo\Dashboard\DashboardPeople;
use Rondo\REST\Api;
use Tests\Support\RondoTestCase;

class DashboardQueriesTest extends RondoTestCase {
	private function summary(): array {
		$api = new Api();
		$api->invalidate_dashboard_cache();
		return $api->get_dashboard_summary( new \WP_REST_Request( 'GET', '/rondo/v1/dashboard' ) )->get_data();
	}

	public function test_counts_include_missing_empty_and_zero_flags_but_exclude_former_and_draft_people(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		foreach ( [ null, '', '0', '1' ] as $flag ) {
			$id = $this->createPerson();
			if ( $flag !== null ) {
				update_post_meta( $id, 'former_member', $flag );
			}
			update_post_meta( $id, 'huidig-vrijwilliger', '1' );
		}
		$this->createPerson( [ 'post_status' => 'draft' ] );
		foreach ( [ null, 'approved', 'resolved', 'declined' ] as $status ) {
			$id = self::factory()->post->create(
				[
					'post_type'   => 'rondo_feedback',
					'post_status' => 'publish',
				]
				);
			if ( $status !== null ) {
				update_post_meta( $id, 'status', $status );
			}
		}
		$data = $this->summary();
		$this->assertSame( 3, $data['stats']['total_people'] );
		$this->assertSame( 3, $data['stats']['total_volunteers'] );
		$this->assertSame( 2, $data['stats']['open_feedback_count'] );
		$this->assertCount( 3, $data['recent_people'] );
	}

	public function test_cached_people_queries_follow_metadata_changes_and_current_user_scope(): void {
		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin );
		$person = $this->createPerson();
		$this->assertSame( [ $person ], DashboardPeople::active_ids() );
		update_post_meta( $person, 'former_member', '1' );
		$this->assertSame( [], DashboardPeople::active_ids() );
		delete_post_meta( $person, 'former_member' );
		$this->assertSame( [ $person ], DashboardPeople::active_ids() );
		wp_set_current_user( $this->createRondoUser() );
		$this->assertSame( [], DashboardPeople::active_ids() );
		wp_set_current_user( $admin );
		$this->assertSame( [ $person ], DashboardPeople::active_ids() );
	}

	private function activity( int $person_id, string $date, string $approved = '1' ): int {
		$id = wp_insert_comment(
			[
				'comment_post_ID'  => $person_id,
				'comment_type'     => 'rondo_activity',
				'comment_content'  => 'Test activity',
				'comment_approved' => $approved,
			]
		);
		update_comment_meta( $id, 'activity_date', $date );
		return $id;
	}

	public function test_recent_contacts_use_latest_approved_activity_per_visible_active_person(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$first  = $this->createPerson();
		$second = $this->createPerson();
		$former = $this->createPerson( [], [ 'former_member' => true ] );
		$draft  = $this->createPerson( [ 'post_status' => 'draft' ] );
		$this->activity( $first, '2026-11-01' );
		$this->activity( $first, '2026-11-03' );
		$this->activity( $second, '2026-11-02' );
		$this->activity( $second, '2026-11-10', '0' );
		$this->activity( $former, '2026-11-09' );
		$this->activity( $draft, '2026-11-08' );
		$this->activity( $this->createOrganization(), '2026-11-07' );
		$contacts = $this->summary()['recently_contacted'];
		$this->assertSame( [ $first, $second ], array_column( $contacts, 'id' ) );
		$this->assertSame( [ '2026-11-03', '2026-11-02' ], array_column( $contacts, 'last_activity_date' ) );

		$this->activity( $second, '2026-11-04' );
		$this->assertSame( [ $second, $first ], array_column( $this->summary()['recently_contacted'], 'id' ) );
		wp_set_current_user( $this->createRondoUser() );
		$this->assertSame( [], $this->summary()['recently_contacted'] );
	}
}
