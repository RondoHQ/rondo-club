<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\Passes\PublicMembershipPassPage;
use Rondo\REST\Sponsors;
use Rondo\Sponsors\Migration;
use Rondo\Sponsors\Relations;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/** Contract tests for sponsor companies, relations and migration. */
class SponsorCompaniesTest extends RondoTestCase {
	private \WP_REST_Server $server;

	protected function set_up(): void {
		parent::set_up();
		Relations::flush_cache();
		$this->server = $this->bootRestControllers( [ Sponsors::class ] );
		$user_id      = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		get_user_by( 'id', $user_id )->add_cap( 'sponsorbeheer' );
		wp_set_current_user( $user_id );
	}

	public function test_manager_creates_company_with_contact_and_pass_right(): void {
		$person_id = $this->createPerson(
			[ 'post_title' => 'Jan Jansen' ],
			[
				'person_type' => 'contact',
				'first_name'  => 'Jan',
				'last_name'   => 'Jansen',
			]
		);
		$response  = $this->json_request(
			'POST',
			'/rondo/v1/sponsors',
			[
				'title'  => 'Voorbeeld BV',
				'status' => 'publish',
				'fields' => [
					'sponsor_role'       => 'businessclub',
					'sponsit_contact_id' => '400',
					'website'            => 'https://www.example.test/sponsors',
					'contacts'           => [
						[
							'person_id'         => $person_id,
							'contact_role'      => 'Contactpersoon',
							'is_primary'        => true,
							'receives_pass'     => true,
							'is_primary_pass'   => true,
							'sponsit_person_id' => '900',
						],
					],
				],
			]
		);

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'rondo_sponsor', get_post_type( $response->get_data()['id'] ) );
		$this->assertTrue( Relations::is_sponsor_contact( $person_id ) );
		$this->assertSame( 'businessclub', PublicMembershipPassPage::get_sponsor_pass_variant( $person_id ) );
		$this->assertSame( 'Voorbeeld BV', PublicMembershipPassPage::get_sponsor_company_name( $person_id ) );
		$this->assertSame( 'https://www.example.test/sponsors', $response->get_data()['fields']['website'] );
	}

	public function test_logo_upload_rejects_invalid_sponsit_source_id_before_writing_media(): void {
		$sponsor_id = $this->createSponsor( 'Logo BV', 'awc_sponsor', [] );
		$request    = new WP_REST_Request( 'POST', '/rondo/v1/sponsors/' . $sponsor_id . '/logo/upload' );
		$request->set_param( 'sponsit_logo_id', 'logo-123' );
		$response = $this->server->dispatch( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rondo_sponsor_logo_source_invalid', $response->get_data()['code'] );
		$this->assertSame( 0, get_post_thumbnail_id( $sponsor_id ) );
	}

	public function test_manager_creates_personal_sponsor_with_one_linked_person(): void {
		$person_id = $this->createPerson(
			[ 'post_title' => 'Piet Sponsor' ],
			[
				'person_type' => 'contact',
				'first_name'  => 'Piet',
				'last_name'   => 'Sponsor',
			]
		);
		$response  = $this->json_request(
			'POST',
			'/rondo/v1/sponsors',
			[
				'title'  => 'Piet Sponsor',
				'fields' => [
					'sponsor_type'       => 'person',
					'sponsor_role'       => 'awc_sponsor',
					'sponsit_contact_id' => 'personal-400',
					'contacts'           => [
						[
							'person_id'         => $person_id,
							'contact_role'      => 'Sponsor',
							'is_primary'        => true,
							'receives_pass'     => true,
							'is_primary_pass'   => true,
							'sponsit_person_id' => 'contact:400',
						],
					],
				],
			]
		);

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( 'person', $response->get_data()['fields']['sponsor_type'] );
		$this->assertSame( 'Sponsor', $response->get_data()['fields']['contacts'][0]['contact_role'] );
		$this->assertTrue( Relations::is_sponsor_contact( $person_id ) );
	}

	public function test_personal_sponsor_contact_endpoint_creates_person_and_relation_together(): void {
		$sponsor  = $this->json_request(
			'POST',
			'/rondo/v1/sponsors',
			[
				'title'  => 'Nieuwe Sponsor',
				'fields' => [
					'sponsor_type' => 'person',
					'sponsor_role' => 'awc_sponsor',
				],
			]
		);
		$response = $this->json_request(
			'POST',
			'/rondo/v1/sponsors/' . $sponsor->get_data()['id'] . '/contacts',
			[
				'first_name'        => 'Nieuwe',
				'last_name'         => 'Sponsor',
				'email'             => 'nieuw@example.test',
				'birthdate'         => '1980-01-02',
				'contact_role'      => 'Sponsor',
				'is_primary'        => true,
				'receives_pass'     => true,
				'is_primary_pass'   => true,
				'sponsit_person_id' => 'contact:401',
			]
		);

		$this->assertSame( 201, $response->get_status() );
		$contact = $response->get_data()['fields']['contacts'][0];
		$this->assertSame( 'contact:401', $contact['sponsit_person_id'] );
		$this->assertSame( '19800102', Fields::get_for_post( $contact['person_id'], 'birthdate' ) );
	}

	public function test_company_source_id_and_primary_contact_are_unique(): void {
		$person_one = $this->createPerson( [ 'post_title' => 'Een' ] );
		$person_two = $this->createPerson( [ 'post_title' => 'Twee' ] );
		$first      = $this->json_request(
			'POST',
			'/rondo/v1/sponsors',
			[
				'title'  => 'Eerste BV',
				'fields' => [
					'sponsor_role'       => 'awc_sponsor',
					'sponsit_contact_id' => 'same',
				],
			]
		);
		$this->assertSame( 201, $first->get_status() );

		$duplicate_source = $this->json_request(
			'POST',
			'/rondo/v1/sponsors',
			[
				'title'  => 'Tweede BV',
				'fields' => [
					'sponsor_role'       => 'awc_sponsor',
					'sponsit_contact_id' => 'same',
				],
			]
		);
		$this->assertSame( 409, $duplicate_source->get_status() );

		$duplicate_primary = $this->json_request(
			'PATCH',
			'/rondo/v1/sponsors/' . $first->get_data()['id'],
			[
				'fields' => [
					'contacts' => [
						[
							'person_id'  => $person_one,
							'is_primary' => true,
						],
						[
							'person_id'  => $person_two,
							'is_primary' => true,
						],
					],
				],
			]
		);
		$this->assertSame( 400, $duplicate_primary->get_status() );
	}

	public function test_archiving_company_disables_pass_but_keeps_person(): void {
		$person_id  = $this->createPerson( [ 'post_title' => 'Blijvende persoon' ] );
		$sponsor_id = $this->createSponsor(
			'Archief BV',
			'businessclub',
			[
				[
					'person_id'     => $person_id,
					'is_primary'    => true,
					'receives_pass' => true,
				],
			]
		);

		$response = $this->json_request( 'DELETE', '/rondo/v1/sponsors/' . $sponsor_id );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'draft', get_post_status( $sponsor_id ) );
		$this->assertNotNull( get_post( $person_id ) );
		$this->assertSame( '', PublicMembershipPassPage::get_sponsor_pass_variant( $person_id ) );
	}

	public function test_migration_groups_people_and_is_idempotent(): void {
		$first     = $this->createPerson(
			[ 'post_title' => 'Jan Jansen' ],
			[
				'first_name'           => 'Jan',
				'last_name'            => 'Jansen',
				'company_name'         => 'Voorbeeld B.V.',
				'is_sponsor'           => true,
				'sponsor_pass_variant' => 'businessclub',
				'sponsit_contact_id'   => '400',
				'sponsit_person_id'    => '900',
				'addresses'            => [
					[
						'address_label' => 'Home',
						'street_name'   => 'Privéstraat',
						'house_number'  => '1',
					],
					[
						'address_label' => 'Hoofdadres',
						'street_name'   => 'Bedrijfsweg',
						'house_number'  => '12',
						'city'          => 'Wijchen',
					],
				],
			]
		);
		$second    = $this->createPerson(
			[ 'post_title' => 'Piet Pieters' ],
			[
				'first_name'           => 'Piet',
				'last_name'            => 'Pieters',
				'company_name'         => 'Voorbeeld B.V.',
				'is_sponsor'           => true,
				'sponsor_pass_variant' => 'businessclub',
				'sponsit_contact_id'   => '400',
				'sponsit_person_id'    => '901',
			]
		);
		$migration = new Migration();
		$plan      = $migration->plan();

		$this->assertSame( 1, $plan['summary']['groups'] );
		$this->assertSame( 'ready', $plan['groups'][0]['decision'] );
		$this->assertSame( 'Bedrijfsweg', $plan['groups'][0]['address']['address_street_name'] );
		$this->assertSame( [ $first, $second ], $plan['groups'][0]['person_ids'] );

		$first_apply  = $migration->apply();
		$second_apply = $migration->apply();
		$this->assertSame( 1, $first_apply['created'] );
		$this->assertSame( 0, $second_apply['created'] );
		$this->assertSame( 1, $second_apply['updated'] );
		$this->assertCount(
			1,
			get_posts(
			[
				'post_type'      => 'rondo_sponsor',
				'post_status'    => 'any',
				'posts_per_page' => -1,
			]
			)
			);
	}

	private function createSponsor( string $title, string $role, array $contacts ): int {
		$post_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_sponsor',
				'post_status' => 'publish',
				'post_title'  => $title,
			]
		);
		Fields::update_for_post( $post_id, 'sponsor_role', $role );
		Relations::set_contacts( $post_id, $contacts );
		return $post_id;
	}

	private function json_request( string $method, string $route, array $body = [] ): \WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );
		if ( $body ) {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}
		return $this->server->dispatch( $request );
	}
}
