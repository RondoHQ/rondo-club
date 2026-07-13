<?php

namespace Tests\Wpunit;

use Rondo\REST\MemberShifts;
use Rondo\Volunteer\ShiftCancellationService;
use Rondo\Volunteer\ShiftEmailScheduler;
use Tests\Support\RondoTestCase;
use WP_REST_Request;
use WP_REST_Server;

/**
 * Regression coverage for member cancellation and scheduled shift emails.
 */
class MemberShiftLifecycleTest extends RondoTestCase {

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
		$start    = current_datetime()->modify( '+' . $days_until_start . ' days' );
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
		$this->assertNotEmpty( get_post_meta( $shift_id, '_shift_email_reminder_14_sent_' . $person_id, true ) );

		$this->assertSame( 0, $scheduler->run_sweep( $now ) );
		$this->assertSame( 1, $scheduler->run_sweep( $now->modify( '+7 days' ) ) );
		$this->assertSame( 1, $scheduler->run_sweep( $now->modify( '+12 days' ) ) );
		$this->assertCount( 3, $this->sent_mail );
		$this->assertNotEmpty( get_post_meta( $shift_id, '_shift_email_reminder_7_sent_' . $person_id, true ) );
		$this->assertNotEmpty( get_post_meta( $shift_id, '_shift_email_reminder_2_sent_' . $person_id, true ) );
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
