<?php

namespace Tests\Wpunit;

use Rondo\Core\AccessControl;
use Rondo\Fields\Fields;
use Rondo\REST\People;
use Rondo\Sponsors\Relations;
use Tests\Support\RondoTestCase;

/** Contract tests for membership passes on the personal household surface. */
class HouseholdMembershipPassTest extends RondoTestCase {

	private const TYPE_CHILD = 3;

	protected function set_up(): void {
		parent::set_up();
		AccessControl::flush_visible_person_ids_cache();
		Relations::flush_cache();
	}

	public function test_household_exposes_only_visible_eligible_passes_with_correct_labels(): void {
		$parent               = $this->createPerson(
			[ 'post_title' => 'Ouder Bondslid' ],
			[
				'first_name' => 'Ouder',
				'type-lid'   => 'Bondslid',
			]
		);
		$club_member          = $this->minorPerson(
			'Kind Verenigingslid',
			[ 'type-lid' => 'Verenigingslid' ]
		);
		$businessclub_sponsor = $this->minorPerson(
			'Kind Businessclub',
			[ 'person_type' => 'contact' ]
		);
		$dual_role_sponsor    = $this->minorPerson(
			'Kind Sponsor en Bondslid',
			[
				'person_type' => 'member',
				'type-lid'    => 'Bondslid',
			]
		);
		$ineligible           = $this->minorPerson(
			'Kind zonder pas',
			[ 'person_type' => 'contact' ]
		);
		$stranger             = $this->createPerson(
			[ 'post_title' => 'Onbekend Bondslid' ],
			[ 'type-lid' => 'Bondslid' ]
		);

		foreach ( [ $club_member, $businessclub_sponsor, $dual_role_sponsor, $ineligible ] as $child_id ) {
			$this->addChild( $parent, $child_id );
		}

		$this->createSponsor(
			'Businessclubbedrijf',
			'businessclub',
			$businessclub_sponsor
		);
		$this->createSponsor(
			'Sponsorbedrijf',
			'awc_sponsor',
			$dual_role_sponsor
		);

		$user_id = $this->createRondoUser( [ 'user_login' => 'household_membership_passes' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $parent );
		AccessControl::flush_visible_person_ids_cache();
		wp_set_current_user( $user_id );

		$response = ( new People() )->get_household();
		$people   = [];
		foreach ( $response->get_data() as $person ) {
			$people[ (int) $person['id'] ] = $person;
		}

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 5, $people );
		$this->assertArrayNotHasKey( $stranger, $people );
		$this->assertPass( $people[ $parent ]['membership_pass'], 'bondslid', 'Ledenpas' );
		$this->assertPass( $people[ $club_member ]['membership_pass'], 'verenigingslid', 'Ledenpas' );
		$this->assertPass( $people[ $businessclub_sponsor ]['membership_pass'], 'businessclub', 'Businessclubpas' );
		$this->assertPass( $people[ $dual_role_sponsor ]['membership_pass'], 'awc_sponsor', 'Sponsorpas' );
		$this->assertNull( $people[ $ineligible ]['membership_pass'] );
	}

	private function minorPerson( string $title, array $fields ): int {
		$fields['birthdate'] = gmdate( 'Ymd', strtotime( '-10 years' ) );
		return $this->createPerson( [ 'post_title' => $title ], $fields );
	}

	private function addChild( int $parent_id, int $child_id ): void {
		$relationships   = Fields::get_for_post( $parent_id, 'relationships' ) ?: [];
		$relationships[] = [
			'related_person'    => $child_id,
			'relationship_type' => self::TYPE_CHILD,
		];
		Fields::update_for_post( $parent_id, 'relationships', $relationships );
		AccessControl::flush_visible_person_ids_cache();
	}

	private function createSponsor( string $title, string $role, int $person_id ): void {
		$sponsor_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_sponsor',
				'post_status' => 'publish',
				'post_title'  => $title,
			]
		);
		Fields::update_for_post( $sponsor_id, 'sponsor_role', $role );
		Relations::set_contacts(
			$sponsor_id,
			[
				[
					'person_id'       => $person_id,
					'receives_pass'   => true,
					'is_primary_pass' => true,
				],
			]
		);
	}

	private function assertPass( array $pass, string $type, string $label ): void {
		$this->assertSame( [ 'url', 'type', 'label' ], array_keys( $pass ) );
		$this->assertStringContainsString( '/lidpas/', $pass['url'] );
		$this->assertSame( $type, $pass['type'] );
		$this->assertSame( $label, $pass['label'] );
	}
}
