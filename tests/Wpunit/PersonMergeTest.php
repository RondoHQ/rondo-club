<?php

namespace Tests\Wpunit;

use Rondo\Data\PersonMergeService;
use Rondo\REST\People;
use Rondo\Users\UserProvisioning;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

class PersonMergeTest extends RondoTestCase {

	public function test_preview_and_merge_combine_member_and_sponsor_profiles(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );

		$primary_id   = $this->createPerson(
			[ 'post_title' => 'Guido Ronnes' ],
			[
				'first_name'   => 'Guido',
				'last_name'    => 'Ronnes',
				'person_type'  => 'member',
				'knvb_id'      => 'FTMT74F',
				'birthdate'    => '19560118',
				'email_1'      => 'voorzitter@svawc.nl',
				'mobile_1'     => '+31654300556',
				'addresses'    => [
					[
						'address_label'         => 'Home',
						'street_name'           => 'Kastanjelaan',
						'house_number'          => '36',
						'house_number_addition' => '',
						'postal_code'           => '6533 BD',
						'city'                  => 'NIJMEGEN',
						'country_code'          => 'NL',
					],
				],
				'work_history' => [
					[
						'job_title'      => 'Voorzitter',
						'is_current'     => true,
						'start_date'     => '20231117',
						'end_date'       => '',
						'team_id'        => null,
						'team_name_text' => 'AWC',
						'entity_type'    => 'external_team',
						'description'    => '',
					],
				],
			]
		);
		$duplicate_id = $this->createPerson(
			[ 'post_title' => 'Guido Ronnes' ],
			[
				'first_name'           => 'Guido',
				'last_name'            => 'Ronnes',
				'person_type'          => 'contact',
				'company_name'         => 'Kersenallee Beheer BV',
				'sponsit_person_id'    => '241195',
				'sponsit_contact_id'   => '348627',
				'is_sponsor'           => true,
				'sponsor_pass_variant' => 'awc_sponsor',
				'birthdate'            => '1956-01-18',
				'email_1'              => 'voorzitter@svawc.ml',
				'telephone_1'          => '+31654300556',
				'addresses'            => [
					[
						'address_label'         => 'Hoofdadres',
						'street_name'           => 'Kastanjelaan',
						'house_number'          => '36',
						'house_number_addition' => '',
						'postal_code'           => '6533 BD',
						'city'                  => 'Nijmegen',
						'country_code'          => 'NL',
					],
				],
			]
		);

		$service = new PersonMergeService();
		$preview = $service->preview( $primary_id, $duplicate_id );
		$this->assertIsArray( $preview );
		$this->assertSame( [], $preview['blocking_conflicts'] );
		$this->assertSame( [], $preview['conflicts'] );

		$result = $service->merge( $primary_id, $duplicate_id, [], $admin_id );
		$this->assertIsArray( $result );
		$this->assertTrue( $result['success'] );
		$this->assertSame( 'trash', get_post_status( $duplicate_id ) );
		$this->assertSame( 'member', \Rondo\Fields\Fields::get_for_post( $primary_id, 'person_type' ) );
		$this->assertTrue( (bool) \Rondo\Fields\Fields::get_for_post( $primary_id, 'is_sponsor' ) );
		$this->assertSame( 'Kersenallee Beheer BV', \Rondo\Fields\Fields::get_for_post( $primary_id, 'company_name' ) );
		$this->assertSame( '241195', \Rondo\Fields\Fields::get_for_post( $primary_id, 'sponsit_person_id' ) );
		$this->assertSame( '348627', \Rondo\Fields\Fields::get_for_post( $primary_id, 'sponsit_contact_id' ) );
		$this->assertSame( 'voorzitter@svawc.nl', \Rondo\Fields\Fields::get_for_post( $primary_id, 'email_1' ) );
		$this->assertSame( 'voorzitter@svawc.ml', \Rondo\Fields\Fields::get_for_post( $primary_id, 'email_2' ) );
		$this->assertSame( '+31654300556', \Rondo\Fields\Fields::get_for_post( $primary_id, 'mobile_1' ) );
		$this->assertSame( '', \Rondo\Fields\Fields::get_for_post( $primary_id, 'telephone_1' ) );
		$this->assertCount( 1, \Rondo\Fields\Fields::get_for_post( $primary_id, 'addresses' ) );
		$this->assertCount( 1, get_post_meta( $primary_id, '_rondo_person_merge_history', false ) );
		$this->assertSame( $primary_id, (int) get_post_meta( $duplicate_id, '_rondo_merged_into_person_id', true ) );
	}

	public function test_conflicting_scalar_requires_an_explicit_choice(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
		$primary_id   = $this->createPerson( [ 'post_title' => 'Alex Voorbeeld' ], [ 'nickname' => 'Alex' ] );
		$duplicate_id = $this->createPerson( [ 'post_title' => 'Alex Voorbeeld' ], [ 'nickname' => 'Lex' ] );
		$service      = new PersonMergeService();

		$preview = $service->preview( $primary_id, $duplicate_id );
		$this->assertSame( 'nickname', $preview['conflicts'][0]['field'] );

		$missing = $service->merge( $primary_id, $duplicate_id, [], $admin_id );
		$this->assertWPError( $missing );
		$this->assertSame( 'rondo_person_merge_choices_required', $missing->get_error_code() );
		$this->assertSame( 'publish', get_post_status( $duplicate_id ) );

		$result = $service->merge( $primary_id, $duplicate_id, [ 'nickname' => 'duplicate' ], $admin_id );
		$this->assertIsArray( $result );
		$this->assertSame( 'Lex', \Rondo\Fields\Fields::get_for_post( $primary_id, 'nickname' ) );
	}

	public function test_different_stable_external_ids_block_the_merge(): void {
		$primary_id   = $this->createPerson( [ 'post_title' => 'Persoon Een' ], [ 'knvb_id' => 'AAAA111' ] );
		$duplicate_id = $this->createPerson( [ 'post_title' => 'Persoon Twee' ], [ 'knvb_id' => 'BBBB222' ] );
		$service      = new PersonMergeService();

		$preview = $service->preview( $primary_id, $duplicate_id );
		$this->assertSame( 'knvb_id', $preview['blocking_conflicts'][0]['field'] );

		$result = $service->merge( $primary_id, $duplicate_id, [], 1 );
		$this->assertWPError( $result );
		$this->assertSame( 'rondo_person_merge_blocked', $result->get_error_code() );
		$this->assertSame( 'publish', get_post_status( $duplicate_id ) );
	}

	public function test_merge_moves_domain_references_account_comments_and_attachments(): void {
		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
		$primary_id   = $this->createPerson( [ 'post_title' => 'Hoofdprofiel' ], [ 'knvb_id' => 'AAAA111' ] );
		$duplicate_id = $this->createPerson( [ 'post_title' => 'Dubbel profiel' ] );
		$other_id     = $this->createPerson(
			[ 'post_title' => 'Gerelateerde persoon' ],
			[
				'relationships' => [
					[
						'related_person_id'    => $duplicate_id,
						'relationship_type_id' => 4,
						'relationship_label'   => '',
					],
				],
			]
		);

		$shift_id = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
				'post_title'  => 'Shift',
			]
			);
		\Rondo\Fields\Fields::update_for_post( $shift_id, 'assigned_persons', [ $duplicate_id, $other_id ] );
		update_post_meta( $shift_id, '_no_show_' . $duplicate_id, 1 );

		$todo_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_todo',
				'post_status' => 'publish',
				'post_title'  => 'Todo',
			]
			);
		\Rondo\Fields\Fields::update_for_post( $todo_id, 'related_persons', [ $duplicate_id, $primary_id ] );

		$case_id = self::factory()->post->create(
			[
				'post_type'   => 'discipline_case',
				'post_status' => 'publish',
				'post_title'  => 'Case',
			]
			);
		\Rondo\Fields\Fields::update_for_post( $case_id, 'person', $duplicate_id );
		$invoice_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_invoice',
				'post_status' => 'publish',
				'post_title'  => 'Invoice',
			]
			);
		\Rondo\Fields\Fields::update_for_post( $invoice_id, 'person', $duplicate_id );

		$clothing_id = self::factory()->post->create(
			[
				'post_type'   => 'clothing_assignment',
				'post_status' => 'publish',
				'post_title'  => 'Kleding',
			]
			);
		update_post_meta( $clothing_id, '_clothing_person_id', $duplicate_id );

		$comment_id    = self::factory()->comment->create(
			[
				'comment_post_ID' => $duplicate_id,
				'comment_content' => 'Historische notitie',
			]
			);
		$image         = wp_upload_bits( 'rondo-merge-source.gif', null, base64_decode( 'R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==' ) );
		$attachment_id = self::factory()->attachment->create_upload_object( $image['file'], $duplicate_id );
		set_post_thumbnail( $duplicate_id, $attachment_id );

		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		update_user_meta( $user_id, 'rondo_linked_person_id', $duplicate_id );
		update_post_meta( $duplicate_id, UserProvisioning::META_USER_ID, $user_id );

		$result = ( new PersonMergeService() )->merge( $primary_id, $duplicate_id, [], $admin_id );
		$this->assertIsArray( $result );
		$this->assertSame( [ $primary_id, $other_id ], array_map( 'intval', \Rondo\Fields\Fields::get_for_post( $shift_id, 'assigned_persons' ) ) );
		$this->assertSame( 1, (int) get_post_meta( $shift_id, '_no_show_' . $primary_id, true ) );
		$this->assertFalse( metadata_exists( 'post', $shift_id, '_no_show_' . $duplicate_id ) );
		$this->assertSame( [ $primary_id ], array_map( 'intval', \Rondo\Fields\Fields::get_for_post( $todo_id, 'related_persons' ) ) );
		$this->assertSame( $primary_id, (int) \Rondo\Fields\Fields::get_for_post( $case_id, 'person' ) );
		$this->assertSame( $primary_id, (int) \Rondo\Fields\Fields::get_for_post( $invoice_id, 'person' ) );
		$this->assertSame( $primary_id, (int) get_post_meta( $clothing_id, '_clothing_person_id', true ) );
		$this->assertSame( $primary_id, (int) get_comment( $comment_id )->comment_post_ID );
		$this->assertSame( $primary_id, (int) get_post( $attachment_id )->post_parent );
		$this->assertSame( $attachment_id, (int) get_post_thumbnail_id( $primary_id ) );
		$this->assertSame( $primary_id, (int) get_user_meta( $user_id, 'rondo_linked_person_id', true ) );
		$this->assertSame( $user_id, (int) get_post_meta( $primary_id, UserProvisioning::META_USER_ID, true ) );
		$relationships = \Rondo\Fields\Fields::get_for_post( $other_id, 'relationships' );
		$this->assertSame( $primary_id, (int) $relationships[0]['related_person'] );
	}

	public function test_merge_rest_routes_are_admin_only_and_require_confirmation(): void {
		$server       = $this->bootRestControllers( [ People::class ] );
		$primary_id   = $this->createPerson( [ 'post_title' => 'Hoofdprofiel' ] );
		$duplicate_id = $this->createPerson( [ 'post_title' => 'Dubbel profiel' ] );
		$member_id    = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $member_id );

		$preview = new WP_REST_Request( 'GET', '/rondo/v1/people/' . $primary_id . '/merge-preview' );
		$preview->set_param( 'duplicate_id', $duplicate_id );
		$this->assertSame( 403, $server->dispatch( $preview )->get_status() );

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
		$this->assertSame( 200, $server->dispatch( $preview )->get_status() );

		$merge = new WP_REST_Request( 'POST', '/rondo/v1/people/' . $primary_id . '/merge' );
		$merge->set_param( 'duplicate_id', $duplicate_id );
		$merge->set_param( 'resolutions', [] );
		$merge->set_param( 'confirmed', false );
		$response = $server->dispatch( $merge );
		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rondo_person_merge_confirmation_required', $response->get_data()['code'] );

		$merge->set_param( 'confirmed', true );
		$response = $server->dispatch( $merge );
		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['success'] );
		$this->assertSame( 'trash', get_post_status( $duplicate_id ) );

		$target   = new WP_REST_Request( 'GET', '/rondo/v1/people/' . $duplicate_id . '/merge-target' );
		$response = $server->dispatch( $target );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $primary_id, $response->get_data()['merged_into_person_id'] );

		$missing = new WP_REST_Request( 'GET', '/rondo/v1/people/' . $primary_id . '/merge-target' );
		$this->assertSame( 404, $server->dispatch( $missing )->get_status() );
	}
}
