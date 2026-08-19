<?php

namespace Tests\Wpunit;

use Rondo\Core\VolunteerStatus;
use Rondo\Fields\Fields;
use Rondo\REST\People;
use Rondo\VOG\VOGEmail;
use Rondo\VOG\VOGRequirement;
use Tests\Support\RondoTestCase;

/**
 * Covers role-specific VOG exemptions without changing volunteer status.
 */
class VogRequirementTest extends RondoTestCase {

	private People $controller;

	protected function set_up(): void {
		parent::set_up();
		delete_option( VOGEmail::OPTION_EXEMPT_ROLES );
		delete_option( VOGEmail::OPTION_EXEMPT_COMMISSIES );
		VOGRequirement::invalidate_cache();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->controller = new People();
	}

	protected function tear_down(): void {
		delete_option( VOGEmail::OPTION_EXEMPT_ROLES );
		delete_option( VOGEmail::OPTION_EXEMPT_COMMISSIES );
		VOGRequirement::invalidate_cache();
		parent::tear_down();
	}

	public function test_exempt_role_removes_vog_requirement_but_keeps_volunteer_status(): void {
		$commissie_id = $this->commissie( 'Verenigingsbreed' );
		$person_id    = $this->person_with_roles(
			'Wilma van As',
			[
				$this->role( $commissie_id, 'Omroepster' ),
			]
		);

		$this->assertTrue( VOGRequirement::is_required( $person_id ) );

		( new VOGEmail() )->update_exempt_roles( [ 'Omroepster' ] );
		( new VolunteerStatus() )->calculate_and_update_status( $person_id );

		$this->assertTrue( Fields::get_for_post( $person_id, 'huidig_vrijwilliger' ) );
		$this->assertFalse( VOGRequirement::is_required( $person_id ) );
	}

	public function test_another_active_role_can_still_require_a_vog(): void {
		( new VOGEmail() )->update_exempt_roles( [ 'Omroepster' ] );
		$commissie_id = $this->commissie( 'Verenigingsbreed' );
		$team_id      = self::factory()->post->create(
			[
				'post_type'   => 'team',
				'post_status' => 'publish',
				'post_title'  => 'AWC O13-1',
			]
		);
		$person_id    = $this->person_with_roles(
			'Vrijwilliger met twee functies',
			[
				$this->role( $commissie_id, 'Omroepster' ),
				$this->role( $team_id, 'Trainer', 'team' ),
			]
		);

		$this->assertTrue( VOGRequirement::is_required( $person_id ) );
	}

	public function test_people_filter_uses_role_specific_vog_requirement(): void {
		( new VOGEmail() )->update_exempt_roles( [ 'Omroepster' ] );
		$commissie_id = $this->commissie( 'Verenigingsbreed' );
		$exempt_id    = $this->person_with_roles( 'Vrijgestelde omroepster', [ $this->role( $commissie_id, 'Omroepster' ) ] );
		$required_id  = $this->person_with_roles( 'VOG-plichtig commissielid', [ $this->role( $commissie_id, 'Commissielid' ) ] );

		$request = new \WP_REST_Request( 'GET' );
		$request->set_param( 'page', 1 );
		$request->set_param( 'per_page', 100 );
		$request->set_param( 'ownership', 'all' );
		$request->set_param( 'orderby', 'first_name' );
		$request->set_param( 'order', 'asc' );
		$request->set_param( 'huidig_vrijwilliger', '1' );
		$request->set_param( 'vog_required', '1' );

		$data = $this->controller->get_filtered_people( $request )->get_data();
		$ids  = array_map( 'intval', array_column( $data['people'], 'id' ) );

		$this->assertNotContains( $exempt_id, $ids );
		$this->assertContains( $required_id, $ids );
	}

	private function commissie( string $title ): int {
		return self::factory()->post->create(
			[
				'post_type'   => 'commissie',
				'post_status' => 'publish',
				'post_title'  => $title,
			]
		);
	}

	private function person_with_roles( string $title, array $roles ): int {
		$person_id = $this->createPerson( [ 'post_title' => $title ] );
		Fields::update_for_post( $person_id, 'work_history', $roles );
		( new VolunteerStatus() )->calculate_and_update_status( $person_id );
		VOGRequirement::invalidate_cache();
		return $person_id;
	}

	private function role( int $team_id, string $job_title, string $entity_type = 'commissie' ): array {
		return [
			'team'        => $team_id,
			'entity_type' => $entity_type,
			'job_title'   => $job_title,
			'start_date'  => '2020-01-01',
			'end_date'    => '',
			'is_current'  => true,
		];
	}
}
