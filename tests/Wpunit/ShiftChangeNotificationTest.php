<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\REST\MemberShifts;
use Rondo\Volunteer\ShiftChangeNotification;
use Tests\Support\RondoTestCase;

class ShiftChangeNotificationTest extends RondoTestCase {
	private int $shift_id;
	private int $person_id;
	private array $mail     = [];
	private bool $fail_mail = false;
	private array $original;

	protected function set_up(): void {
		parent::set_up();
		new ShiftChangeNotification();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->bootRestControllers( [ MemberShifts::class ] );
		$this->person_id = $this->createPerson(
			[],
			[
				'first_name' => 'Jan',
				'email_1'    => 'jan@example.com',
			]
			);
		$type            = self::factory()->post->create(
			[
				'post_type'   => 'dienst_type',
				'post_status' => 'publish',
				'post_title'  => 'Kantine & bar',
			]
			);
		$this->shift_id  = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
			]
			);
		$start           = current_datetime()->modify( '+10 days' )->setTime( 10, 0 );
		$this->original  = [
			'dienst_type_id'   => $type,
			'start_datetime'   => $start->format( 'Y-m-d H:i:s' ),
			'end_datetime'     => $start->modify( '+2 hours' )->format( 'Y-m-d H:i:s' ),
			'notes'            => 'Oude uitleg',
			'status'           => 'open',
			'assigned_persons' => [ $this->person_id ],
		];
		Fields::update_many_for_post( $this->shift_id, $this->original );
		add_filter(
			'pre_wp_mail',
			function ( $result, $args ) {
				$this->mail[] = $args;
				return ! $this->fail_mail;
			},
			10,
			2
			);
	}

	private function events(): array {
		$events = [];
		foreach ( _get_cron_array() as $hooks ) {
			foreach ( $hooks[ ShiftChangeNotification::HOOK ] ?? [] as $event ) {
				if ( $event['args'][0] === $this->shift_id ) {
					$events[] = $event['args'];
				}
			}
		}
		return $events;
	}

	private function deliver( array $args ): void {
		wp_clear_scheduled_hook( ShiftChangeNotification::HOOK, $args );
		do_action( 'rondo_send_shift_change_notification', ...$args );
	}

	public function test_rest_update_sends_one_mail_with_old_and_new_details_and_escaped_notes(): void {
		$this->assertSame( [], $this->events(), 'Creating a shift must not send a change notice.' );
		$request   = new \WP_REST_Request( 'POST', '/wp/v2/dienst-shifts/' . $this->shift_id );
		$new_start = ( new \DateTimeImmutable( $this->original['start_datetime'], wp_timezone() ) )->modify( '+1 day' );
		$request->set_body_params(
			[
				'fields' => [
					'start_datetime' => $new_start->format( DATE_RFC3339 ),
					'end_datetime'   => $new_start->modify( '+3 hours' )->format( DATE_RFC3339 ),
					'notes'          => 'Nieuwe uitleg & instructies',
				],
			]
			);
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$this->assertCount( 1, $this->events() );
		$this->assertSame( [], $this->mail, 'Sending happens after saving, through cron.' );
		$args = $this->events()[0];
		$this->deliver( $args );
		$this->assertCount( 1, $this->mail );
		$this->assertSame( [ 'jan@example.com' ], $this->mail[0]['to'] );
		$this->assertStringContainsString( 'Kantine & bar', $this->mail[0]['subject'] );
		$this->assertStringContainsString( 'Oude uitleg', $this->mail[0]['message'] );
		$this->assertStringContainsString( 'Nieuwe uitleg &amp; instructies', $this->mail[0]['message'] );
		$this->assertStringContainsString( $new_start->format( 'd-m-Y' ), $this->mail[0]['message'] );
		$this->assertStringContainsString( '13:00', $this->mail[0]['message'] );
		$this->deliver( $args );
		$this->assertCount( 1, $this->mail, 'An already delivered event must not send twice.' );
		rest_do_request( $request );
		$this->assertSame( [], $this->events(), 'Saving the same values is not another change.' );
	}

	public function test_capacity_invalid_and_unauthorized_updates_do_not_queue_mail(): void {
		Fields::update_for_post( $this->shift_id, 'capacity', 8 );
		$request = new \WP_REST_Request( 'POST', '/wp/v2/dienst-shifts/' . $this->shift_id );
		$request->set_body_params(
			[
				'fields' => [
					'notes'          => 'Must not save',
					'start_datetime' => 'invalid',
				],
			]
			);
		$this->assertSame( 400, rest_do_request( $request )->get_status() );
		wp_set_current_user( 0 );
		$request->set_body_params( [ 'fields' => [ 'notes' => 'Unauthorized' ] ] );
		$this->assertSame( 401, rest_do_request( $request )->get_status() );
		$this->assertSame( [], $this->events() );
	}

	public function test_failed_recipient_retries_without_resending_successes(): void {
		$second = $this->createPerson(
			[],
			[
				'first_name' => 'Piet',
				'email_1'    => 'piet@example.com',
			]
			);
		Fields::update_for_post( $this->shift_id, 'assigned_persons', [ $this->person_id, $second ] );
		$fail_second = static fn( $result, $args ) => in_array( 'piet@example.com', (array) $args['to'], true ) ? false : $result;
		add_filter( 'pre_wp_mail', $fail_second, 20, 2 );
		Fields::update_for_post( $this->shift_id, 'notes', 'Gewijzigd' );
		$args = $this->events()[0];
		$this->deliver( $args );
		$this->assertCount( 2, $this->mail );
		$this->assertCount( 1, $this->events(), 'Failure schedules a retry.' );
		remove_filter( 'pre_wp_mail', $fail_second, 20 );
		$this->deliver( $args );
		$this->assertCount( 3, $this->mail );
		$this->assertSame( [ 'piet@example.com' ], $this->mail[2]['to'] );
		$this->assertSame( [], $this->events() );
	}

	public function test_superseded_cancelled_and_removed_assignments_do_not_send_stale_mail(): void {
		Fields::update_for_post( $this->shift_id, 'notes', 'Eerste wijziging' );
		$first = $this->events()[0];
		Fields::update_for_post( $this->shift_id, 'notes', 'Laatste wijziging' );
		$this->deliver( $first );
		$this->assertSame( [], $this->mail );
		$last = $this->events()[0];
		update_post_meta( $this->shift_id, 'status', 'geannuleerd' );
		$this->deliver( $last );
		$this->assertSame( [], $this->mail );
		update_post_meta( $this->shift_id, 'status', 'open' );
		Fields::update_for_post( $this->shift_id, 'notes', 'Opnieuw gewijzigd' );
		$args = $this->events()[0];
		update_post_meta( $this->shift_id, 'assigned_persons', [] );
		$this->deliver( $args );
		$this->assertSame( [], $this->mail );
	}

	public function test_past_completed_empty_and_draft_shifts_do_not_queue_mail(): void {
		foreach ( [ 'past', 'completed', 'empty', 'draft' ] as $case ) {
			foreach ( $this->original as $key => $value ) {
				update_post_meta( $this->shift_id, $key, $value );
			}
			if ( $case === 'past' ) {
				update_post_meta( $this->shift_id, 'start_datetime', '2020-01-01 10:00:00' );
			}
			if ( $case === 'completed' ) {
				update_post_meta( $this->shift_id, 'status', 'voltooid' );
			}
			if ( $case === 'empty' ) {
				update_post_meta( $this->shift_id, 'assigned_persons', [] );
			}
			if ( $case === 'draft' ) {
				wp_update_post(
					[
						'ID'          => $this->shift_id,
						'post_status' => 'draft',
					]
					);
			}
			Fields::update_for_post( $this->shift_id, 'notes', 'Geen melding voor ' . $case );
			$this->assertSame( [], $this->events(), $case );
		}
	}
}
