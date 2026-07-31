<?php

namespace Tests\Wpunit;

use Rondo\Volunteer\IvaCertificateParser;
use Tests\Support\RondoTestCase;

/**
 * Tests for IvaCertificateParser — text-layer parsing of official IVA
 * e-learning certificates and the auto-approval rules built on top.
 */
class IvaCertificateParserTest extends RondoTestCase {

	/**
	 * smalot/pdfparser extraction order: values glued, name last.
	 */
	public function test_parses_smalot_text_order(): void {
		$parsed = IvaCertificateParser::parse_text( "Sport\nVoor elkaar210777\n31 mei 2026\nJoost de Valk\nPowered by TCPDF (www.tcpdf.org)" );

		$this->assertSame( 'Joost de Valk', $parsed['name'] );
		$this->assertSame( '2026-05-31', $parsed['datum'] );
		$this->assertSame( '210777', $parsed['user_id'] );
	}

	/**
	 * pdftotext extraction order: one value per line, name first.
	 */
	public function test_parses_pdftotext_text_order(): void {
		$parsed = IvaCertificateParser::parse_text( "Joost de Valk\n\nSport\n\n210777\n\nVoor elkaar\n\n31 mei 2026\n" );

		$this->assertSame( 'Joost de Valk', $parsed['name'] );
		$this->assertSame( '2026-05-31', $parsed['datum'] );
		$this->assertSame( '210777', $parsed['user_id'] );
	}

	public function test_rejects_pdf_without_voor_elkaar_marker(): void {
		$this->assertNull( IvaCertificateParser::parse_text( "Factuur 2026-001\n12 maart 2026\nJansen BV" ) );
	}

	public function test_rejects_text_without_date(): void {
		$this->assertNull( IvaCertificateParser::parse_text( "Voor elkaar\nJoost de Valk\n210777" ) );
	}

	public function test_rejects_invalid_calendar_date(): void {
		$this->assertNull( IvaCertificateParser::parse_text( "Voor elkaar\nJoost de Valk\n31 februari 2026" ) );
	}

	public function test_datum_auto_approvable_window(): void {
		$today = gmdate( 'Y-m-d' );

		$this->assertTrue( IvaCertificateParser::is_datum_auto_approvable( $today ) );
		$this->assertTrue( IvaCertificateParser::is_datum_auto_approvable( gmdate( 'Y-m-d', strtotime( '-23 months' ) ) ) );
		$this->assertFalse( IvaCertificateParser::is_datum_auto_approvable( gmdate( 'Y-m-d', strtotime( '-25 months' ) ) ), 'older than 2 years' );
		$this->assertFalse( IvaCertificateParser::is_datum_auto_approvable( gmdate( 'Y-m-d', strtotime( '+2 days' ) ) ), 'future date' );
		$this->assertFalse( IvaCertificateParser::is_datum_auto_approvable( 'niet-een-datum' ) );
		$this->assertFalse( IvaCertificateParser::is_datum_auto_approvable( '' ) );
	}

	public function test_name_matches_person_via_post_title(): void {
		$person_id = self::factory()->post->create(
			[
				'post_type'  => 'person',
				'post_title' => 'Joost de Valk',
			]
		);

		$this->assertTrue( IvaCertificateParser::name_matches_person( 'Joost de Valk', $person_id ) );
		$this->assertTrue( IvaCertificateParser::name_matches_person( '  joost  DE valk ', $person_id ), 'case/whitespace-insensitive' );
		$this->assertTrue( IvaCertificateParser::name_matches_person( 'Joost de Valck', $person_id ), 'small typo tolerated' );
		$this->assertFalse( IvaCertificateParser::name_matches_person( 'Piet Pietersen', $person_id ) );
		$this->assertFalse( IvaCertificateParser::name_matches_person( '', $person_id ) );
	}

	public function test_name_matches_person_via_name_fields(): void {
		$person_id = self::factory()->post->create(
			[
				'post_type'  => 'person',
				'post_title' => 'J. de Valk',
			]
		);
		update_post_meta( $person_id, 'first_name', 'Johannes' );
		update_post_meta( $person_id, 'last_name', 'de Valk' );
		update_post_meta( $person_id, 'nickname', 'Joost' );

		$this->assertTrue( IvaCertificateParser::name_matches_person( 'Johannes de Valk', $person_id ) );
		$this->assertTrue( IvaCertificateParser::name_matches_person( 'Joost de Valk', $person_id ), 'roepnaam + achternaam' );
	}

	public function test_short_names_get_no_typo_tolerance(): void {
		$person_id = self::factory()->post->create(
			[
				'post_type'  => 'person',
				'post_title' => 'Jan Bos',
			]
		);

		$this->assertFalse( IvaCertificateParser::name_matches_person( 'Jan Vos', $person_id ) );
	}

	public function test_should_auto_approve_requires_both_name_and_recent_date(): void {
		$person_id = self::factory()->post->create(
			[
				'post_type'  => 'person',
				'post_title' => 'Joost de Valk',
			]
		);

		$recent = [
			'name'  => 'Joost de Valk',
			'datum' => gmdate( 'Y-m-d', strtotime( '-6 months' ) ),
		];
		$this->assertTrue( IvaCertificateParser::should_auto_approve( $recent, $person_id ) );

		$wrong_name = array_merge( $recent, [ 'name' => 'Piet Pietersen' ] );
		$this->assertFalse( IvaCertificateParser::should_auto_approve( $wrong_name, $person_id ) );

		$too_old = array_merge( $recent, [ 'datum' => gmdate( 'Y-m-d', strtotime( '-3 years' ) ) ] );
		$this->assertFalse( IvaCertificateParser::should_auto_approve( $too_old, $person_id ) );
	}
}
