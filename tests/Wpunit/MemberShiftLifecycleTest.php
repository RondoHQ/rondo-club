<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\REST\MemberShifts;
use Rondo\Fees\SeasonKey;
use Rondo\Volunteer\ShiftCancellationService;
use Rondo\Volunteer\ShiftEmailScheduler;
use Rondo\Volunteer\VolunteerEligibilityService;
use Rondo\Volunteer\VolunteerObligationCalculator;
use Tests\Support\RondoTestCase;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Regression coverage for member cancellation and scheduled shift emails.
 */
class MemberShiftLifecycleTest extends RondoTestCase {
	private const TYPE_PARENT = 2;
	private const TYPE_CHILD  = 3;

	private MemberShifts $controller;
	private WP_REST_Server $server;

	/** @var array<int, array<string, mixed>> */
	private array $sent_mail = [];

	protected function set_up(): void {
		parent::set_up();

		global $wp_rest_server;
		$wp_rest_server   = new WP_REST_Server();
		$this->server     = $wp_rest_server;
		$this->controller = new MemberShifts();
		do_action( 'rest_api_init' );

		add_filter(
			'pre_wp_mail',
			function ( $short_circuit, $atts ) {
				$atts['attachment_contents'] = [];
				foreach ( (array) ( $atts['attachments'] ?? [] ) as $attachment ) {
					if ( is_string( $attachment ) && is_readable( $attachment ) ) {
						// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- inspect temporary test attachment.
						$atts['attachment_contents'][ wp_basename( $attachment ) ] = file_get_contents( $attachment );
					}
				}
				$this->sent_mail[] = $atts;
				return true;
			},
			10,
			2
		);
	}

	private function member( string $login = 'shift_policy_member' ): array {
		$user_id   = $this->createRondoUser( [ 'user_login' => $login ] );
		$person_id = $this->createPerson( [ 'post_title' => 'Test Vrijwilliger' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $person_id );
		wp_set_current_user( $user_id );
		return [ $user_id, $person_id ];
	}

	private function shift( array $assigned, int $days_until_start ): int {
		$start    = current_datetime()->modify( sprintf( '%+d days', $days_until_start ) );
		$shift_id = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
				'post_title'  => 'Testdienst',
			]
		);
		update_post_meta( $shift_id, 'start_datetime', $start->format( 'Y-m-d H:i:s' ) );
		update_post_meta( $shift_id, 'end_datetime', $start->modify( '+2 hours' )->format( 'Y-m-d H:i:s' ) );
		update_post_meta( $shift_id, 'status', 'open' );
		update_post_meta( $shift_id, 'assigned_persons', $assigned );
		return $shift_id;
	}

	private function link_parent_child( int $parent_id, int $child_id ): void {
		$parent_relationships   = Fields::get_for_post( $parent_id, 'relationships' ) ?: [];
		$parent_relationships[] = [
			'related_person'    => $child_id,
			'relationship_type' => self::TYPE_CHILD,
		];
		Fields::update_for_post( $parent_id, 'relationships', $parent_relationships );
		Fields::update_for_post(
			$child_id,
			'relationships',
			[
				[
					'related_person'    => $parent_id,
					'relationship_type' => self::TYPE_PARENT,
				],
			]
		);
	}

	public function test_person_profile_shifts_include_all_future_and_only_two_most_recent_past_shifts(): void {
		[, $person_id] = $this->member( 'person_shift_overview_member' );

		$future_later = $this->shift( [ $person_id ], 5 );
		$future_next  = $this->shift( [ $person_id ], 2 );
		$cancelled    = $this->shift( [ $person_id ], 3 );
		update_post_meta( $cancelled, 'status', 'geannuleerd' );

		$past_oldest = $this->shift( [ $person_id ], -8 );
		$past_second = $this->shift( [ $person_id ], -4 );
		$past_latest = $this->shift( [ $person_id ], -1 );
		foreach ( [ $past_oldest, $past_second, $past_latest ] as $shift_id ) {
			update_post_meta( $shift_id, 'status', 'voltooid' );
		}

		$request = new WP_REST_Request( 'GET', "/rondo/v1/people/{$person_id}/shifts" );
		$request->set_param( 'person_id', $person_id );
		$response = $this->server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertSame( [ $future_next, $future_later ], array_column( $data['upcoming'], 'id' ) );
		$this->assertSame( [ $past_latest, $past_second ], array_column( $data['recent'], 'id' ) );
		$this->assertNotContains( $cancelled, array_column( $data['upcoming'], 'id' ) );
		$this->assertArrayNotHasKey( 'assigned_person_ids', $data['upcoming'][0] );
		$this->assertArrayNotHasKey( 'fellow_volunteer_contacts', $data['upcoming'][0] );
	}

	public function test_person_profile_includes_discounted_shared_family_obligation(): void {
		$parent_id    = $this->createPerson( [ 'post_title' => 'Ouder' ] );
		$first_child  = $this->createPerson( [ 'post_title' => 'Eerste kind' ] );
		$second_child = $this->createPerson( [ 'post_title' => 'Tweede kind' ] );
		update_post_meta( $first_child, 'leeftijdsgroep', 'Onder 12' );
		update_post_meta( $second_child, 'leeftijdsgroep', 'Onder 9' );
		$this->link_parent_child( $parent_id, $first_child );
		$this->link_parent_child( $parent_id, $second_child );
		VolunteerEligibilityService::invalidate_cache();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$request = new WP_REST_Request( 'GET', "/rondo/v1/people/{$second_child}/shifts" );
		$request->set_param( 'person_id', $second_child );
		$response = $this->server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertSame( SeasonKey::current(), $data['season'] );
		$this->assertCount( 1, $data['obligations'] );
		$this->assertSame( 'gezin', $data['obligations'][0]['kind'] );
		$this->assertSame( 2, $data['obligations'][0]['child_count'] );
		$this->assertSame( 3, $data['obligations'][0]['required_count'] );
	}

	public function test_recent_signups_returns_at_most_ten_shifts_with_names_in_signup_order(): void {
		$manager_id = $this->createRondoUser(
			[
				'role'       => 'rondo_vrijwilligers',
				'user_login' => 'recent_signup_manager',
			]
		);
		wp_set_current_user( $manager_id );

		$anne_id = $this->createPerson( [ 'post_title' => 'Anne Recent' ] );
		$bob_id  = $this->createPerson( [ 'post_title' => 'Bob Recent' ] );
		$base    = time() - 1000;
		$ids     = [];

		for ( $index = 0; $index < 51; ++$index ) {
			$shift_id = $this->shift( [ $anne_id ], 5 );
			update_post_meta( $shift_id, '_shift_signup_at_' . $anne_id, $base + $index );
			$ids[] = $shift_id;
		}

		$latest_shift_id = $ids[50];
		update_post_meta( $latest_shift_id, 'assigned_persons', [ $anne_id, $bob_id ] );
		update_post_meta( $latest_shift_id, '_shift_signup_at_' . $bob_id, $base + 100 );
		$cancelled_shift_id = $this->shift( [ $bob_id ], 2 );
		update_post_meta( $cancelled_shift_id, 'status', 'geannuleerd' );
		update_post_meta( $cancelled_shift_id, '_shift_signup_at_' . $bob_id, $base + 200 );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/shifts/recent-signups' ) );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertCount( 10, $data['shifts'] );
		$this->assertSame( $latest_shift_id, $data['shifts'][0]['id'] );
		$this->assertSame( [ 'Bob Recent', 'Anne Recent' ], array_column( $data['shifts'][0]['signups'], 'name' ) );
		$this->assertSame( [ $bob_id, $anne_id ], array_column( $data['shifts'][0]['signups'], 'person_id' ) );
		$this->assertNotContains( $ids[0], array_column( $data['shifts'], 'id' ) );
		$this->assertNotContains( $cancelled_shift_id, array_column( $data['shifts'], 'id' ) );
	}

	public function test_signup_overview_returns_all_assignments_with_upcoming_shifts_first(): void {
		$manager_id = $this->createRondoUser(
			[
				'role'       => 'rondo_vrijwilligers',
				'user_login' => 'signup_overview_manager',
			]
		);
		wp_set_current_user( $manager_id );

		$person_id      = $this->createPerson( [ 'post_title' => 'Overview Volunteer' ] );
		$future_later   = $this->shift( [ $person_id ], 8 );
		$past_oldest    = $this->shift( [ $person_id ], -10 );
		$future_nearest = $this->shift( [ $person_id ], 3 );
		$past_nearest   = $this->shift( [ $person_id ], -1 );
		$cancelled      = $this->shift( [ $person_id ], 2 );
		update_post_meta( $cancelled, 'status', 'geannuleerd' );
		foreach ( [ $future_later, $past_oldest, $past_nearest, $cancelled ] as $index => $shift_id ) {
			update_post_meta( $shift_id, '_shift_signup_at_' . $person_id, time() - $index );
		}

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/shifts/signups' ) );
		$this->assertSame( 200, $response->get_status() );
		$data = $response->get_data();

		$this->assertSame(
			[ $future_nearest, $future_later, $past_nearest, $past_oldest ],
			array_column( $data['shifts'], 'id' )
		);
		$this->assertNull( $data['shifts'][0]['latest_signup_at'] );
		$this->assertSame( [ $person_id ], array_column( $data['shifts'][0]['signups'], 'person_id' ) );
		$this->assertNotContains( $cancelled, array_column( $data['shifts'], 'id' ) );

		$cancelled_request = new WP_REST_Request( 'GET', '/rondo/v1/shifts/signups' );
		$cancelled_request->set_param( 'status', 'cancelled' );
		$cancelled_data = $this->server->dispatch( $cancelled_request )->get_data();
		$this->assertSame( [ $cancelled ], array_column( $cancelled_data['shifts'], 'id' ) );
		$this->assertSame( 'geannuleerd', $cancelled_data['shifts'][0]['status'] );

		$all_request = new WP_REST_Request( 'GET', '/rondo/v1/shifts/signups' );
		$all_request->set_param( 'status', 'all' );
		$all_data = $this->server->dispatch( $all_request )->get_data();
		$this->assertContains( $cancelled, array_column( $all_data['shifts'], 'id' ) );
	}

	private function cancel( int $shift_id ) {
		$request = new WP_REST_Request( 'POST', '/rondo/v1/shifts/' . $shift_id . '/cancel' );
		$request->set_param( 'id', $shift_id );
		return $this->controller->cancel( $request );
	}

	private function cancel_shift( int $shift_id, string $reason = '' ) {
		$request = new WP_REST_Request( 'POST', '/rondo/v1/shifts/' . $shift_id . '/cancellation' );
		$request->set_param( 'id', $shift_id );
		$request->set_param( 'reason', $reason );
		return $this->controller->cancel_shift( $request );
	}

	public function test_signup_and_cancellation_immediately_refresh_obligation_progress(): void {
		[, $person_id] = $this->member( 'obligation_cache_member' );
		$shift_id      = $this->shift( [], 22 );
		$season        = SeasonKey::current();
		$unit          = [
			'unit_id'        => 'cache-regression-' . $person_id,
			'kind'           => 'speler',
			'person_ids'     => [ $person_id ],
			'required_count' => 2,
		];
		$calculator    = new VolunteerObligationCalculator();

		$this->assertSame( 0, $calculator->progress_for_unit( $unit, $season )['pending_count'] );

		$signup_request = new WP_REST_Request( 'POST', '/rondo/v1/shifts/' . $shift_id . '/signup' );
		$signup_request->set_param( 'id', $shift_id );
		$this->assertNotWPError( $this->controller->signup( $signup_request ) );
		$this->assertSame( 1, $calculator->progress_for_unit( $unit, $season )['pending_count'] );

		$this->assertNotWPError( $this->cancel( $shift_id ) );
		$this->assertSame( 0, $calculator->progress_for_unit( $unit, $season )['pending_count'] );
	}

	public function test_member_can_cancel_more_than_three_weeks_before_shift(): void {
		[, $person_id] = $this->member();
		$shift_id      = $this->shift( [ $person_id ], 22 );

		$response = $this->cancel( $shift_id );

		$this->assertNotWPError( $response );
		$this->assertSame( [], get_post_meta( $shift_id, 'assigned_persons', true ) );
	}

	public function test_member_can_cancel_within_thirty_minutes_of_signup(): void {
		[, $person_id] = $this->member();
		$shift_id      = $this->shift( [ $person_id ], 10 );
		update_post_meta( $shift_id, '_shift_signup_at_' . $person_id, time() - ( 29 * MINUTE_IN_SECONDS ) );

		$response = $this->cancel( $shift_id );

		$this->assertNotWPError( $response );
		$this->assertSame( [], get_post_meta( $shift_id, 'assigned_persons', true ) );
	}

	public function test_member_cannot_cancel_inside_three_weeks_after_grace_period(): void {
		[, $person_id] = $this->member();
		$shift_id      = $this->shift( [ $person_id ], 20 );
		update_post_meta( $shift_id, '_shift_signup_at_' . $person_id, time() - ( 31 * MINUTE_IN_SECONDS ) );

		$response = $this->cancel( $shift_id );

		$this->assertWPError( $response );
		$this->assertSame( 'shift_cancel_deadline_passed', $response->get_error_code() );
		$this->assertSame( [ $person_id ], get_post_meta( $shift_id, 'assigned_persons', true ) );
	}

	public function test_signup_confirmation_combines_shifts_after_ten_minutes_and_attaches_calendar(): void {
		[, $person_id] = $this->member( 'shift_confirmation_member' );
		\Rondo\Fields\Fields::update_for_post( $person_id, 'first_name', 'Anne' );
		\Rondo\Fields\Fields::update_for_post( $person_id, 'email_1', 'anne@example.com' );
		$type_id   = $this->dienst_type();
		$shift_one = $this->dated_shift( $type_id, [], new \DateTimeImmutable( '2026-12-04 09:00:00', wp_timezone() ) );
		$shift_two = $this->dated_shift( $type_id, [], new \DateTimeImmutable( '2026-12-05 09:00:00', wp_timezone() ) );
		update_post_meta( $shift_one, 'end_datetime', '2026-12-04 12:00:00' );
		update_post_meta( $shift_two, 'end_datetime', '2026-12-05 12:00:00' );
		$before = time();

		foreach ( [ $shift_one, $shift_two ] as $shift_id ) {
			$request = new WP_REST_Request( 'POST', '/rondo/v1/shifts/' . $shift_id . '/signup' );
			$request->set_param( 'id', $shift_id );
			$this->assertNotWPError( $this->controller->signup( $request ) );
		}

		$scheduled = wp_next_scheduled( ShiftEmailScheduler::SIGNUP_CONFIRMATION_CRON_HOOK, [ $person_id ] );
		$this->assertIsInt( $scheduled );
		$this->assertGreaterThanOrEqual( $before + ShiftEmailScheduler::SIGNUP_CONFIRMATION_DELAY_SECONDS, $scheduled );
		$this->assertLessThanOrEqual( time() + ShiftEmailScheduler::SIGNUP_CONFIRMATION_DELAY_SECONDS + 2, $scheduled );

		$scheduler = new ShiftEmailScheduler();
		$this->assertSame( 2, $scheduler->send_signup_confirmation( $person_id ) );
		$this->assertCount( 1, $this->sent_mail );
		$this->assertSame( 'Bevestiging van je 2 inschrijftaken', $this->sent_mail[0]['subject'] );
		$this->assertStringContainsString( 'vrijdag 4 december 2026', $this->sent_mail[0]['message'] );
		$this->assertStringContainsString( '09:00–12:00', $this->sent_mail[0]['message'] );
		$this->assertStringNotContainsString( 'Monday', $this->sent_mail[0]['message'] );
		$this->assertStringContainsString( 'kalenderbestand', $this->sent_mail[0]['message'] );
		$this->assertCount( 1, $this->sent_mail[0]['attachment_contents'] );

		$filename = array_key_first( $this->sent_mail[0]['attachment_contents'] );
		$calendar = $this->sent_mail[0]['attachment_contents'][ $filename ];
		$this->assertStringEndsWith( '.ics', $filename );
		$this->assertSame( 2, substr_count( $calendar, 'BEGIN:VEVENT' ) );
		$this->assertStringContainsString( 'SUMMARY:Bardienst', $calendar );
		$this->assertStringContainsString( 'BEGIN:VTIMEZONE', $calendar );
		$this->assertStringContainsString( 'TZID:Europe/Amsterdam', $calendar );
		$this->assertStringContainsString( 'DTSTART;TZID=Europe/Amsterdam:20261204T090000', $calendar );
		$this->assertStringContainsString( 'DTEND;TZID=Europe/Amsterdam:20261204T120000', $calendar );
		$this->assertStringNotContainsString( 'DTSTART:20261204T090000Z', $calendar );
		$this->assertNotEmpty( get_post_meta( $shift_one, '_shift_email_confirmation_sent_' . $person_id, true ) );
		$this->assertNotEmpty( get_post_meta( $shift_two, '_shift_email_confirmation_sent_' . $person_id, true ) );
		$this->assertSame( 0, $scheduler->send_signup_confirmation( $person_id ) );
		$this->assertCount( 1, $this->sent_mail );
	}

	public function test_member_can_download_calendar_with_all_non_cancelled_assignments(): void {
		[, $person_id] = $this->member( 'shift_calendar_download_member' );
		$type_id       = $this->dienst_type();
		$active_id     = $this->dated_shift( $type_id, [ $person_id ], new \DateTimeImmutable( '2026-12-04 09:00:00', wp_timezone() ) );
		$completed_id  = $this->dated_shift( $type_id, [ $person_id ], new \DateTimeImmutable( '2026-06-05 10:00:00', wp_timezone() ) );
		$cancelled_id  = $this->dated_shift( $type_id, [ $person_id ], new \DateTimeImmutable( '2026-12-06 11:00:00', wp_timezone() ) );
		update_post_meta( $completed_id, 'status', 'voltooid' );
		update_post_meta( $cancelled_id, 'status', 'geannuleerd' );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/my-shifts/calendar' ) );

		$this->assertSame( 200, $response->get_status() );
		$calendar = $response->get_data();
		$this->assertIsString( $calendar );
		$this->assertSame( 2, substr_count( $calendar, 'BEGIN:VEVENT' ) );
		$this->assertStringContainsString( 'UID:rondo-shift-' . $active_id . '-' . $person_id, $calendar );
		$this->assertStringContainsString( 'UID:rondo-shift-' . $completed_id . '-' . $person_id, $calendar );
		$this->assertStringNotContainsString( 'UID:rondo-shift-' . $cancelled_id . '-' . $person_id, $calendar );
		$this->assertStringContainsString( 'DESCRIPTION:Bekijk je inschrijftaken in Rondo:', $calendar );
		$this->assertStringContainsString( 'URL:' . home_url( '/vrijwillig' ), $calendar );
		$this->assertStringContainsString( 'DTSTART;TZID=Europe/Amsterdam:20261204T090000', $calendar );
	}

	public function test_calendar_download_requires_a_linked_person(): void {
		wp_set_current_user( $this->createRondoUser( [ 'user_login' => 'calendar_member_without_person' ] ) );

		$response = $this->server->dispatch( new WP_REST_Request( 'GET', '/rondo/v1/my-shifts/calendar' ) );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( 'no_person', $response->get_data()['code'] );
	}

	public function test_cancelling_during_confirmation_delay_suppresses_the_email(): void {
		[, $person_id] = $this->member( 'cancelled_shift_confirmation_member' );
		\Rondo\Fields\Fields::update_for_post( $person_id, 'email_1', 'cancelled@example.com' );
		$type_id  = $this->dienst_type();
		$shift_id = $this->dated_shift( $type_id, [], current_datetime()->modify( '+22 days' ) );
		$request  = new WP_REST_Request( 'POST', '/rondo/v1/shifts/' . $shift_id . '/signup' );
		$request->set_param( 'id', $shift_id );

		$this->assertNotWPError( $this->controller->signup( $request ) );
		$this->assertNotWPError( $this->cancel( $shift_id ) );
		$this->assertSame( 0, ( new ShiftEmailScheduler() )->send_signup_confirmation( $person_id ) );
		$this->assertCount( 0, $this->sent_mail );
		$this->assertEmpty( get_post_meta( $shift_id, '_shift_email_confirmation_sent_' . $person_id, true ) );
	}

	public function test_volunteer_manager_can_remove_after_deadline_but_plain_member_cannot(): void {
		[, $person_id] = $this->member();
		$shift_id      = $this->shift( [ $person_id ], 10 );

		$plain_response = $this->server->dispatch( new WP_REST_Request( 'DELETE', "/rondo/v1/shifts/{$shift_id}/assignees/{$person_id}" ) );
		$this->assertSame( 403, $plain_response->get_status() );

		$manager_id = $this->createRondoUser(
			[
				'role'       => 'rondo_vrijwilligers',
				'user_login' => 'shift_policy_manager',
			]
			);
		wp_set_current_user( $manager_id );
		$manager_response = $this->server->dispatch( new WP_REST_Request( 'DELETE', "/rondo/v1/shifts/{$shift_id}/assignees/{$person_id}" ) );

		$this->assertSame( 200, $manager_response->get_status() );
		$this->assertSame( [], get_post_meta( $shift_id, 'assigned_persons', true ) );
	}

	public function test_reminders_are_personalized_and_idempotent(): void {
		$now       = new \DateTimeImmutable( '2026-09-01 10:00:00', wp_timezone() );
		$type_id   = $this->dienst_type();
		$person_id = $this->mail_person( 'Anne', 'anne@example.com' );
		$shift_id  = $this->dated_shift( $type_id, [ $person_id ], $now->modify( '+14 days' ) );
		$scheduler = new ShiftEmailScheduler();

		$this->assertSame( 1, $scheduler->run_sweep( $now ) );
		$this->assertCount( 1, $this->sent_mail );
		$this->assertStringStartsWith( 'Denk aan Bardienst op ', $this->sent_mail[0]['subject'] );
		$this->assertStringNotContainsString( '{datum}', $this->sent_mail[0]['subject'] );
		$this->assertStringContainsString( 'Hoi Anne', $this->sent_mail[0]['message'] );
		$this->assertStringContainsString( 'dinsdag 15 september 2026', $this->sent_mail[0]['message'] );
		$this->assertNotEmpty( get_post_meta( $shift_id, '_shift_email_reminder_14_sent_' . $person_id, true ) );

		$this->assertSame( 0, $scheduler->run_sweep( $now ) );
		$this->assertSame( 1, $scheduler->run_sweep( $now->modify( '+7 days' ) ) );
		$this->assertSame( 1, $scheduler->run_sweep( $now->modify( '+12 days' ) ) );
		$this->assertCount( 3, $this->sent_mail );
		$this->assertNotEmpty( get_post_meta( $shift_id, '_shift_email_reminder_7_sent_' . $person_id, true ) );
		$this->assertNotEmpty( get_post_meta( $shift_id, '_shift_email_reminder_2_sent_' . $person_id, true ) );
	}

	public function test_reminders_ignore_invalid_assignee_ids(): void {
		$now       = new \DateTimeImmutable( '2026-09-01 10:00:00', wp_timezone() );
		$type_id   = $this->dienst_type();
		$person_id = $this->mail_person( 'Anne', 'anne-invalid-assignees@example.com' );
		$shift_id  = $this->dated_shift( $type_id, [ 0, 999999, $person_id ], $now->modify( '+14 days' ) );

		$this->assertSame( 1, ( new ShiftEmailScheduler() )->run_sweep( $now ) );
		$this->assertCount( 1, $this->sent_mail );
		$this->assertNotEmpty( get_post_meta( $shift_id, '_shift_email_reminder_14_sent_' . $person_id, true ) );
		$this->assertEmpty( get_post_meta( $shift_id, '_shift_email_reminder_14_sent_0', true ) );
		$this->assertEmpty( get_post_meta( $shift_id, '_shift_email_reminder_14_sent_999999', true ) );
	}

	/**
	 * The sweep is the only delivery path for reminders, so an exception that
	 * escapes it costs every later shift in the batch its email — silently. That
	 * happened on 2026-08-01 via an assignee id of 0. Keep failures per-shift.
	 */
	public function test_one_failing_shift_does_not_abort_the_sweep(): void {
		$now       = new \DateTimeImmutable( '2026-09-01 10:00:00', wp_timezone() );
		$type_id   = $this->dienst_type();
		$person_id = $this->mail_person( 'Anne', 'anne-sweep-resilience@example.com' );

		$broken_id  = $this->dated_shift( $type_id, [ $person_id ], $now->modify( '+14 days' ) );
		$healthy_id = $this->dated_shift( $type_id, [ $person_id ], $now->modify( '+14 days' ) );

		$thrower = static function ( $value, $object_id, $meta_key ) use ( $broken_id ) {
			if ( (int) $object_id === $broken_id && $meta_key === 'status' ) {
				throw new \RuntimeException( 'Simulated failure inside process_shift.' );
			}
			return $value;
		};
		add_filter( 'get_post_metadata', $thrower, 10, 3 );

		try {
			$sent = ( new ShiftEmailScheduler() )->run_sweep( $now );
		} finally {
			remove_filter( 'get_post_metadata', $thrower, 10 );
		}

		$this->assertSame( 1, $sent, 'De gezonde dienst wordt nog steeds verwerkt.' );
		$this->assertNotEmpty(
			get_post_meta( $healthy_id, '_shift_email_reminder_14_sent_' . $person_id, true ),
			'De dienst na de kapotte moet zijn herinnering alsnog krijgen.'
		);
		$this->assertEmpty( get_post_meta( $broken_id, '_shift_email_reminder_14_sent_' . $person_id, true ) );
	}

	public function test_survey_is_sent_one_day_later_with_google_forms_link(): void {
		$now       = new \DateTimeImmutable( '2026-09-16 12:00:00', wp_timezone() );
		$type_id   = $this->dienst_type();
		$person_id = $this->mail_person( 'Anne', 'anne@example.com' );
		$shift_id  = $this->dated_shift( $type_id, [ $person_id ], $now->modify( '-1 day -2 hours' ) );
		update_post_meta( $shift_id, 'end_datetime', $now->modify( '-1 day' )->format( 'Y-m-d H:i:s' ) );
		update_post_meta( $shift_id, 'status', 'voltooid' );
		$scheduler = new ShiftEmailScheduler();

		$this->assertSame( 1, $scheduler->run_sweep( $now ) );
		$this->assertCount( 1, $this->sent_mail );
		$this->assertSame( 'Hoe ging Bardienst?', $this->sent_mail[0]['subject'] );
		$this->assertStringContainsString( 'https://docs.google.com/forms/d/test-form', $this->sent_mail[0]['message'] );
		$this->assertSame( 0, $scheduler->run_sweep( $now ) );
		$this->assertCount( 1, $this->sent_mail );
	}

	public function test_early_shift_cancellation_notifies_assignees_without_credit(): void {
		$manager_id = $this->createRondoUser( [ 'role' => 'rondo_vrijwilligers' ] );
		wp_set_current_user( $manager_id );
		$type_id   = $this->dienst_type();
		$person_id = $this->mail_person( 'Anne', 'anne@example.com' );
		$shift_id  = $this->dated_shift( $type_id, [ $person_id ], current_datetime()->modify( '+3 days' ) );

		$response = $this->cancel_shift( $shift_id, 'Geen wedstrijden op deze datum.' );
		$data     = $response->get_data();

		$this->assertSame( 'geannuleerd', get_post_meta( $shift_id, 'status', true ) );
		$this->assertSame( [ $person_id ], get_post_meta( $shift_id, 'assigned_persons', true ) );
		$this->assertSame( ShiftCancellationService::VARIANT_EARLY, $data['variant'] );
		$this->assertFalse( $data['credit_awarded'] );
		$this->assertSame( 1, $data['notifications']['sent'] );
		$this->assertCount( 1, $this->sent_mail );
		$this->assertStringContainsString( 'telt daarom niet mee', $this->sent_mail[0]['message'] );
		$this->assertStringContainsString( 'Geen wedstrijden op deze datum.', $this->sent_mail[0]['message'] );
	}

	public function test_last_minute_cancellation_awards_credit_and_is_idempotent(): void {
		$manager_id = $this->createRondoUser( [ 'role' => 'rondo_vrijwilligers' ] );
		wp_set_current_user( $manager_id );
		$type_id   = $this->dienst_type();
		$person_id = $this->mail_person( 'Anne', 'anne@example.com' );
		$shift_id  = $this->dated_shift( $type_id, [ $person_id ], current_datetime()->modify( '+24 hours' ) );

		$first  = $this->cancel_shift( $shift_id, 'Velden afgekeurd.' )->get_data();
		$second = $this->cancel_shift( $shift_id )->get_data();

		$this->assertSame( ShiftCancellationService::VARIANT_LAST_MINUTE, $first['variant'] );
		$this->assertTrue( $first['credit_awarded'] );
		$this->assertStringContainsString( 'telt daarom wel mee', $this->sent_mail[0]['message'] );
		$this->assertTrue( $second['already_cancelled'] );
		$this->assertSame( 0, $second['notifications']['sent'] );
		$this->assertSame( 1, $second['notifications']['already_sent'] );
		$this->assertCount( 1, $this->sent_mail );
	}

	public function test_cancellation_uses_the_configured_template_for_each_variant(): void {
		$manager_id = $this->createRondoUser( [ 'role' => 'rondo_vrijwilligers' ] );
		wp_set_current_user( $manager_id );
		$type_id = $this->dienst_type();
		update_post_meta( $type_id, 'cancellation_early_email_subject', 'Nieuwe dienst nodig: {dienst} op {datum}' );
		update_post_meta( $type_id, 'cancellation_early_email_body', 'Beste {naam}, de vroege annulering van {dienst} is van {tijd} tot {eindtijd}.' );
		update_post_meta( $type_id, 'cancellation_last_minute_email_subject', '{dienst} vervalt last minute' );
		update_post_meta( $type_id, 'cancellation_last_minute_email_body', 'Beste {naam}, deze late annulering telt wel mee.' );

		$early_person_id = $this->mail_person( 'Anne', 'anne@example.com' );
		$early_shift_id  = $this->dated_shift( $type_id, [ $early_person_id ], current_datetime()->modify( '+3 days' ) );
		$this->cancel_shift( $early_shift_id, 'Geen wedstrijden.' );

		$late_person_id = $this->mail_person( 'Piet', 'piet@example.com' );
		$late_shift_id  = $this->dated_shift( $type_id, [ $late_person_id ], current_datetime()->modify( '+24 hours' ) );
		$this->cancel_shift( $late_shift_id );

		$this->assertCount( 2, $this->sent_mail );
		$this->assertStringStartsWith( 'Nieuwe dienst nodig: Bardienst op ', $this->sent_mail[0]['subject'] );
		$this->assertStringNotContainsString( '{datum}', $this->sent_mail[0]['subject'] );
		$this->assertStringContainsString( 'Beste Anne, de vroege annulering van Bardienst is van', $this->sent_mail[0]['message'] );
		$this->assertStringContainsString( 'Reden: Geen wedstrijden.', $this->sent_mail[0]['message'] );
		$this->assertSame( 'Bardienst vervalt last minute', $this->sent_mail[1]['subject'] );
		$this->assertStringContainsString( 'Beste Piet, deze late annulering telt wel mee.', $this->sent_mail[1]['message'] );
	}

	public function test_exactly_forty_eight_hours_is_the_early_variant(): void {
		$now       = new \DateTimeImmutable( '2026-09-01 10:00:00', wp_timezone() );
		$type_id   = $this->dienst_type();
		$person_id = $this->mail_person( 'Anne', 'anne@example.com' );
		$shift_id  = $this->dated_shift( $type_id, [ $person_id ], $now->modify( '+48 hours' ) );
		$service   = new ShiftCancellationService();

		$result = $service->cancel( $shift_id, '', 1, $now );

		$this->assertIsArray( $result );
		$this->assertSame( ShiftCancellationService::VARIANT_EARLY, $result['variant'] );
		$this->assertFalse( $result['credit_awarded'] );
	}

	public function test_assigned_shift_cannot_be_hard_deleted(): void {
		[, $person_id] = $this->member();
		$shift_id      = $this->shift( [ $person_id ], 10 );
		new ShiftCancellationService();

		$this->assertFalse( wp_delete_post( $shift_id, true ) );
		$this->assertNotNull( get_post( $shift_id ) );
	}

	public function test_cancellation_reports_assignee_without_email(): void {
		$manager_id = $this->createRondoUser( [ 'role' => 'rondo_vrijwilligers' ] );
		wp_set_current_user( $manager_id );
		$type_id   = $this->dienst_type();
		$person_id = $this->createPerson( [ 'post_title' => 'Geen E-mail' ], [ 'first_name' => 'Piet' ] );
		$shift_id  = $this->dated_shift( $type_id, [ $person_id ], current_datetime()->modify( '+3 days' ) );

		$data = $this->cancel_shift( $shift_id )->get_data();

		$this->assertSame( 0, $data['notifications']['sent'] );
		$this->assertSame( 1, $data['notifications']['no_email'] );
		$this->assertSame( [ $person_id ], $data['notifications']['no_email_person_ids'] );
		$this->assertCount( 0, $this->sent_mail );
	}

	public function test_plain_member_cannot_cancel_entire_shift(): void {
		[, $person_id] = $this->member();
		$shift_id      = $this->shift( [ $person_id ], 10 );
		$request       = new WP_REST_Request( 'POST', "/rondo/v1/shifts/{$shift_id}/cancellation" );
		$request->set_param( 'id', $shift_id );

		$response = $this->server->dispatch( $request );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'open', get_post_meta( $shift_id, 'status', true ) );
	}

	private function dienst_type(): int {
		$type_id = self::factory()->post->create(
			[
				'post_type'   => 'dienst_type',
				'post_status' => 'publish',
				'post_title'  => 'Bardienst',
			]
		);
		update_post_meta( $type_id, 'reminder_email_subject', 'Denk aan {dienst} op {datum}' );
		update_post_meta( $type_id, 'reminder_email_body', 'Hoi {naam}, je werkt van {tijd} tot {eindtijd} met {medevrijwilligers}.' );
		update_post_meta( $type_id, 'survey_email_subject', 'Hoe ging {dienst}?' );
		update_post_meta( $type_id, 'survey_email_body', 'Hoi {naam}, bedankt voor je hulp.' );
		update_post_meta( $type_id, 'survey_url', 'https://docs.google.com/forms/d/test-form' );
		return $type_id;
	}

	private function mail_person( string $first_name, string $email ): int {
		return $this->createPerson(
			[ 'post_title' => $first_name . ' Test' ],
			[
				'first_name' => $first_name,
				'email_1'    => $email,
			]
		);
	}

	private function dated_shift( int $type_id, array $assigned, \DateTimeImmutable $start ): int {
		$shift_id = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
				'post_title'  => 'Bardienst test',
			]
		);
		update_post_meta( $shift_id, 'dienst_type_id', $type_id );
		update_post_meta( $shift_id, 'start_datetime', $start->format( 'Y-m-d H:i:s' ) );
		update_post_meta( $shift_id, 'end_datetime', $start->modify( '+2 hours' )->format( 'Y-m-d H:i:s' ) );
		update_post_meta( $shift_id, 'status', 'open' );
		update_post_meta( $shift_id, 'assigned_persons', $assigned );
		return $shift_id;
	}
}
