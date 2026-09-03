<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\Identity\OidcAuthorizationService;
use Rondo\Identity\OidcClientRegistry;
use Rondo\Identity\OidcIdentity;
use Rondo\Integrations\FreeScout\Config;
use Rondo\Integrations\FreeScout\PersonMatcher;
use Rondo\REST\FreeScoutIntegration;
use Rondo\REST\MemberShifts;
use Tests\Support\RondoTestCase;

/** Covers the signed FreeScout sidebar, access, configuration and activity contracts. */
class FreeScoutIntegrationTest extends RondoTestCase {

	private const KEY      = 'test-signing-key-with-at-least-thirty-two-bytes';
	private const INSTANCE = 'https://support.example.test';

	private \WP_REST_Server $server;
	private int $agent_id;
	private string $subject;
	private int $nonce_counter      = 0;
	private ?string $previous_https = null;

	protected function set_up(): void {
		parent::set_up();
		$this->previous_https = isset( $_SERVER['HTTPS'] ) ? (string) $_SERVER['HTTPS'] : null;
		$_SERVER['HTTPS']     = 'on';
		add_filter( 'rondo_freescout_signing_keys', [ $this, 'signing_keys' ] );
		$this->server = $this->bootRestControllers( [ FreeScoutIntegration::class, MemberShifts::class ] );

		$this->agent_id  = self::factory()->user->create(
			[
				'role'       => 'rondo_user',
				'user_email' => 'agent@example.test',
			]
		);
		$agent_person_id = $this->createPerson( [ 'post_title' => 'Integration agent' ], [ 'email_1' => 'agent@example.test' ] );
		update_user_meta( $this->agent_id, 'rondo_linked_person_id', $agent_person_id );
		$user = get_userdata( $this->agent_id );
		$user->add_cap( 'ledenadministratie' );
		$this->subject = OidcIdentity::subject( $this->agent_id );

		$client = OidcClientRegistry::create(
			[
				'label'              => 'FreeScout test',
				'redirect_uris'      => [ self::INSTANCE . '/rondo/oidc/callback' ],
				'freescout_base_url' => self::INSTANCE,
			]
		);
		$this->assertNotWPError( $client );
	}

	protected function tear_down(): void {
		remove_filter( 'rondo_freescout_signing_keys', [ $this, 'signing_keys' ] );
		if ( $this->previous_https === null ) {
			unset( $_SERVER['HTTPS'] );
		} else {
			$_SERVER['HTTPS'] = $this->previous_https;
		}
		parent::tear_down();
	}

	/** @return string[] */
	public function signing_keys(): array {
		return [ self::KEY ];
	}

	public function test_configuration_requires_a_valid_signature_and_returns_closed_catalog(): void {
		$response = $this->signed_request(
			'configuration',
			[
				'version'  => 1,
				'instance' => self::INSTANCE,
			]
			);

		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$data = $response->get_data();
		$this->assertSame( 1, $data['version'] );
		$this->assertSame(
			[
				'key'            => 'basis',
				'sidebar_policy' => 'basis.v1',
				'enabled'        => true,
			],
			$data['sidebar']
		);
		$this->assertSame( 'ledenadministratie', $data['mappings'][0]['key'] );
		$this->assertSame( 'ledenadministratie.v2', $data['mappings'][0]['sidebar_policy'] );
		$this->assertSame( 'contributie', $data['mappings'][1]['key'] );
		$this->assertSame( 'financieel', $data['mappings'][1]['required_capability'] );
		$this->assertSame( 'contributie.v1', $data['mappings'][1]['sidebar_policy'] );
		$this->assertSame(
			[
				'retention_days' => 365,
				'source'         => 'default',
			],
			$data['audit']
			);

		$denied = $this->signed_request(
			'configuration',
			[
				'version'  => 1,
				'instance' => self::INSTANCE,
			],
			'bad-key'
			);
		$this->assertSame( 401, $denied->get_status() );
		$this->assertSame( 'rondo_freescout_signature_invalid', $denied->get_data()['code'] );
	}

	public function test_nonce_replay_is_rejected_after_signature_validation(): void {
		$body      = [
			'version'  => 1,
			'instance' => self::INSTANCE,
		];
		$timestamp = time();
		$nonce     = 'nonce_value_that_is_long_enough_123456';

		$first  = $this->signed_request( 'configuration', $body, self::KEY, $nonce, $timestamp );
		$second = $this->signed_request( 'configuration', $body, self::KEY, $nonce, $timestamp );

		$this->assertSame( 200, $first->get_status() );
		$this->assertSame( 409, $second->get_status() );
		$this->assertSame( 'rondo_freescout_replay', $second->get_data()['code'] );
	}

	public function test_configuration_publishes_the_configurable_retention_policy(): void {
		$this->assertTrue( Config::update_retention( 420 ) );

		$response = $this->signed_request(
			'configuration',
			[
				'version'  => 1,
				'instance' => self::INSTANCE,
			]
			);

		$this->assertSame(
			[
				'retention_days' => 420,
				'source'         => 'rondo_setting',
			],
			$response->get_data()['audit']
			);
		$this->assertSame( 420, Config::retention_status()['retention_days'] );
		$this->assertFalse( Config::retention_status()['locked'] );
	}

	public function test_access_resolves_exact_subject_and_rechecks_current_capability(): void {
		$active = $this->signed_request(
			'access',
			[
				'version'         => 1,
				'issuer'          => OidcAuthorizationService::issuer(),
				'subject'         => $this->subject,
				'freescoutUserId' => 44,
			]
		);
		$this->assertTrue( $active->get_data()['active'] );
		$this->assertSame( [ 'ledenadministratie' ], $active->get_data()['managed_mailboxes'] );

		$agent = get_userdata( $this->agent_id );
		$agent->add_cap( 'financieel' );
		$both = $this->signed_request(
			'access',
			[
				'version'         => 1,
				'issuer'          => OidcAuthorizationService::issuer(),
				'subject'         => $this->subject,
				'freescoutUserId' => 44,
			]
		);
		$this->assertSame( [ 'ledenadministratie', 'contributie' ], $both->get_data()['managed_mailboxes'] );

		$agent->remove_cap( 'financieel' );
		$agent->remove_cap( 'ledenadministratie' );
		$inactive = $this->signed_request(
			'access',
			[
				'version'         => 1,
				'issuer'          => OidcAuthorizationService::issuer(),
				'subject'         => $this->subject,
				'freescoutUserId' => 44,
			]
		);
		$this->assertFalse( $inactive->get_data()['active'] );
		$this->assertSame( [], $inactive->get_data()['managed_mailboxes'] );
	}

	public function test_access_allows_missing_local_user_id_during_first_binding(): void {
		$without_id = $this->signed_request(
			'access',
			[
				'version'         => 1,
				'issuer'          => OidcAuthorizationService::issuer(),
				'subject'         => $this->subject,
				'freescoutUserId' => null,
			]
		);

		$this->assertSame( 200, $without_id->get_status(), wp_json_encode( $without_id->get_data() ) );
		$this->assertTrue( $without_id->get_data()['active'] );
		$this->assertSame( [ 'ledenadministratie' ], $without_id->get_data()['managed_mailboxes'] );
	}

	public function test_access_rejects_invalid_local_user_ids(): void {
		foreach ( [ 0, -1, '44' ] as $invalid_id ) {
			$response = $this->signed_request(
				'access',
				[
					'version'         => 1,
					'issuer'          => OidcAuthorizationService::issuer(),
					'subject'         => $this->subject,
					'freescoutUserId' => $invalid_id,
				]
			);

			$this->assertSame( 400, $response->get_status() );
			$this->assertSame( 'rondo_freescout_access_schema_invalid', $response->get_data()['code'] );
		}
	}

	public function test_sidebar_matches_secondary_email_and_omits_excluded_data(): void {
		$person_id = $this->createPerson(
			[ 'post_title' => 'Jan van Test' ],
			[
				'first_name'               => 'Jan',
				'infix'                    => 'van',
				'last_name'                => 'Test',
				'email_2'                  => 'member@example.test',
				'knvb_id'                  => 'KNVB123',
				'type_lid'                 => 'Bondslid',
				'nikki_contributie_status' => 'Privé financieel veld',
				'freescout_id'             => 987,
			]
		);

		$response = $this->signed_request( 'sidebar', $this->sidebar_body( [ 'member@example.test' ] ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'ok', $data['status'] );
		$this->assertStringContainsString( 'Jan van Test', $data['html'] );
		$this->assertStringContainsString( 'KNVB123', $data['html'] );
		$this->assertStringContainsString( 'data-rondo-card', $data['html'] );
		$this->assertStringContainsString( 'data-rondo-tab="member"', $data['html'] );
		$this->assertStringContainsString( 'data-rondo-tab="contact"', $data['html'] );
		$this->assertStringContainsString( 'data-rondo-tab="process"', $data['html'] );
		$this->assertStringContainsString( '>Acties</button>', $data['html'] );
		$this->assertStringContainsString( 'rondo-mailbox-badge', $data['html'] );
		$this->assertStringContainsString( 'Open in Rondo', $data['html'] );
		$this->assertStringContainsString( 'Open in Sportlink', $data['html'] );
		$this->assertStringContainsString( 'https://club.sportlink.com/member/member-details/KNVB123/general', $data['html'] );
		$this->assertStringNotContainsString( 'Privé financieel veld', $data['html'] );
		$this->assertStringNotContainsString( '987', $data['html'] );
		$this->assertStringNotContainsString( '<script', strtolower( $data['html'] ) );
		$this->assertSame( 0, get_current_user_id(), 'The anonymous server-request user is restored after rendering.' );
		$this->assertSame( 'publish', get_post_status( $person_id ) );
	}

	public function test_sidebar_shared_email_renders_accessible_profile_switcher(): void {
		$this->createPerson( [ 'post_title' => 'First private name' ], [ 'email_1' => 'shared@example.test' ] );
		$this->createPerson( [ 'post_title' => 'Second private name' ], [ 'email_2' => 'shared@example.test' ] );

		$data = $this->signed_request( 'sidebar', $this->sidebar_body( [ 'shared@example.test' ] ) )->get_data();

		$this->assertSame( 'ambiguous', $data['status'] );
		$this->assertStringContainsString( 'data-rondo-profile-switcher', $data['html'] );
		$this->assertStringContainsString( 'First private name', $data['html'] );
		$this->assertStringContainsString( 'Second private name', $data['html'] );
		$this->assertStringContainsString( 'data-rondo-profile-panel', $data['html'] );
		$this->assertStringContainsString( 'hidden', $data['html'] );
	}

	public function test_sidebar_shared_email_prefers_the_exact_sender_name(): void {
		$this->createPerson( [ 'post_title' => 'Maaike Netten' ], [ 'email_1' => 'family@example.test' ] );
		$this->createPerson( [ 'post_title' => 'Tibbe Smit' ], [ 'email_1' => 'family@example.test' ] );
		$body             = $this->sidebar_body( [ 'family@example.test' ] );
		$body['fromName'] = 'MÁÁIKE   NETTEN';

		$data = $this->signed_request( 'sidebar', $body )->get_data();

		$this->assertSame( 'ok', $data['status'] );
		$this->assertStringContainsString( 'Maaike Netten', $data['html'] );
		$this->assertStringNotContainsString( 'Tibbe Smit', $data['html'] );
		$this->assertStringNotContainsString( 'data-rondo-profile-switcher', $data['html'] );
	}

	public function test_sidebar_shared_email_prefers_the_only_active_person(): void {
		$this->createPerson(
			[ 'post_title' => 'Former member' ],
			[
				'email_1'       => 'status@example.test',
				'former_member' => true,
			]
		);
		$this->createPerson( [ 'post_title' => 'Active member' ], [ 'email_1' => 'status@example.test' ] );

		$data = $this->signed_request( 'sidebar', $this->sidebar_body( [ 'status@example.test' ] ) )->get_data();

		$this->assertSame( 'ok', $data['status'] );
		$this->assertStringContainsString( 'Active member', $data['html'] );
		$this->assertStringNotContainsString( 'Former member', $data['html'] );
	}

	public function test_sidebar_rejects_an_invalid_sender_name(): void {
		$body             = $this->sidebar_body( [ 'member@example.test' ] );
		$body['fromName'] = "Naam\nInjected";

		$response = $this->signed_request( 'sidebar', $body );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rondo_freescout_sidebar_schema_invalid', $response->as_error()->get_error_code() );
	}

	public function test_sidebar_matches_a_validated_sportlink_relation_code_before_customer_email(): void {
		$this->createPerson( [ 'post_title' => 'Shared sender decoy' ], [ 'email_1' => 'no-reply@sportlinkservices.nl' ] );
		$this->createPerson(
			[ 'post_title' => 'Transfer member' ],
			[
				'email_1' => 'transfer-member@example.test',
				'knvb_id' => 'LXCX82K',
			]
		);
		$body                    = $this->sidebar_body( [ 'no-reply@sportlinkservices.nl' ] );
		$body['personReference'] = [
			'type'   => 'knvb_id',
			'value'  => 'LXCX82K',
			'source' => 'sportlink_transfer_request',
		];

		$response = $this->signed_request( 'sidebar', $body );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'ok', $data['status'] );
		$this->assertStringContainsString( 'Transfer member', $data['html'] );
		$this->assertStringNotContainsString( 'Shared sender decoy', $data['html'] );
		$this->assertStringContainsString( 'https://club.sportlink.com/member/member-details/LXCX82K/general', $data['html'] );
	}

	public function test_sidebar_rejects_untrusted_person_reference_contracts(): void {
		$invalid_references = [
			null,
			[
				'type'   => 'knvb_id',
				'value'  => '../bad',
				'source' => 'sportlink_transfer_request',
			],
			[
				'type'   => 'knvb_id',
				'value'  => 'LXCX82K',
				'source' => 'email_body',
			],
			[
				'type'   => 'email',
				'value'  => 'LXCX82K',
				'source' => 'sportlink_transfer_request',
			],
			[
				'type'   => 'knvb_id',
				'value'  => 'LXCX82K',
				'source' => 'sportlink_transfer_request',
				'extra'  => true,
			],
		];

		foreach ( $invalid_references as $reference ) {
			$body                    = $this->sidebar_body( [ 'member@example.test' ] );
			$body['personReference'] = $reference;
			$response                = $this->signed_request( 'sidebar', $body );

			$this->assertSame( 400, $response->get_status() );
			$this->assertSame( 'rondo_freescout_sidebar_schema_invalid', $response->as_error()->get_error_code() );
		}
	}

	public function test_sidebar_links_related_records_and_formats_contact_and_action_details(): void {
		$team_id        = self::factory()->post->create(
			[
				'post_type'   => 'team',
				'post_status' => 'publish',
				'post_title'  => 'AWC O19-2',
			]
		);
		$parent_id      = $this->createPerson( [ 'post_title' => 'Ouder Test' ] );
		$person_id      = $this->createPerson(
			[ 'post_title' => 'Sidebar member' ],
			[
				'email_1'        => 'sidebar-details@example.test',
				'mobile_1'       => '06 12345678',
				'type_lid'       => 'Bondslid',
				'spelactiviteit' => 'Veld - Algemeen',
				'birthdate'      => '2010-07-09',
				'addresses'      => [
					[
						'address_label' => 'Home',
						'street_name'   => 'Dorpsstraat',
						'house_number'  => '12',
						'postal_code'   => '6601 AA',
						'city'          => 'Wijchen',
					],
				],
				'work_history'   => [
					[
						'team_id'    => $team_id,
						'is_current' => true,
					],
				],
				'relationships'  => [
					[
						'related_person_id'  => $parent_id,
						'relationship_label' => 'Ouder/verzorger',
					],
				],
			]
		);
		$linked_user_id = self::factory()->user->create( [ 'role' => 'rondo_user' ] );
		update_user_meta( $linked_user_id, 'rondo_linked_person_id', $person_id );

		$shift_start = current_datetime()->modify( '+2 days' );
		$shift_id    = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
				'post_title'  => 'Terreinonderhoud',
			]
		);
		update_post_meta( $shift_id, 'start_datetime', $shift_start->format( 'Y-m-d H:i:s' ) );
		update_post_meta( $shift_id, 'end_datetime', $shift_start->modify( '+2 hours' )->format( 'Y-m-d H:i:s' ) );
		update_post_meta( $shift_id, 'status', 'open' );
		update_post_meta( $shift_id, 'assigned_persons', [ $person_id ] );

		$data = $this->signed_request( 'sidebar', $this->sidebar_body( [ 'sidebar-details@example.test' ] ) )->get_data();

		$this->assertSame( 'ok', $data['status'] );
		$this->assertStringContainsString( 'href="' . home_url( '/teams/' . $team_id ) . '">AWC O19-2</a>', $data['html'] );
		$this->assertStringContainsString( 'href="' . home_url( '/people/' . $parent_id ) . '">Ouder Test</a>', $data['html'] );
		$this->assertStringContainsString( '<dt>Ouder / Verz.</dt>', $data['html'] );
		$this->assertStringContainsString( 'https://wa.me/31612345678', $data['html'] );
		$this->assertStringContainsString( 'rondo-inline-action--whatsapp', $data['html'] );
		$this->assertStringContainsString( 'aria-label="Open WhatsApp"', $data['html'] );
		$this->assertStringContainsString( 'Dorpsstraat 12<br>6601 AA Wijchen', $data['html'] );
		$this->assertStringContainsString( '<dt>Geb. datum</dt>', $data['html'] );
		$this->assertStringNotContainsString( '<dt>Geboortedatum</dt>', $data['html'] );
		$this->assertLessThan( strpos( $data['html'], '<dt>Spelactiviteit</dt>' ), strpos( $data['html'], '<dt>Lidsoort</dt>' ) );
		$this->assertStringContainsString( '<dt>Rondo-account</dt><dd>Ja</dd>', $data['html'] );
		$this->assertStringNotContainsString( 'Digitale pas', $data['html'] );
		$this->assertStringContainsString( '<h3>Inschrijftaken</h3>', $data['html'] );
		$this->assertStringContainsString( 'Terreinonderhoud', $data['html'] );
	}

	public function test_basic_sidebar_works_for_an_exact_bound_rondo_user(): void {
		$person_id  = $this->createPerson( [ 'post_title' => 'Basic member' ], [ 'email_1' => 'basic@example.test' ] );
		$invoice_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_invoice',
				'post_status' => 'rondo_overdue',
			]
		);
		Fields::update_for_post( $invoice_id, 'person', $person_id );
		Fields::update_for_post( $invoice_id, 'invoice_type', 'membership' );
		Fields::update_for_post( $invoice_id, 'invoice_number', 'BASIC-HIDDEN' );
		Fields::update_for_post( $invoice_id, 'total_amount', 100 );
		get_userdata( $this->agent_id )->add_cap( 'financieel' );

		$response = $this->signed_request( 'sidebar', $this->sidebar_body( [ 'basic@example.test' ], 'basis' ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'ok', $data['status'] );
		$this->assertStringContainsString( 'Basic member', $data['html'] );
		$this->assertStringNotContainsString( 'Openstaande contributie', $data['html'] );
		$this->assertStringNotContainsString( 'BASIC-HIDDEN', $data['html'] );
	}

	public function test_sportlink_action_requires_an_allowed_capability_and_valid_knvb_id(): void {
		$this->createPerson(
			[ 'post_title' => 'Sportlink member' ],
			[
				'email_1' => 'sportlink@example.test',
				'knvb_id' => 'SQJG27J',
			]
		);

		get_userdata( $this->agent_id )->remove_cap( 'ledenadministratie' );
		$without_capability = $this->signed_request( 'sidebar', $this->sidebar_body( [ 'sportlink@example.test' ], 'basis' ) )->get_data();
		$this->assertStringNotContainsString( 'Open in Sportlink', $without_capability['html'] );

		get_userdata( $this->agent_id )->add_cap( 'financieel' );
		$with_finance = $this->signed_request( 'sidebar', $this->sidebar_body( [ 'sportlink@example.test' ], 'basis' ) )->get_data();
		$this->assertStringContainsString( 'https://club.sportlink.com/member/member-details/SQJG27J/general', $with_finance['html'] );

		$invalid_id = $this->createPerson(
			[ 'post_title' => 'Invalid Sportlink member' ],
			[
				'email_1' => 'invalid-sportlink@example.test',
				'knvb_id' => '../bad',
			]
		);
		$invalid    = $this->signed_request( 'sidebar', $this->sidebar_body( [ 'invalid-sportlink@example.test' ], 'basis' ) )->get_data();
		$this->assertStringNotContainsString( 'Open in Sportlink', $invalid['html'] );
		$this->assertSame( 'publish', get_post_status( $invalid_id ) );
	}

	public function test_sidebar_rejects_an_unknown_policy_key(): void {
		$response = $this->signed_request( 'sidebar', $this->sidebar_body( [ 'member@example.test' ], 'unknown' ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rondo_freescout_sidebar_schema_invalid', $response->as_error()->get_error_code() );
	}

	public function test_sidebar_only_shows_open_contribution_invoices_to_finance_viewers(): void {
		$person_id  = $this->createPerson( [ 'post_title' => 'Finance member' ], [ 'email_1' => 'finance@example.test' ] );
		$invoice_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_invoice',
				'post_status' => 'rondo_overdue',
				'post_title'  => 'Membership invoice',
			]
		);
		Fields::update_for_post( $invoice_id, 'person', $person_id );
		Fields::update_for_post( $invoice_id, 'invoice_type', 'membership' );
		Fields::update_for_post( $invoice_id, 'invoice_number', 'C2026-123' );
		Fields::update_for_post( $invoice_id, 'total_amount', 120 );
		Fields::update_for_post( $invoice_id, 'due_date', '2026-09-01' );
		update_post_meta( $invoice_id, '_installment_plan', 'quarterly_3' );
		update_post_meta( $invoice_id, '_installment_count', 3 );
		update_post_meta( $invoice_id, '_installment_1_status', 'betaald' );
		update_post_meta( $invoice_id, '_installment_1_amount', 40 );
		update_post_meta( $invoice_id, '_installment_2_status', 'sent' );
		update_post_meta( $invoice_id, '_installment_2_due_date', '2026-09-01' );

		$manual_invoice_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_invoice',
				'post_status' => 'rondo_overdue',
			]
			);
		Fields::update_for_post( $manual_invoice_id, 'person', $person_id );
		Fields::update_for_post( $manual_invoice_id, 'invoice_type', 'manual' );
		Fields::update_for_post( $manual_invoice_id, 'invoice_number', 'F-PRIVATE' );
		Fields::update_for_post( $manual_invoice_id, 'total_amount', 999 );

		$without_finance = $this->signed_request( 'sidebar', $this->sidebar_body( [ 'finance@example.test' ] ) )->get_data();
		$this->assertStringNotContainsString( 'Openstaande contributie', $without_finance['html'] );
		$this->assertStringNotContainsString( 'C2026-123', $without_finance['html'] );

		get_userdata( $this->agent_id )->add_cap( 'financieel_read' );
		$with_finance = $this->signed_request( 'sidebar', $this->sidebar_body( [ 'finance@example.test' ] ) )->get_data();
		$this->assertStringContainsString( 'Openstaande contributie', $with_finance['html'] );
		$this->assertStringContainsString( 'C2026-123', $with_finance['html'] );
		$this->assertStringContainsString( '€ 80.00', $with_finance['html'] );
		$this->assertStringContainsString( '1/3 termijnen betaald', $with_finance['html'] );
		$this->assertStringContainsString( 'rondo-alert--finance', $with_finance['html'] );
		$this->assertStringContainsString( '<a class="rondo-invoice-link" href="' . home_url( '/financien/facturen/' . $invoice_id ) . '">Factuur C2026-123</a>', $with_finance['html'] );
		$this->assertStringContainsString( '<dt>Totaal bedrag</dt><dd>€ 120.00</dd>', $with_finance['html'] );
		$this->assertStringContainsString( '<dt>Nog open</dt><dd>€ 80.00</dd>', $with_finance['html'] );
		$this->assertStringNotContainsString( '>Open</a>', $with_finance['html'] );
		$this->assertStringNotContainsString( 'F-PRIVATE', $with_finance['html'] );
		$this->assertStringNotContainsString( '999', $with_finance['html'] );
	}

	public function test_contribution_sidebar_requires_write_capability_but_accepts_finance_viewer_data(): void {
		$this->createPerson( [ 'post_title' => 'Contribution member' ], [ 'email_1' => 'contribution@example.test' ] );
		get_userdata( $this->agent_id )->add_cap( 'financieel_read' );

		$denied = $this->signed_request( 'sidebar', $this->sidebar_body( [ 'contribution@example.test' ], 'contributie' ) )->get_data();
		$this->assertSame( 'unauthorized', $denied['status'] );

		get_userdata( $this->agent_id )->add_cap( 'financieel' );
		$allowed = $this->signed_request( 'sidebar', $this->sidebar_body( [ 'contribution@example.test' ], 'contributie' ) )->get_data();
		$this->assertSame( 'ok', $allowed['status'] );
		$this->assertStringContainsString( 'Contribution member', $allowed['html'] );
	}

	public function test_matcher_discards_synthetic_and_malformed_emails(): void {
		$person_id = $this->createPerson( [ 'post_title' => 'Synthetic' ], [ 'email_1' => '123@members.rondo.invalid' ] );
		$this->createPerson( [ 'post_title' => 'Inaccessible' ], [ 'email_1' => 'real@example.test' ] );
		$matcher = new PersonMatcher();

		$this->assertSame( 'no_match', $matcher->match( [ '123@members.rondo.invalid', 'not-an-email' ] )['status'] );
		$this->assertSame( 'inaccessible', $matcher->match( [ 'real@example.test' ], 'sidebar', 0 )['status'] ?? 'no_match' );
		$this->assertSame( 'publish', get_post_status( $person_id ) );
	}

	public function test_matcher_requires_a_unique_valid_knvb_id(): void {
		$person_id = $this->createPerson( [ 'post_title' => 'Sportlink member' ], [ 'knvb_id' => 'LXCX82K' ] );
		$matcher   = new PersonMatcher();

		$this->assertSame( $person_id, $matcher->match_knvb_id( 'lxcx82k' )['person_id'] );
		$this->assertSame( 'no_match', $matcher->match_knvb_id( '../bad' )['status'] );

		$this->createPerson( [ 'post_title' => 'Duplicate Sportlink member' ], [ 'knvb_id' => 'LXCX82K' ] );
		$this->assertSame( 'ambiguous', $matcher->match_knvb_id( 'LXCX82K' )['status'] );
	}

	public function test_activity_is_idempotent_and_customer_changes_move_hide_and_restore_it(): void {
		$first  = $this->createPerson( [ 'post_title' => 'First member' ], [ 'email_1' => 'first@example.test' ] );
		$second = $this->createPerson( [ 'post_title' => 'Second member' ], [ 'email_1' => 'second@example.test' ] );
		$body   = $this->activity_body( 'conversation_created', [ 'first@example.test' ] );

		$created   = $this->signed_request( 'activity', $body )->get_data();
		$confirmed = $this->signed_request( 'activity', $body )->get_data();
		$this->assertSame( 'created', $created['status'] );
		$this->assertSame( 'confirmed', $confirmed['status'] );
		$this->assertSame( $created['activity_id'], $confirmed['activity_id'] );
		$this->assertSame( $first, (int) get_comment( $created['activity_id'] )->comment_post_ID );

		$moved = $this->signed_request( 'activity', $this->activity_body( 'conversation_customer_changed', [ 'second@example.test' ] ) )->get_data();
		$this->assertSame( 'moved', $moved['status'] );
		$this->assertSame( $second, (int) get_comment( $created['activity_id'] )->comment_post_ID );

		$hidden = $this->signed_request( 'activity', $this->activity_body( 'conversation_customer_changed', [ 'missing@example.test' ] ) )->get_data();
		$this->assertSame( 'no_match', $hidden['status'] );
		$this->assertSame( '0', (string) get_comment( $created['activity_id'] )->comment_approved );

		$restored = $this->signed_request( 'activity', $this->activity_body( 'conversation_customer_changed', [ 'second@example.test' ] ) )->get_data();
		$this->assertSame( 'restored', $restored['status'] );
		$this->assertSame( '1', (string) get_comment( $created['activity_id'] )->comment_approved );
		$this->assertSame(
			1,
			count(
			get_comments(
			[
				'type'   => 'rondo_activity',
				'status' => 'all',
			]
			)
			)
			);
		$this->assertStringNotContainsString( 'second@example.test', serialize( get_comment_meta( $created['activity_id'] ) ) );
	}

	public function test_reply_activities_are_distinct_idempotent_and_keep_message_content_out(): void {
		$first  = $this->createPerson( [ 'post_title' => 'First member' ], [ 'email_1' => 'first@example.test' ] );
		$second = $this->createPerson( [ 'post_title' => 'Second member' ], [ 'email_1' => 'second@example.test' ] );
		$this->signed_request( 'activity', $this->activity_body( 'conversation_created', [ 'first@example.test' ] ) );

		$incoming_body            = $this->activity_body( 'customer_replied', [ 'first@example.test' ] );
		$incoming_body['eventId'] = 1001;
		$incoming                 = $this->signed_request( 'activity', $incoming_body )->get_data();
		$incoming_replay          = $this->signed_request( 'activity', $incoming_body )->get_data();
		$this->assertSame( 'created', $incoming['status'] );
		$this->assertSame( 'confirmed', $incoming_replay['status'] );
		$this->assertSame( $incoming['activity_id'], $incoming_replay['activity_id'] );

		$outgoing_body            = $this->activity_body( 'user_replied', [ 'first@example.test' ] );
		$outgoing_body['eventId'] = 1002;
		$outgoing_body['actor']   = [
			'freescoutUserId' => 44,
			'issuer'          => OidcAuthorizationService::issuer(),
			'subject'         => $this->subject,
		];
		get_userdata( $this->agent_id )->remove_cap( 'ledenadministratie' );
		$outgoing         = $this->signed_request( 'activity', $outgoing_body )->get_data();
		$outgoing_comment = get_comment( $outgoing['activity_id'] );
		$this->assertSame( $this->agent_id, (int) $outgoing_comment->user_id );
		$this->assertStringContainsString( 'Antwoord verzonden door', $outgoing_comment->comment_content );
		$this->assertStringNotContainsString( 'berichttekst', $outgoing_comment->comment_content );

		$all = get_comments(
			[
				'type'   => 'rondo_activity',
				'status' => 'all',
			]
			);
		$this->assertCount( 3, $all );
		$moved = $this->signed_request( 'activity', $this->activity_body( 'conversation_customer_changed', [ 'second@example.test' ] ) )->get_data();
		$this->assertSame( 'moved', $moved['status'] );
		$this->assertCount( 3, $moved['activity_ids'] );
		foreach ( $all as $activity ) {
			$this->assertSame( $first, (int) $activity->comment_post_ID );
			$this->assertSame( $second, (int) get_comment( $activity->comment_ID )->comment_post_ID );
		}
	}

	public function test_reply_activity_requires_a_thread_event_id(): void {
		$this->createPerson( [ 'post_title' => 'First member' ], [ 'email_1' => 'first@example.test' ] );
		$response = $this->signed_request( 'activity', $this->activity_body( 'customer_replied', [ 'first@example.test' ] ) );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'rondo_freescout_activity_schema_invalid', $response->as_error()->get_error_code() );
	}

	public function test_activity_date_and_time_use_the_site_timezone(): void {
		$previous_timezone = get_option( 'timezone_string' );
		update_option( 'timezone_string', 'Europe/Amsterdam' );

		try {
			$this->createPerson( [ 'post_title' => 'First member' ], [ 'email_1' => 'first@example.test' ] );
			$response    = $this->signed_request( 'activity', $this->activity_body( 'conversation_created', [ 'first@example.test' ] ) );
			$activity_id = (int) $response->get_data()['activity_id'];

			$this->assertSame( '2026-09-01', get_comment_meta( $activity_id, 'activity_date', true ) );
			$this->assertSame( '14:00', get_comment_meta( $activity_id, 'activity_time', true ) );
			$this->assertSame( '2026-09-01 12:00:00', get_comment( $activity_id )->comment_date_gmt );
			$this->assertSame( '2026-09-01 14:00:00', get_comment( $activity_id )->comment_date );
		} finally {
			update_option( 'timezone_string', $previous_timezone );
		}
	}

	/** @return array<string,mixed> */
	private function sidebar_body( array $emails, string $mailbox_key = 'ledenadministratie' ): array {
		return [
			'version'            => 1,
			'mailboxKey'         => $mailbox_key,
			'conversationId'     => 3456,
			'conversationNumber' => 789,
			'customerId'         => 123,
			'customerEmails'     => $emails,
			'agent'              => [
				'freescoutUserId' => 44,
				'issuer'          => OidcAuthorizationService::issuer(),
				'subject'         => $this->subject,
			],
		];
	}

	/** @return array<string,mixed> */
	private function activity_body( string $event_type, array $emails ): array {
		return [
			'version'        => 1,
			'eventType'      => $event_type,
			'instance'       => self::INSTANCE,
			'mailboxKey'     => 'ledenadministratie',
			'conversationId' => 8765,
			'customerId'     => 321,
			'customerEmails' => $emails,
			'subject'        => 'Vraag over lidmaatschap',
			'createdAt'      => '2026-09-01T12:00:00Z',
		];
	}

	private function signed_request( string $route, array $body, string $key = self::KEY, ?string $nonce = null, ?int $timestamp = null ): \WP_REST_Response {
		$raw       = wp_json_encode( $body, JSON_UNESCAPED_SLASHES );
		$timestamp = $timestamp ?? time();
		$nonce     = $nonce ?? 'test_nonce_value_' . str_pad( (string) ++$this->nonce_counter, 32, 'x' );
		$signature = hash_hmac( 'sha256', $timestamp . "\n" . $nonce . "\n" . $raw, $key );
		$request   = new \WP_REST_Request( 'POST', '/rondo/v1/integrations/freescout/' . $route );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_header( 'X-Rondo-Timestamp', (string) $timestamp );
		$request->set_header( 'X-Rondo-Nonce', $nonce );
		$request->set_header( 'X-Rondo-Signature', 'v1=' . $signature );
		$request->set_body( $raw );
		wp_set_current_user( 0 );
		return $this->server->dispatch( $request );
	}
}
