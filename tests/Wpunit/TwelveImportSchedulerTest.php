<?php
namespace Tests\Wpunit;

use Rondo\Twelve\AgentMailClient;
use Rondo\Twelve\ImportScheduler;
use Tests\Support\RondoTestCase;

class TwelveImportSchedulerTest extends RondoTestCase {
	protected function tearDown(): void {
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
		$this->assertGreaterThan( time(), wp_next_scheduled( ImportScheduler::HOOK ) );
		$this->assertStringNotContainsString( 'Sensitive', wp_json_encode( get_option( ImportScheduler::STATUS ) ) );
	}

	public function test_overlap_lock_preserves_last_run_status(): void {
		update_option( ImportScheduler::STATUS, [ 'imported' => 3 ] );
		update_option( 'rondo_twelve_import_lock', time() );
		ImportScheduler::run();
		$this->assertSame( [ 'imported' => 3 ], get_option( ImportScheduler::STATUS ) );
	}
}
