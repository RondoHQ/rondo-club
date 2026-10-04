<?php
/** Daily Twelve report import, shared by WordPress cron and WP-CLI. */
namespace Rondo\Twelve;

class ImportScheduler {
	public const HOOK   = 'rondo_twelve_daily_import';
	public const STATUS = 'rondo_twelve_import_status';
	private const LOCK  = 'rondo_twelve_import_lock';

	public function __construct() {
		add_action( 'init', [ self::class, 'schedule' ] );
		add_action( self::HOOK, [ self::class, 'run' ] );
		add_action( 'switch_theme', [ self::class, 'unschedule' ] );
	}

	public static function schedule(): void {
		if ( ! AgentMailClient::has_credentials() || wp_next_scheduled( self::HOOK ) ) {
			return;
		}
		$next = new \DateTimeImmutable( 'today 07:00', new \DateTimeZone( 'Europe/Amsterdam' ) );
		if ( $next->getTimestamp() <= time() ) {
			$next = $next->modify( '+1 day' );
		}
		// Single events retain 07:00 local time across daylight-saving changes.
		wp_schedule_single_event( $next->getTimestamp(), self::HOOK );
	}

	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::HOOK );
	}

	public static function run(): void {
		self::schedule();
		if ( ! add_option( self::LOCK, time(), '', false ) ) {
			return;
		}
		$status = [
			'ran_at'   => gmdate( 'c' ),
			'imported' => 0,
			'skipped'  => 0,
			'errors'   => [],
		];
		try {
			$client = AgentMailClient::from_stored_credentials();
			$ids    = is_wp_error( $client ) ? $client : $client->list_report_message_ids();
			if ( is_wp_error( $ids ) ) {
				$status['errors'][] = $ids->get_error_code();
				self::notify_failure( '', $ids );
			} else {
				foreach ( $ids as $id ) {
					$result = self::import_message( $client, $id );
					if ( is_wp_error( $result ) ) {
						$status['errors'][] = $result->get_error_code();
					} elseif ( ! empty( $result['skipped'] ) ) {
						++$status['skipped'];
					} else {
						++$status['imported'];
					}
				}
			}
		} catch ( \Throwable $e ) {
			$status['errors'][] = 'twelve_import_exception';
			self::notify_failure( '', new \WP_Error( 'twelve_import_exception', 'De import is onverwacht afgebroken.' ) );
		} finally {
			update_option( self::STATUS, $status, false );
			delete_option( self::LOCK );
		}
	}

	/** Parse and optionally persist one report; return safe status or an error. */
	public static function import_message( AgentMailClient $client, string $id, bool $dry_run = false ) {
		try {
			$result = self::parse_and_store( $client, $id, $dry_run );
		} catch ( \Throwable $e ) {
			$result = new \WP_Error( 'twelve_import_exception', 'Het rapport kon niet volledig worden verwerkt.' );
		}
		if ( ! $dry_run && is_wp_error( $result ) ) {
			self::notify_failure( $id, $result );
		}
		return $result;
	}

	private static function parse_and_store( AgentMailClient $client, string $id, bool $dry_run ) {
		$repository = new ReportRepository();
		if ( $repository->find_by_message_id( $id ) !== null ) {
			return [ 'skipped' => true ];
		}
		$pdf = $client->download_report_pdf( $id );
		if ( is_wp_error( $pdf ) ) {
			return $pdf;
		}
		try {
			$parsed = ReportParser::parse( ( new \Smalot\PdfParser\Parser() )->parseContent( $pdf['bytes'] )->getText() );
		} catch ( \Throwable $e ) {
			$reason = $e instanceof ReportParserException ? $e->getMessage() : 'De PDF kon niet worden uitgelezen.';
			return new \WP_Error( 'twelve_parse_error', $reason, [ 'filename' => sanitize_file_name( $pdf['filename'] ) ] );
		}
		if ( $dry_run ) {
			return [ 'parsed' => $parsed ];
		}
		$post_id = $repository->store( $parsed, $id, $pdf['filename'], $pdf['bytes'] );
		if ( is_wp_error( $post_id ) ) {
			return $post_id->get_error_code() === 'twelve_duplicate_report' ? [ 'skipped' => true ] : $post_id;
		}
		return [
			'parsed'  => $parsed,
			'post_id' => $post_id,
		];
	}
	/** Notify the administration address; successful alerts are limited to one per error per day. */
	private static function notify_failure( string $message_id, \WP_Error $error ): void {
		$recipient = sanitize_email( (string) get_option( 'admin_email' ) );
		$key       = 'rondo_twelve_alert_' . md5( $recipient . '|' . $message_id . '|' . $error->get_error_code() );
		if ( get_transient( $key ) ) {
			return;
		}
		$status = 'send_failed';
		try {
			if ( ! is_email( $recipient ) ) {
				$status = 'no_recipient';
				return;
			}
			$data     = $error->get_error_data();
			$filename = is_array( $data ) ? (string) ( $data['filename'] ?? '' ) : '';
			// Only controlled diagnostics go into mail; transport errors can contain secrets.
			$reason = $error->get_error_code() === 'twelve_parse_error'
				? $error->get_error_message()
				: 'Het rapport kon niet worden opgehaald of opgeslagen. Controleer de import.';
			$body   = '<p>De Twelve-import kon niet volledig worden afgerond. De omzetrapportage kan hierdoor onvolledig zijn.</p>'
				. '<p><strong>Bestand:</strong> ' . esc_html( $filename ?: 'Nog niet beschikbaar' ) . '<br>'
				. '<strong>Bericht:</strong> ' . esc_html( $message_id ?: 'Inbox ophalen' ) . '<br>'
				. '<strong>Foutcode:</strong> ' . esc_html( $error->get_error_code() ) . '<br>'
				. '<strong>Reden:</strong> ' . esc_html( $reason ) . '</p>'
				. '<p>Andere rapporten worden verder verwerkt. Rondo probeert dit bij de volgende dagelijkse import opnieuw. Dezelfde fout wordt maximaal eenmaal per 24 uur gemeld.</p>';
			$html   = \Rondo\Notifications\EmailTemplate::render(
				[
					'heading'   => 'Twelve-rapportage niet verwerkt',
					'body_html' => $body,
					'cta_url'   => home_url( '/financien/kassaomzet' ),
					'cta_label' => 'Bekijk Kassaomzet',
				]
			);
			if ( wp_mail( $recipient, 'Rondo: Twelve-rapportage niet volledig verwerkt', $html, [ 'Content-Type: text/html; charset=UTF-8' ] ) ) {
				set_transient( $key, true, DAY_IN_SECONDS );
				$status = 'sent';
			}
		} catch ( \Throwable $e ) {
			// Mail failures must never stop other reports or prevent the import lock from releasing.
			$status = 'send_failed';
		} finally {
			update_option(
				'rondo_twelve_last_alert',
				[
					'status'       => $status,
					'attempted_at' => gmdate( 'c' ),
				],
				false
				);
		}
	}
}
