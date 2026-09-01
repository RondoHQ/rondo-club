<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\Config\FinanceConfig;
use Rondo\Finance\MollieConfig;
use Rondo\Tournaments\TournamentPaymentService;
use Rondo\Tournaments\TournamentPaymentReminderScheduler;
use Rondo\Tournaments\TournamentPaymentRetryScheduler;
use Rondo\Tournaments\TournamentService;
use Tests\Support\RondoTestCase;
use Tests\Support\TournamentPaymentMollieStub;

/** Covers idempotent tournament invoices and permission-safe payment summaries. */
class TournamentPaymentTest extends RondoTestCase {

	public function test_tournament_account_has_a_dedicated_setting(): void {
		update_option( MollieConfig::OPTION_MOLLIE_DEFAULT_TOURNAMENT_ACCOUNT_ID, 'toernooi-rekening' );
		$config = new MollieConfig( new FinanceConfig() );

		$this->assertSame( 'toernooi-rekening', $config->get_default_mollie_account_id( 'tournament' ) );
		$this->assertNotSame( $config->get_default_mollie_account_id( 'manual' ), $config->get_default_mollie_account_id( 'tournament' ) );
	}

	public function test_submitted_entry_gets_one_tournament_invoice_and_payment_link(): void {
		$actor_id  = $this->createRondoUser();
		$person_id = $this->createPerson( [ 'post_title' => 'Betalende trainer' ] );
		update_user_meta( $actor_id, 'rondo_linked_person_id', $person_id );

		$entry_id = $this->create_submitted_entry( 2, 24.0 );
		$mollie   = new TournamentPaymentMollieStub();
		$service  = new TournamentPaymentService( $mollie, [ $this, 'payment_account' ] );

		$first = $service->ensure_payment( $entry_id, $actor_id );
		$this->assertIsArray( $first );
		$this->assertSame( 'open', $first['payment_state'] );
		$this->assertSame( 'https://pay.example.test/tournament', $first['payment_url'] );
		$this->assertSame( 1, $mollie->create_calls );

		$invoice_id = (int) $first['invoice_id'];
		$this->assertSame( 'rondo_sent', get_post_status( $invoice_id ) );
		$this->assertSame( 'tournament', Fields::get_for_post( $invoice_id, 'invoice_type' ) );
		$this->assertMatchesRegularExpression( '/^' . gmdate( 'Y' ) . 'O\d{3,}$/', Fields::get_for_post( $invoice_id, 'invoice_number' ) );
		$this->assertSame( 48.0, (float) Fields::get_for_post( $invoice_id, 'total_amount' ) );
		$this->assertSame( $person_id, (int) Fields::get_for_post( $invoice_id, 'person' ) );
		$this->assertSame( $entry_id, (int) get_post_meta( $invoice_id, '_tournament_entry_id', true ) );
		$this->assertSame( 'toernooien', get_post_meta( $invoice_id, '_payment_account_id', true ) );
		$this->assertSame( '20270518', Fields::get_for_post( $invoice_id, 'due_date' ) );
		$this->assertStringContainsString( '2 teams · 21 spelers', get_post_meta( $invoice_id, '_mollie_description', true ) );

		$second = $service->ensure_payment( $entry_id, $actor_id );
		$this->assertSame( $invoice_id, $second['invoice_id'] );
		$this->assertSame( 1, $mollie->create_calls );
		$this->assertCount(
			1,
			get_posts(
				[
					'post_type'      => 'rondo_invoice',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'meta_key'       => '_tournament_entry_id',
					'meta_value'     => $entry_id,
				]
			)
		);
	}

	public function test_paid_invoice_is_derived_without_exposing_the_old_link(): void {
		$entry_id = $this->create_submitted_entry( 1, 15.0 );
		$service  = new TournamentPaymentService( new TournamentPaymentMollieStub(), [ $this, 'payment_account' ] );
		$created  = $service->ensure_payment( $entry_id, self::factory()->user->create() );
		$invoice  = (int) $created['invoice_id'];

		wp_update_post(
			[
				'ID'          => $invoice,
				'post_status' => 'rondo_paid',
			]
		);
		Fields::update_for_post( $invoice, 'status', 'paid' );
		update_post_meta( $invoice, '_mollie_paid_at', '2026-09-01T12:34:56+02:00' );

		$summary = $service->payment_summary( $entry_id );
		$this->assertSame( 'paid', $summary['payment_state'] );
		$this->assertNull( $summary['payment_url'] );
		$this->assertSame( '2026-09-01T12:34:56+02:00', $summary['paid_at'] );
	}

	public function test_missing_tournament_account_keeps_registration_and_exposes_retryable_error(): void {
		$entry_id = $this->create_submitted_entry( 1, 10.0 );
		$service  = new TournamentPaymentService(
			new TournamentPaymentMollieStub(),
			static fn() => new \WP_Error( 'missing_tournament_account', 'Kies eerst een standaard Mollie-rekening voor toernooien.' )
		);

		$result = $service->ensure_payment( $entry_id, self::factory()->user->create() );
		$this->assertWPError( $result );
		$this->assertSame( 'submitted', Fields::get_for_post( $entry_id, 'registration_status' ) );
		$this->assertSame( 'error', Fields::get_for_post( $entry_id, 'payment_state' ) );
		$this->assertSame( 'error', $service->payment_summary( $entry_id )['payment_state'] );
		$this->assertSame( 0, (int) Fields::get_for_post( $entry_id, 'invoice_id' ) );
		$this->assertNotFalse( wp_next_scheduled( TournamentPaymentRetryScheduler::CRON_HOOK, [ $entry_id ] ) );
	}

	public function test_failed_payment_link_is_retried_automatically(): void {
		$entry_id             = $this->create_submitted_entry( 1, 10.0 );
		$mollie               = new TournamentPaymentMollieStub();
		$mollie->create_error = new \WP_Error( 'temporary_provider_error', 'Tijdelijke storing.' );
		$service              = new TournamentPaymentService( $mollie, [ $this, 'payment_account' ] );

		$this->assertWPError( $service->ensure_payment( $entry_id, self::factory()->user->create() ) );
		$this->assertNotFalse( wp_next_scheduled( TournamentPaymentRetryScheduler::CRON_HOOK, [ $entry_id ] ) );
		$this->assertSame( 1, (int) get_post_meta( $entry_id, '_tournament_payment_retry_attempts', true ) );

		$mollie->create_error = null;
		( new TournamentPaymentRetryScheduler( $service ) )->retry( $entry_id );

		$this->assertSame( 'open', $service->payment_summary( $entry_id )['payment_state'] );
		$this->assertFalse( wp_next_scheduled( TournamentPaymentRetryScheduler::CRON_HOOK, [ $entry_id ] ) );
		$this->assertSame( '', get_post_meta( $entry_id, '_tournament_payment_retry_attempts', true ) );
		$this->assertSame( 2, $mollie->create_calls );
	}

	public function test_free_entry_needs_no_invoice(): void {
		$entry_id = $this->create_submitted_entry( 1, 0.0 );
		$mollie   = new TournamentPaymentMollieStub();
		$service  = new TournamentPaymentService( $mollie, [ $this, 'payment_account' ] );

		$summary = $service->ensure_payment( $entry_id, self::factory()->user->create() );
		$this->assertSame( 'not_applicable', $summary['payment_state'] );
		$this->assertNull( $summary['invoice_id'] );
		$this->assertSame( 0, $mollie->create_calls );
	}

	public function test_cancelling_an_entry_disables_its_unpaid_invoice(): void {
		$entry_id = $this->create_submitted_entry( 1, 12.0 );
		$mollie   = new TournamentPaymentMollieStub();
		$service  = new TournamentPaymentService( $mollie, [ $this, 'payment_account' ] );
		$created  = $service->ensure_payment( $entry_id, self::factory()->user->create() );

		$service->cancel_unpaid_payment( $entry_id );

		$invoice_id = (int) $created['invoice_id'];
		$this->assertSame( 1, $mollie->archive_calls );
		$this->assertSame( 'rondo_cancelled', get_post_status( $invoice_id ) );
		$this->assertSame( 'cancelled', Fields::get_for_post( $invoice_id, 'status' ) );
		$this->assertSame( '', (string) Fields::get_for_post( $invoice_id, 'payment_link' ) );
		$this->assertSame( '', get_post_meta( $invoice_id, '_mollie_payment_link_id', true ) );
	}

	public function test_payment_email_and_configured_reminder_are_idempotent(): void {
		$user_id  = $this->createRondoUser(
			[
				'display_name' => 'Trainer',
				'user_email'   => 'trainer@example.test',
			]
			);
		$entry_id = $this->create_submitted_entry( 1, 12.0 );
		Fields::update_for_post(
			$entry_id,
			'assignment_snapshot',
			[
				[
					'user_id' => $user_id,
					'name'    => 'Trainer',
					'email'   => 'trainer@example.test',
				],
			]
		);
		$tournament_id = (int) Fields::get_for_post( $entry_id, 'tournament_id' );
		Fields::update_many_for_post(
			$tournament_id,
			[
				'payment_deadline'      => current_datetime()->modify( '+7 days' )->format( 'Y-m-d H:i:s' ),
				'payment_reminder_days' => [ [ 'days_before' => 7 ] ],
			]
		);

		$mails  = [];
		$filter = static function ( $return, array $atts ) use ( &$mails ) {
			$mails[] = $atts;
			return true;
		};
		add_filter( 'pre_wp_mail', $filter, 10, 2 );
		$service = new TournamentPaymentService( new TournamentPaymentMollieStub(), [ $this, 'payment_account' ] );
		$service->ensure_payment( $entry_id, $user_id );
		$service->ensure_payment( $entry_id, $user_id );
		$this->assertCount( 1, $mails );
		$this->assertStringContainsString( 'Open betaallink', $mails[0]['message'] );

		$scheduler = new TournamentPaymentReminderScheduler();
		$scheduler->process_entry( $entry_id );
		$scheduler->process_entry( $entry_id );
		$this->assertCount( 2, $mails );
		$this->assertNotEmpty( get_post_meta( $entry_id, '_tournament_payment_reminder_7_sent_at', true ) );
		remove_filter( 'pre_wp_mail', $filter, 10 );
	}

	public function test_unpaid_entry_can_reopen_and_new_submission_gets_new_invoice(): void {
		$entry_id = $this->create_submitted_entry( 1, 12.0 );
		$mollie   = new TournamentPaymentMollieStub();
		$payments = new TournamentPaymentService( $mollie, [ $this, 'payment_account' ] );
		$service  = new TournamentService( $payments );
		$first    = $payments->ensure_payment( $entry_id, self::factory()->user->create() );
		update_post_meta( $entry_id, '_tournament_payment_email_sent_10', current_time( 'mysql' ) );
		update_post_meta( $entry_id, '_tournament_payment_reminder_7_sent_at', current_time( 'mysql' ) );
		Fields::update_for_post(
			$entry_id,
			'assignment_snapshot',
			[
				[
					'user_id' => 10,
					'email'   => 'trainer@example.test',
				],
			]
			);
		$reopened = $service->reopen_entry( $entry_id, self::factory()->user->create() );

		$this->assertIsArray( $reopened );
		$this->assertSame( 'open', $reopened['registration_status'] );
		$this->assertNull( $reopened['invoice_id'] );
		$this->assertSame( '', get_post_meta( $entry_id, '_tournament_payment_email_sent_10', true ) );
		$this->assertSame( '', get_post_meta( $entry_id, '_tournament_payment_reminder_7_sent_at', true ) );
		$this->assertSame( 'rondo_cancelled', get_post_status( (int) $first['invoice_id'] ) );

		Fields::update_many_for_post(
			$entry_id,
			[
				'registration_status'   => 'submitted',
				'registered_team_count' => 1,
				'total_amount'          => 12,
			]
		);
		$second = $payments->ensure_payment( $entry_id, self::factory()->user->create() );
		$this->assertNotSame( (int) $first['invoice_id'], (int) $second['invoice_id'] );
	}

	public function test_paid_entry_cannot_reopen(): void {
		$entry_id = $this->create_submitted_entry( 1, 12.0 );
		$payments = new TournamentPaymentService( new TournamentPaymentMollieStub(), [ $this, 'payment_account' ] );
		$created  = $payments->ensure_payment( $entry_id, self::factory()->user->create() );
		wp_update_post(
			[
				'ID'          => (int) $created['invoice_id'],
				'post_status' => 'rondo_paid',
			]
			);
		Fields::update_for_post( (int) $created['invoice_id'], 'status', 'paid' );

		$result = ( new TournamentService( $payments ) )->reopen_entry( $entry_id, self::factory()->user->create() );
		$this->assertWPError( $result );
		$this->assertSame( 'rondo_tournament_payment_already_paid', $result->get_error_code() );
	}

	public function payment_account(): array {
		return [
			'id'              => 'toernooien',
			'internal_name'   => 'Toernooien',
			'account_holder'  => 'AWC',
			'iban'            => 'NL00TEST0000000000',
			'linked_provider' => 'mollie',
		];
	}

	private function create_submitted_entry( int $team_count, float $price ): int {
		$tournament_id = self::factory()->post->create(
			[
				'post_type'   => TournamentService::TOURNAMENT_POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Testtoernooi',
			]
		);
		Fields::update_many_for_post(
			$tournament_id,
			[
				'external_deadline' => '2027-05-20 23:59:59',
				'payment_deadline'  => '2027-05-18 23:59:59',
				'lifecycle_status'  => 'open',
			]
		);

		$entry_id = self::factory()->post->create(
			[
				'post_type'   => TournamentService::ENTRY_POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => 'Testtoernooi · AWC O15-1',
			]
		);
		Fields::update_many_for_post(
			$entry_id,
			[
				'contact_email'         => 'trainer@example.test',
				'contact_name'          => 'Trainer',
				'player_count'          => 21,
				'price_per_team'        => $price,
				'registered_team_count' => $team_count,
				'registration_status'   => 'submitted',
				'team_name_snapshot'    => 'AWC O15-1',
				'total_amount'          => $team_count * $price,
				'tournament_id'         => $tournament_id,
			]
		);

		return $entry_id;
	}
}
