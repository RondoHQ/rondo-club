<?php
namespace Tests\Wpunit;

use Rondo\Twelve\AgentMailClient;
use Rondo\Twelve\ImportScheduler;
use Tests\Support\RondoTestCase;

class TwelveImportSchedulerTest extends RondoTestCase {
	private array $mails        = [];
	private bool $mail_succeeds = true;
	private bool $mail_throws   = false;

	protected function setUp(): void {
		parent::setUp();
		update_option( 'admin_email', 'admin@example.org' );
		add_filter( 'pre_wp_mail', [ $this, 'capture_mail' ], 1, 2 );
	}

	public function capture_mail( $pre, array $mail ): bool {
		$this->mails[] = $mail;
		if ( $this->mail_throws ) {
			throw new \RuntimeException( 'Mail transport unavailable' );
		}
		return $this->mail_succeeds;
	}

	private function pdf_client( string $bytes ): AgentMailClient {
		return new class( $bytes ) extends AgentMailClient {
			private string $bytes;
			public function __construct( string $bytes ) {
				parent::__construct( [] );
				$this->bytes = $bytes;
			}
			public function download_report_pdf( string $message_id ) {
				return [
					'filename' => 'rapportage_test.pdf',
					'bytes'    => $this->bytes,
				];
			}
		};
	}

	protected function tearDown(): void {
		remove_filter( 'pre_wp_mail', [ $this, 'capture_mail' ], 1 );
		ImportScheduler::unschedule();
		AgentMailClient::clear_credentials();
		delete_option( 'rondo_twelve_import_lock' );
		delete_option( ImportScheduler::STATUS );
		parent::tearDown();
	}

	public function test_schedule_requires_credentials_and_is_idempotent(): void {
		ImportScheduler::unschedule();
		AgentMailClient::clear_credentials();
		ImportScheduler::schedule();
		$this->assertFalse( wp_next_scheduled( ImportScheduler::HOOK ) );
		AgentMailClient::store_credentials( 'test-key', 'reports@agentmail.to' );
		ImportScheduler::schedule();
		$next = wp_next_scheduled( ImportScheduler::HOOK );
		$this->assertGreaterThan( time(), $next );
		$this->assertSame( '07:00', wp_date( 'H:i', $next, new \DateTimeZone( 'Europe/Amsterdam' ) ) );
		ImportScheduler::schedule();
		$this->assertSame( $next, wp_next_scheduled( ImportScheduler::HOOK ) );
		ImportScheduler::unschedule();
		$this->assertFalse( wp_next_scheduled( ImportScheduler::HOOK ) );
	}

	public function test_cron_saves_safe_failure_status_and_releases_lock(): void {
		AgentMailClient::clear_credentials();
		delete_option( 'rondo_twelve_import_lock' );
		ImportScheduler::run();
		$status = get_option( ImportScheduler::STATUS );
		$this->assertSame( [ 'twelve_no_credentials' ], $status['errors'] );
		$this->assertSame( 0, $status['imported'] );
		$this->assertFalse( get_option( 'rondo_twelve_import_lock' ) );
	}

	public function test_cron_continues_after_download_errors_and_schedules_next_run(): void {
		ImportScheduler::unschedule();
		AgentMailClient::store_credentials( 'test-key', 'reports@agentmail.to' );
		$mock = static function ( $pre, $args, $url ) {
			if ( strpos( $url, 'api.agentmail.to' ) === false ) {
				return $pre;
			}
			if ( strpos( $url, '/messages?' ) !== false ) {
				return [
					'response' => [ 'code' => 200 ],
					'body'     => wp_json_encode(
					[
						'messages' => [
							[
								'message_id' => 'a',
								'from'       => 'noreply@twelve.eu',
							],
							[
								'message_id' => 'b',
								'from'       => 'noreply@twelve.eu',
							],
						],
					]
				),
				];
			}
			return new \WP_Error( 'offline', 'Sensitive diagnostic omitted from saved status' );
		};
		add_filter( 'pre_http_request', $mock, 10, 3 );
		try {
			ImportScheduler::run();
		} finally {
			remove_filter( 'pre_http_request', $mock, 10 );
		}
		$this->assertCount( 2, get_option( ImportScheduler::STATUS )['errors'] );
		$this->assertCount( 2, $this->mails );
		$this->assertStringNotContainsString( 'Sensitive', $this->mails[0]['message'] );
		$this->assertGreaterThan( time(), wp_next_scheduled( ImportScheduler::HOOK ) );
		$this->assertStringNotContainsString( 'Sensitive', wp_json_encode( get_option( ImportScheduler::STATUS ) ) );
	}

	public function test_overlap_lock_preserves_last_run_status(): void {
		update_option( ImportScheduler::STATUS, [ 'imported' => 3 ] );
		update_option( 'rondo_twelve_import_lock', time() );
		ImportScheduler::run();
		$this->assertSame( [ 'imported' => 3 ], get_option( ImportScheduler::STATUS ) );
	}
	public function test_parse_failure_emails_admin_and_suppresses_repeats(): void {
		$client = $this->pdf_client( '%PDF-invalid' );
		$result = ImportScheduler::import_message( $client, '<broken@example.org>' );
		$this->assertSame( 'twelve_parse_error', $result->get_error_code() );
		$this->assertCount( 1, $this->mails );
		$this->assertSame( [ 'admin@example.org' ], (array) $this->mails[0]['to'] );
		$this->assertStringContainsString( 'rapportage_test.pdf', $this->mails[0]['message'] );
		$this->assertStringContainsString( '&lt;broken@example.org&gt;', $this->mails[0]['message'] );
		$this->assertStringContainsString( '/financien/kassaomzet', $this->mails[0]['message'] );
		$this->assertSame( 'sent', get_option( 'rondo_twelve_last_alert' )['status'] );
		ImportScheduler::import_message( $client, '<broken@example.org>' );
		$this->assertCount( 1, $this->mails );
		ImportScheduler::import_message( $client, 'another-report' );
		$this->assertCount( 2, $this->mails );
		$key = 'rondo_twelve_alert_' . md5( 'admin@example.org|<broken@example.org>|twelve_parse_error' );
		delete_transient( $key );
		ImportScheduler::import_message( $client, '<broken@example.org>' );
		$this->assertCount( 3, $this->mails );
	}

	public function test_failed_mail_is_retried_and_dry_run_never_mails(): void {
		$client = $this->pdf_client( '%PDF-invalid' );
		ImportScheduler::import_message( $client, 'retry', true );
		$this->assertCount( 0, $this->mails );
		$this->mail_succeeds = false;
		ImportScheduler::import_message( $client, 'retry' );
		$this->assertSame( 'send_failed', get_option( 'rondo_twelve_last_alert' )['status'] );
		$this->mail_succeeds = true;
		ImportScheduler::import_message( $client, 'retry' );
		$this->assertCount( 2, $this->mails );
		$this->assertSame( 'sent', get_option( 'rondo_twelve_last_alert' )['status'] );
	}

	public function test_mail_exception_preserves_import_error(): void {
		$this->mail_throws = true;
		$result            = ImportScheduler::import_message( $this->pdf_client( '%PDF-invalid' ), 'throws' );
		$this->assertSame( 'twelve_parse_error', $result->get_error_code() );
		$this->assertSame( 'send_failed', get_option( 'rondo_twelve_last_alert' )['status'] );
	}

	public function test_incomplete_pdf_is_rejected_and_notified(): void {
		$pdf = new \Mpdf\Mpdf( [ 'tempDir' => sys_get_temp_dir() . '/rondo-twelve-tests' ] );
		$pdf->WriteHTML( '<p>Dagtotaal Testclub</p><p>Begindatum (incl.) 03-10-2026 06:00</p><p>Einddatum (excl.) 04-10-2026 06:00</p><p>Omzetoverzicht</p><p>Onvolledige tabel</p>' );
		$result = ImportScheduler::import_message( $this->pdf_client( $pdf->Output( '', 'S' ) ), 'incomplete' );
		$this->assertSame( 'twelve_parse_error', $result->get_error_code() );
		$this->assertStringContainsString( 'omzet totaal ontbreekt', $this->mails[0]['message'] );
		$this->assertNull( ( new \Rondo\Twelve\ReportRepository() )->find_by_message_id( 'incomplete' ) );
	}

	public function test_successful_import_and_duplicates_do_not_email(): void {
		$pdf  = new \Mpdf\Mpdf( [ 'tempDir' => sys_get_temp_dir() . '/rondo-twelve-tests' ] );
		$text = file_get_contents( __DIR__ . '/../fixtures/twelve-rapportage.txt' );
		$pdf->WriteHTML( '<pre>' . esc_html( $text ) . '</pre>' );
		$client = $this->pdf_client( $pdf->Output( '', 'S' ) );
		$result = ImportScheduler::import_message( $client, 'valid' );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'post_id', $result );
		$this->assertSame( [ 'skipped' => true ], ImportScheduler::import_message( $client, 'valid' ) );
		$this->assertCount( 0, $this->mails );
	}
}
