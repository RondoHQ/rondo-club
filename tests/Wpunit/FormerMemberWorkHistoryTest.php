<?php

namespace Tests\Wpunit;

use Rondo\Data\FormerMemberWorkHistory;
use Rondo\Fields\Fields;
use Tests\Support\RondoTestCase;

class FormerMemberWorkHistoryTest extends RondoTestCase {

	private FormerMemberWorkHistory $service;

	protected function set_up(): void {
		parent::set_up();

		new \RONDO_Post_Types();
		$this->service = new FormerMemberWorkHistory();
	}

	protected function tear_down(): void {
		remove_action( 'rondo_fields_saved_post', [ $this->service, 'close_current_positions' ], 15 );

		parent::tear_down();
	}

	public function test_becoming_former_member_closes_current_positions_on_membership_end_date(): void {
		$person_id = $this->createPerson();
		Fields::update_for_post( $person_id, 'lid_tot', '2026-06-30' );
		Fields::update_for_post(
			$person_id,
			'work_history',
			[
				[
					'job_title'  => 'Jeugdbegeleid(st)er',
					'is_current' => true,
					'start_date' => '2023-11-29',
					'end_date'   => '',
				],
				[
					'job_title'  => 'Historische rol',
					'is_current' => false,
					'start_date' => '2019-01-01',
					'end_date'   => '2020-01-01',
				],
			]
		);

		$this->assertTrue( Fields::update_for_post( $person_id, 'former_member', true ) );

		$history = Fields::get_for_post( $person_id, 'work_history' );
		$this->assertFalse( $history[0]['is_current'] );
		$this->assertSame( '20260630', $history[0]['end_date'] );
		$this->assertFalse( $history[1]['is_current'] );
		$this->assertSame( '20200101', $history[1]['end_date'] );
	}

	public function test_combined_save_closes_work_history_after_complete_payload_is_written(): void {
		$person_id = $this->createPerson();

		$result = Fields::update_many_for_post(
			$person_id,
			[
				'former_member' => true,
				'work_history'  => [
					[
						'job_title'  => 'Kaderlid Algemeen',
						'is_current' => true,
						'start_date' => '2023-11-29',
						'end_date'   => '',
					],
				],
			]
		);

		$this->assertTrue( $result );
		$history = Fields::get_for_post( $person_id, 'work_history' );
		$this->assertFalse( $history[0]['is_current'] );
		$this->assertSame( current_datetime()->format( 'Ymd' ), $history[0]['end_date'] );
	}

	public function test_later_work_history_write_cannot_restore_current_position_for_former_member(): void {
		$person_id = $this->createPerson();
		update_post_meta( $person_id, 'former_member', '1' );

		$this->assertTrue(
			Fields::update_for_post(
				$person_id,
				'work_history',
				[
					[
						'job_title'  => 'Trainer',
						'is_current' => true,
						'start_date' => '2026-01-01',
						'end_date'   => '',
					],
				]
			)
		);

		$history = Fields::get_for_post( $person_id, 'work_history' );
		$this->assertFalse( $history[0]['is_current'] );
		$this->assertSame( current_datetime()->format( 'Ymd' ), $history[0]['end_date'] );
	}

	public function test_reactivation_keeps_current_work_history_current(): void {
		$person_id = $this->createPerson();
		update_post_meta( $person_id, 'former_member', '1' );

		$result = Fields::update_many_for_post(
			$person_id,
			[
				'former_member' => false,
				'work_history'  => [
					[
						'job_title'  => 'Trainer',
						'is_current' => true,
						'start_date' => '2026-08-01',
						'end_date'   => '',
					],
				],
			]
		);

		$this->assertTrue( $result );
		$history = Fields::get_for_post( $person_id, 'work_history' );
		$this->assertTrue( $history[0]['is_current'] );
		$this->assertSame( '', $history[0]['end_date'] );
	}
}
