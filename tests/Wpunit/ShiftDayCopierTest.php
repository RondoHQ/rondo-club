<?php

namespace Tests\Wpunit;

use Rondo\Volunteer\ShiftDayCopier;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/**
 * Regression coverage for copying a complete volunteer planning day.
 */
class ShiftDayCopierTest extends RondoTestCase {

	private ShiftDayCopier $copier;

	protected function set_up(): void {
		parent::set_up();
		update_option( 'timezone_string', 'Europe/Amsterdam' );
		$this->copier = new ShiftDayCopier();
	}

	private function create_type( string $title ): int {
		return self::factory()->post->create(
			[
				'post_type'   => 'dienst_type',
				'post_status' => 'publish',
				'post_title'  => $title,
			]
		);
	}

	private function create_shift( int $type_id, string $date, string $start, string $end, array $meta = [] ): int {
		$shift_id = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
				'post_title'  => 'Bronplanning',
			]
		);

		$defaults = [
			'dienst_type_id'   => $type_id,
			'start_datetime'   => $date . ' ' . $start,
			'end_datetime'     => $date . ' ' . $end,
			'capacity'         => 1,
			'status'           => 'open',
			'assigned_persons' => [],
			'iva_waived'       => 0,
			'notes'            => '',
		];
		foreach ( array_merge( $defaults, $meta ) as $key => $value ) {
			update_post_meta( $shift_id, $key, $value );
		}

		return $shift_id;
	}

	private function copy_request( string $source_date, string $target_date ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/rondo/v1/shifts/copy-day' );
		$request->set_param( 'source_date', $source_date );
		$request->set_param( 'target_date', $target_date );
		return $request;
	}

	private function future_dst_dates(): array {
		$year         = (int) current_datetime()->format( 'Y' ) + 1;
		$october_last = new \DateTimeImmutable( $year . '-10-31 12:00:00', wp_timezone() );
		$target       = $october_last->modify( '-' . $october_last->format( 'w' ) . ' days' );
		$source       = $target->modify( '-49 days' );
		return [ $source->format( 'Y-m-d' ), $target->format( 'Y-m-d' ) ];
	}

	public function test_copies_active_shifts_as_clean_standalone_rows_across_dst(): void {
		wp_set_current_user( $this->createRondoUser( [ 'role' => 'rondo_vrijwilligers' ] ) );
		[ $source_date, $target_date ] = $this->future_dst_dates();
		$kitchen_type                  = $this->create_type( 'Kantine — keuken' );
		$bar_type                      = $this->create_type( 'Kantine — bar' );
		$person_id                     = $this->createPerson( [ 'post_title' => 'Ingeschreven vrijwilliger' ] );
		$template_id                   = self::factory()->post->create(
			[
				'post_type'   => 'shift_template',
				'post_status' => 'publish',
				'post_title'  => 'Zondagsjabloon',
			]
		);

		$kitchen_shift = $this->create_shift(
			$kitchen_type,
			$source_date,
			'11:30:00',
			'14:30:00',
			[
				'capacity'         => 2,
				'status'           => 'vol',
				'assigned_persons' => [ $person_id ],
				'iva_waived'       => 1,
				'notes'            => 'Neem de sleutel mee.',
				'template_id'      => $template_id,
			]
		);
		$this->create_shift( $bar_type, $source_date, '17:30', '20:30', [ 'capacity' => 1 ] );
		$this->create_shift( $bar_type, $source_date, '09:00:00', '10:00:00', [ 'status' => 'geannuleerd' ] );

		$response = $this->copier->rest_copy_day( $this->copy_request( $source_date, $target_date ) );
		$data     = $response->get_data();

		$this->assertSame( 2, $data['source_count'] );
		$this->assertSame( 2, $data['created'] );
		$this->assertSame( 0, $data['skipped'] );
		$this->assertCount( 2, $data['created_ids'] );

		$copied_kitchen = (int) $data['created_ids'][0];
		$this->assertSame( $target_date . ' 11:30:00', get_post_meta( $copied_kitchen, 'start_datetime', true ) );
		$this->assertSame( $target_date . ' 14:30:00', get_post_meta( $copied_kitchen, 'end_datetime', true ) );
		$this->assertSame( 2, (int) get_post_meta( $copied_kitchen, 'capacity', true ) );
		$this->assertSame( 'open', get_post_meta( $copied_kitchen, 'status', true ) );
		$this->assertSame( [], get_post_meta( $copied_kitchen, 'assigned_persons', true ) );
		$this->assertSame( '', get_post_meta( $copied_kitchen, 'template_id', true ) );
		$this->assertSame( '1', get_post_meta( $copied_kitchen, 'iva_waived', true ) );
		$this->assertSame( 'Neem de sleutel mee.', get_post_meta( $copied_kitchen, 'notes', true ) );
		$this->assertSame( $kitchen_shift, (int) get_post_meta( $copied_kitchen, '_copied_from_shift_id', true ) );
		$this->assertStringContainsString( \DateTimeImmutable::createFromFormat( '!Y-m-d', $target_date )->format( 'd-m-Y' ), get_the_title( $copied_kitchen ) );

		$source_time = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $source_date . ' 11:30:00', wp_timezone() );
		$target_time = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $target_date . ' 11:30:00', wp_timezone() );
		$this->assertNotSame( $source_time->getOffset(), $target_time->getOffset(), 'The test dates must cross the Amsterdam DST boundary.' );

		$repeat_data = $this->copier->rest_copy_day( $this->copy_request( $source_date, $target_date ) )->get_data();
		$this->assertSame( 0, $repeat_data['created'] );
		$this->assertSame( 2, $repeat_data['skipped'] );
	}

	public function test_existing_target_rows_are_skipped_without_blocking_missing_rows(): void {
		wp_set_current_user( $this->createRondoUser( [ 'role' => 'rondo_vrijwilligers' ] ) );
		$source_date = current_datetime()->modify( '+10 days' )->format( 'Y-m-d' );
		$target_date = current_datetime()->modify( '+20 days' )->format( 'Y-m-d' );
		$type_id     = $this->create_type( 'Club host' );

		$this->create_shift( $type_id, $source_date, '10:00:00', '12:00:00' );
		$this->create_shift( $type_id, $source_date, '13:00:00', '15:00:00' );
		$existing_id = $this->create_shift( $type_id, $target_date, '10:00:00', '12:00:00', [ 'capacity' => 4 ] );

		$data = $this->copier->rest_copy_day( $this->copy_request( $source_date, $target_date ) )->get_data();

		$this->assertSame( 1, $data['created'] );
		$this->assertSame( 1, $data['skipped'] );
		$this->assertSame( 4, (int) get_post_meta( $existing_id, 'capacity', true ), 'Existing target shifts must never be overwritten.' );
		$this->assertSame( $target_date . ' 13:00:00', get_post_meta( $data['created_ids'][0], 'start_datetime', true ) );
	}

	public function test_rejects_invalid_same_past_and_empty_dates(): void {
		$invalid = $this->copier->rest_copy_day( $this->copy_request( '2026-02-30', '2026-03-01' ) );
		$this->assertWPError( $invalid );
		$this->assertSame( 'rondo_invalid_shift_copy_date', $invalid->get_error_code() );

		$future = current_datetime()->modify( '+10 days' )->format( 'Y-m-d' );
		$same   = $this->copier->rest_copy_day( $this->copy_request( $future, $future ) );
		$this->assertWPError( $same );
		$this->assertSame( 'rondo_shift_copy_same_date', $same->get_error_code() );

		$past = $this->copier->rest_copy_day(
			$this->copy_request(
				current_datetime()->modify( '-2 days' )->format( 'Y-m-d' ),
				current_datetime()->modify( '-1 day' )->format( 'Y-m-d' )
			)
		);
		$this->assertWPError( $past );
		$this->assertSame( 'rondo_shift_copy_date_in_past', $past->get_error_code() );

		$empty = $this->copier->rest_copy_day(
			$this->copy_request(
				current_datetime()->modify( '+30 days' )->format( 'Y-m-d' ),
				current_datetime()->modify( '+31 days' )->format( 'Y-m-d' )
			)
		);
		$this->assertWPError( $empty );
		$this->assertSame( 'rondo_shift_copy_source_empty', $empty->get_error_code() );
	}

	public function test_copy_permission_is_limited_to_volunteer_managers(): void {
		wp_set_current_user( $this->createRondoUser() );
		$this->assertFalse( $this->copier->can_copy_day() );

		wp_set_current_user( $this->createRondoUser( [ 'role' => 'rondo_vrijwilligers' ] ) );
		$this->assertTrue( $this->copier->can_copy_day() );

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertTrue( $this->copier->can_copy_day() );
	}
}
