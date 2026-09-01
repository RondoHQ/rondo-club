<?php

namespace Tests\Wpunit;

use Rondo\Config\FinanceConfig;
use Rondo\Fields\Fields;
use Rondo\REST\Tournaments;
use Rondo\Tournaments\TournamentActivityLog;
use Rondo\Tournaments\TournamentExport;
use Rondo\Tournaments\TournamentProgramService;
use Rondo\Tournaments\TournamentService;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/** Covers the operational overview, exports, program delivery and activity history. */
class TournamentOperationsTest extends RondoTestCase {

	private TournamentService $service;

	protected function set_up(): void {
		parent::set_up();
		$this->service = new TournamentService();
	}

	public function test_totals_are_authoritative_per_age_group_and_overall(): void {
		$tournament_id = $this->create_tournament();
		$this->create_entry( $tournament_id, 'AWC O15-1', 'O15', 'open' );
		$paid_id = $this->create_entry( $tournament_id, 'AWC O15-2', 'O15', 'submitted', 2, 20, 50.0 );
		$open_id = $this->create_entry( $tournament_id, 'AWC O13-1', 'O13', 'submitted', 1, 10, 30.0 );
		$this->attach_invoice( $paid_id, 'paid', '' );
		$this->attach_invoice( $open_id, 'sent', 'https://pay.example.test/open' );

		$tournament = $this->service->format_tournament( $tournament_id, true );
		$overall    = $tournament['totals']['overall'];
		$this->assertSame( 3, $overall['selected_team_count'] );
		$this->assertSame( 2, $overall['submitted_entry_count'] );
		$this->assertSame( 3, $overall['registered_team_count'] );
		$this->assertSame( 30, $overall['player_count'] );
		$this->assertSame( 80.0, $overall['receivable_amount'] );
		$this->assertSame( 50.0, $overall['received_amount'] );
		$this->assertSame( 30.0, $overall['outstanding_amount'] );
		$this->assertSame( 1, $overall['open_payment_count'] );
		$this->assertSame( [ 'O15', 'O13' ], array_column( $tournament['totals']['by_age_group'], 'age_group' ) );
	}

	public function test_csv_and_pdf_exports_share_team_contact_and_payment_data(): void {
		$tournament_id = $this->create_tournament();
		$entry_id      = $this->create_entry( $tournament_id, 'AWC O15-1', 'O15', 'submitted', 2, 21, 48.0 );
		$this->attach_invoice( $entry_id, 'paid', '' );
		Fields::update_many_for_post(
			$entry_id,
			[
				'contact_name'           => 'Contact Toernooi',
				'contact_email'          => 'contact@example.test',
				'contact_mobile'         => '0612345678',
				'planner_note'           => 'Controleer afwijkende speeltijd.',
				'submitted_team_entries' => [
					[
						'sequence'     => 1,
						'player_count' => 10,
					],
					[
						'sequence'     => 2,
						'player_count' => 11,
					],
				],
			]
		);

		$export = new TournamentExport( $this->service );
		$csv    = $export->csv( $tournament_id );
		$this->assertIsString( $csv );
		$this->assertStringContainsString( 'Contact Toernooi', $csv );
		$this->assertStringContainsString( 'team 1: 10, team 2: 11', $csv );
		$this->assertStringContainsString( 'Controleer afwijkende speeltijd.', $csv );

		$pdf = $export->pdf( $tournament_id );
		$this->assertIsString( $pdf );
		$this->assertStringStartsWith( '%PDF-', $pdf );
		$this->assertGreaterThan( 5000, strlen( $pdf ) );
	}

	public function test_pdf_uses_configured_club_branding(): void {
		$logo_path     = get_template_directory() . '/public/icons/apple-touch-icon-180x180.png';
		$attachment_id = wp_insert_attachment(
			[
				'post_title'     => 'Clublogo',
				'post_mime_type' => 'image/png',
				'post_status'    => 'inherit',
			],
			$logo_path
		);
		update_attached_file( $attachment_id, $logo_path );
		update_option( FinanceConfig::OPTION_CLUB_LOGO_ID, $attachment_id );
		update_option( FinanceConfig::OPTION_ACCENT_COLOR, '#c8102e' );
		update_option( FinanceConfig::OPTION_ACCENT_BACKGROUND_COLOR, '#fff0f2' );

		$tournament_id = $this->create_tournament();
		$export        = new TournamentExport( $this->service );
		$data          = $export->data( $tournament_id );
		$branding      = ( new \ReflectionClass( $export ) )->getMethod( 'branding' )->invoke( $export );
		$html          = ( new \ReflectionClass( $export ) )->getMethod( 'pdf_html' )->invoke( $export, $data, $branding );
		$pdf           = $export->pdf( $tournament_id );

		$this->assertSame( '#c8102e', $branding['accent_color'] );
		$this->assertSame( '#fff0f2', $branding['accent_background_color'] );
		$this->assertSame( $logo_path, $branding['logo_path'] );
		$this->assertStringContainsString( 'background:#c8102e', $html );
		$this->assertStringContainsString( 'background:#fff0f2', $html );
		$this->assertStringContainsString( esc_attr( $logo_path ), $html );
		$this->assertStringContainsString( 'style="height:52px"', $html );
		$this->assertIsString( $pdf );
		$this->assertStringStartsWith( '%PDF-', $pdf );
	}

	public function test_program_preview_deduplicates_and_excludes_non_submitted_teams(): void {
		$tournament_id = $this->create_tournament();
		$user_id       = $this->createRondoUser(
			[
				'display_name' => 'Trainer',
				'user_email'   => 'shared@example.test',
			]
			);
		$submitted_id  = $this->create_entry( $tournament_id, 'AWC O15-1', 'O15', 'submitted', 1, 10, 10.0 );
		Fields::update_many_for_post(
			$submitted_id,
			[
				'assignment_snapshot' => [
					[
						'user_id' => $user_id,
						'name'    => 'Trainer',
						'email'   => 'oud@example.test',
					],
				],
				'contact_email'       => 'shared@example.test',
				'contact_name'        => 'Dezelfde ontvanger',
			]
		);
		$not_submitted_id = $this->create_entry( $tournament_id, 'AWC O13-1', 'O13', 'open' );
		Fields::update_many_for_post(
			$not_submitted_id,
			[
				'assignment_snapshot' => [
					[
						'user_id' => $this->createRondoUser( [ 'user_email' => 'excluded@example.test' ] ),
						'name'    => 'Niet ingeschreven',
					],
				],
				'contact_email'       => 'excluded-contact@example.test',
			]
		);

		$program = new TournamentProgramService();
		$preview = $program->recipients( $tournament_id );
		$this->assertSame( 1, $preview['recipient_count'] );
		$this->assertSame( 1, $preview['deduplicated_count'] );
		$this->assertSame( 1, $preview['submitted_entry_count'] );
		$this->assertSame( 'shared@example.test', $preview['recipients'][0]['email'] );
		$this->assertNotContains( 'excluded@example.test', array_column( $preview['recipients'], 'email' ) );
	}

	public function test_program_delivery_and_operational_changes_are_logged(): void {
		$admin_id      = self::factory()->user->create( [ 'role' => 'administrator' ] );
		$tournament_id = $this->create_tournament();
		$user_id       = $this->createRondoUser(
			[
				'display_name' => 'Trainer',
				'user_email'   => 'trainer@example.test',
			]
			);
		$entry_id      = $this->create_entry( $tournament_id, 'AWC O15-1', 'O15', 'submitted', 1, 10, 10.0 );
		Fields::update_many_for_post(
			$entry_id,
			[
				'assignment_snapshot' => [
					[
						'user_id' => $user_id,
						'name'    => 'Trainer',
					],
				],
				'contact_email'       => 'contact@example.test',
				'contact_name'        => 'Contact',
			]
		);

		$program = new TournamentProgramService();
		$this->assertIsArray(
			$program->save(
				$tournament_id,
				[
					'program_url'     => 'https://example.com/programma.pdf',
					'program_message' => 'Hierbij het programma.',
				],
				$admin_id
			)
		);

		$mails  = [];
		$filter = static function ( $return, array $atts ) use ( &$mails ) {
			$mails[] = $atts;
			return true;
		};
		add_filter( 'pre_wp_mail', $filter, 10, 2 );
		$sent = $program->send( $tournament_id, [ 'subject' => 'Definitief programma' ], $admin_id );
		remove_filter( 'pre_wp_mail', $filter, 10 );
		$this->assertSame( 2, $sent['sent_count'] );
		$this->assertSame( 0, $sent['failed_count'] );
		$this->assertCount( 2, $mails );
		$this->assertStringContainsString( 'Open programma', $mails[0]['message'] );

		$this->service->update_external_status( $tournament_id, 'submitted', $admin_id );
		$this->service->update_planner_note( $entry_id, 'Extern gecorrigeerd.', $admin_id );
		$actions = array_column( TournamentActivityLog::recent( $tournament_id ), 'action' );
		$this->assertContains( 'program_saved', $actions );
		$this->assertContains( 'program_sent', $actions );
		$this->assertContains( 'external_status_changed', $actions );
		$this->assertContains( 'planner_note_changed', $actions );
	}

	public function test_operational_routes_require_a_tournament_manager(): void {
		$server        = $this->bootRestControllers( [ Tournaments::class ] );
		$tournament_id = $this->create_tournament();
		$entry_id      = $this->create_entry( $tournament_id, 'AWC O15-1', 'O15', 'open' );

		wp_set_current_user( $this->createRondoUser() );
		$forbidden = new WP_REST_Request( 'PATCH', '/rondo/v1/tournaments/' . $tournament_id . '/external-status' );
		$forbidden->set_header( 'Content-Type', 'application/json' );
		$forbidden->set_body( wp_json_encode( [ 'external_status' => 'submitted' ] ) );
		$this->assertSame( 403, $server->dispatch( $forbidden )->get_status() );

		$admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin_id );
		$external = new WP_REST_Request( 'PATCH', '/rondo/v1/tournaments/' . $tournament_id . '/external-status' );
		$external->set_header( 'Content-Type', 'application/json' );
		$external->set_body( wp_json_encode( [ 'external_status' => 'confirmed' ] ) );
		$response = $server->dispatch( $external );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'confirmed', $response->get_data()['external_status'] );

		$note = new WP_REST_Request( 'PATCH', '/rondo/v1/tournament-entries/' . $entry_id . '/planner-note' );
		$note->set_header( 'Content-Type', 'application/json' );
		$note->set_body( wp_json_encode( [ 'planner_note' => 'Alleen voor planners.' ] ) );
		$note_response = $server->dispatch( $note );
		$this->assertSame( 200, $note_response->get_status() );
		$this->assertSame( 'Alleen voor planners.', $note_response->get_data()['planner_note'] );
	}

	private function create_tournament(): int {
		$id = self::factory()->post->create(
			[
				'post_type'   => TournamentService::TOURNAMENT_POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Operationeel testtoernooi',
			]
		);
		Fields::update_many_for_post(
			$id,
			[
				'external_deadline' => '2027-06-20 23:59:59',
				'external_status'   => 'not_processed',
				'internal_deadline' => '2027-06-10 23:59:59',
				'lifecycle_status'  => 'open',
				'location'          => 'Sportpark',
				'organizer'         => 'Testorganisatie',
				'payment_deadline'  => '2027-06-15 23:59:59',
			]
		);
		return $id;
	}

	private function create_entry( int $tournament_id, string $team_name, string $age_group, string $status, int $team_count = 0, int $player_count = 0, float $amount = 0.0 ): int {
		$id = self::factory()->post->create(
			[
				'post_type'   => TournamentService::ENTRY_POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $team_name,
			]
		);
		Fields::update_many_for_post(
			$id,
			[
				'age_group_snapshot'    => $age_group,
				'player_count'          => $player_count,
				'registered_team_count' => $team_count,
				'registration_status'   => $status,
				'team_name_snapshot'    => $team_name,
				'total_amount'          => $amount,
				'tournament_id'         => $tournament_id,
			]
		);
		return $id;
	}

	private function attach_invoice( int $entry_id, string $status, string $payment_link ): int {
		$invoice_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_invoice',
				'post_status' => $status === 'paid' ? 'rondo_paid' : 'rondo_sent',
				'post_title'  => 'O-test',
			]
		);
		Fields::update_many_for_post(
			$invoice_id,
			[
				'payment_link' => $payment_link,
				'status'       => $status,
			]
		);
		if ( $status === 'paid' ) {
			update_post_meta( $invoice_id, '_mollie_paid_at', '2026-09-01T12:00:00+02:00' );
		}
		Fields::update_for_post( $entry_id, 'invoice_id', $invoice_id );
		return $invoice_id;
	}
}
