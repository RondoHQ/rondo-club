<?php

namespace Tests\Wpunit;

use Rondo\Core\AutoTitle;
use Tests\Support\RondoTestCase;

class CalendarRematchSchedulingTest extends RondoTestCase {

	private int $user_id;

	protected function set_up(): void {
		parent::set_up();
		$this->user_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->user_id );
		wp_clear_scheduled_hook( 'rondo_async_calendar_rematch_user', [ $this->user_id ] );
	}

	protected function tear_down(): void {
		wp_clear_scheduled_hook( 'rondo_async_calendar_rematch_user', [ $this->user_id ] );
		parent::tear_down();
	}

	public function test_email_change_schedules_one_debounced_job_per_owner(): void {
		$first_person  = $this->createPerson( [ 'post_author' => $this->user_id ] );
		$second_person = $this->createPerson( [ 'post_author' => $this->user_id ] );
		$auto_title    = new AutoTitle();

		$auto_title->track_calendar_email_change( 'first@example.com', $first_person, [], '' );
		$auto_title->trigger_calendar_rematch( $first_person );
		$first_timestamp = wp_next_scheduled( 'rondo_async_calendar_rematch_user', [ $this->user_id ] );

		$this->assertIsInt( $first_timestamp );
		$this->assertGreaterThanOrEqual( time() + 55, $first_timestamp );

		$auto_title->track_calendar_email_change( 'second@example.com', $second_person, [], '' );
		$auto_title->trigger_calendar_rematch( $second_person );

		$this->assertSame(
			$first_timestamp,
			wp_next_scheduled( 'rondo_async_calendar_rematch_user', [ $this->user_id ] )
		);
	}

	public function test_unchanged_email_does_not_schedule_rematch(): void {
		$person_id  = $this->createPerson( [ 'post_author' => $this->user_id ] );
		$auto_title = new AutoTitle();

		$auto_title->track_calendar_email_change( 'same@example.com', $person_id, [], 'SAME@example.com' );
		$auto_title->trigger_calendar_rematch( $person_id );

		$this->assertFalse( wp_next_scheduled( 'rondo_async_calendar_rematch_user', [ $this->user_id ] ) );
	}
}
