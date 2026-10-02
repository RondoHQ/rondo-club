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
		} finally {
			update_option( self::STATUS, $status, false );
			delete_option( self::LOCK );
		}
	}

	/** Parse and optionally persist one report; return safe status or an error. */
	public static function import_message( AgentMailClient $client, string $id, bool $dry_run = false ) {
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
			return new \WP_Error( 'twelve_parse_error', 'PDF parsen mislukt: ' . $e->getMessage() );
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
}
