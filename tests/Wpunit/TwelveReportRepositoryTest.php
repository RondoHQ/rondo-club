<?php

namespace Tests\Wpunit;

use Rondo\Twelve\ReportParser;
use Rondo\Twelve\ReportRepository;
use Tests\Support\RondoTestCase;

/** Tests for storing and querying Twelve reports. */
class TwelveReportRepositoryTest extends RondoTestCase {

	private function parsed(): array {
		return ReportParser::parse( (string) file_get_contents( __DIR__ . '/../fixtures/twelve-rapportage.txt' ) );
	}

	private function pdf_bytes(): string {
		return "%PDF-1.4\n%fake pdf for tests\n";
	}

	public function test_store_and_query(): void {
		$repository = new ReportRepository();
		$post_id    = $repository->store( $this->parsed(), 'msg-123', 'rapportage.pdf', $this->pdf_bytes() );

		$this->assertNotInstanceOf( \WP_Error::class, $post_id );
		$this->assertSame( 'rondo_twelve_report', get_post_type( $post_id ) );
		$this->assertSame( '2026-09-29 06:00:00', get_post_meta( $post_id, ReportRepository::META_PERIOD_START, true ) );
		$this->assertSame( '2026-09-30 06:00:00', get_post_meta( $post_id, ReportRepository::META_PERIOD_END, true ) );
		$this->assertSame( 'msg-123', get_post_meta( $post_id, ReportRepository::META_MESSAGE_ID, true ) );
		$this->assertSame( 43.45, (float) get_post_meta( $post_id, ReportRepository::META_TOTAL_GROSS, true ) );

		$attachment_id = (int) get_post_meta( $post_id, ReportRepository::META_PDF, true );
		$this->assertGreaterThan( 0, $attachment_id );
		$this->assertSame( 'application/pdf', get_post_mime_type( $attachment_id ) );

		$reports = $repository->query( '2026-09-01', '2026-09-30' );
		$this->assertCount( 1, $reports );
		$this->assertSame( $post_id, $reports[0]['id'] );
		$this->assertSame( 'AWC Wijchen, SV', $reports[0]['data']['club'] );
		$this->assertCount( 9, $reports[0]['data']['producten'] );

		$this->assertCount( 0, $repository->query( '2026-08-01', '2026-08-31' ) );
	}

	public function test_store_is_idempotent_on_message_id(): void {
		$repository = new ReportRepository();
		$parsed     = $this->parsed();

		$first = $repository->store( $parsed, 'msg-123', 'rapportage.pdf', $this->pdf_bytes() );
		$this->assertNotInstanceOf( \WP_Error::class, $first );

		$second = $repository->store( $parsed, 'msg-123', 'rapportage.pdf', $this->pdf_bytes() );
		$this->assertInstanceOf( \WP_Error::class, $second );
		$this->assertSame( 'twelve_duplicate_report', $second->get_error_code() );
		$this->assertSame( $first, $second->get_error_data()['post_id'] );
	}

	public function test_store_is_idempotent_on_period_end(): void {
		$repository = new ReportRepository();
		$parsed     = $this->parsed();

		$first = $repository->store( $parsed, 'msg-123', 'rapportage.pdf', $this->pdf_bytes() );
		$this->assertNotInstanceOf( \WP_Error::class, $first );

		// Same period, different Gmail message id (e.g. re-sent mail).
		$second = $repository->store( $parsed, 'msg-456', 'rapportage.pdf', $this->pdf_bytes() );
		$this->assertInstanceOf( \WP_Error::class, $second );
		$this->assertSame( 'twelve_duplicate_report', $second->get_error_code() );
	}

	public function test_latest_returns_newest_first(): void {
		$repository = new ReportRepository();

		$first_parsed                  = $this->parsed();
		$second_parsed                 = $this->parsed();
		$second_parsed['period_start'] = '2026-09-30 06:00';
		$second_parsed['period_end']   = '2026-10-01 06:00';

		$repository->store( $first_parsed, 'msg-1', 'a.pdf', $this->pdf_bytes() );
		$repository->store( $second_parsed, 'msg-2', 'b.pdf', $this->pdf_bytes() );

		$latest = $repository->latest( 10 );
		$this->assertCount( 2, $latest );
		$this->assertSame( '2026-09-30 06:00:00', $latest[0]['period_start'] );
		$this->assertSame( '2026-09-29 06:00:00', $latest[1]['period_start'] );
	}
}
