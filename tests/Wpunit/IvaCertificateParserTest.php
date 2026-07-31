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
		$this->assertSame( 'voor_elkaar', $parsed['format'] );
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

	public function test_parses_vrijwilligerswerknl_certificate_from_tcpdf_metadata(): void {
		$parsed = IvaCertificateParser::parse_text(
			"t van de Groes\n30 juli 2026\nNiet ingesteld\nPowered by TCPDF (www.tcpdf.org)",
			[
				'Title'    => '_Certificaat_IVA_-_VrijwilligerswerkNL.pdf',
				'Producer' => 'TCPDF (http://www.tcpdf.org)',
			]
		);

		$this->assertSame( 't van de Groes', $parsed['name'] );
		$this->assertSame( '2026-07-30', $parsed['datum'] );
		$this->assertSame( '', $parsed['user_id'] );
		$this->assertSame( 'vrijwilligerswerknl', $parsed['format'] );
	}

	public function test_rejects_vrijwilligerswerknl_text_without_matching_tcpdf_metadata(): void {
		$text = "t van de Groes\n30 juli 2026\nNiet ingesteld\nPowered by TCPDF (www.tcpdf.org)";

		$this->assertNull( IvaCertificateParser::parse_text( $text ) );
		$this->assertNull(
			IvaCertificateParser::parse_text(
				$text,
				[
					'Title'    => '_Certificaat_IVA_-_VrijwilligerswerkNL.pdf',
					'Producer' => 'Onbekende PDF-generator',
				]
			)
		);
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

	public function test_vrijwilligerswerknl_allows_known_truncated_given_name(): void {
		$person_id = self::factory()->post->create(
			[
				'post_type'  => 'person',
				'post_title' => 'Jurgen van de Groes',
			]
		);
		update_post_meta( $person_id, 'first_name', 'Jurgen' );
		update_post_meta( $person_id, 'last_name', 'Groes' );

		$parsed = [
			'name'   => 't van de Groes',
			'datum'  => gmdate( 'Y-m-d' ),
			'format' => 'vrijwilligerswerknl',
		];

		$this->assertTrue( IvaCertificateParser::should_auto_approve( $parsed, $person_id ) );
		$this->assertFalse(
			IvaCertificateParser::should_auto_approve(
				array_merge( $parsed, [ 'format' => 'voor_elkaar' ] ),
				$person_id
			),
			'Legacy certificates retain the full-name match.'
		);
	}

	public function test_vrijwilligerswerknl_truncated_match_requires_multiword_surname(): void {
		$person_id = self::factory()->post->create(
			[
				'post_type'  => 'person',
				'post_title' => 'Jan Jansen',
			]
		);
		update_post_meta( $person_id, 'first_name', 'Jan' );

		$this->assertFalse(
			IvaCertificateParser::should_auto_approve(
				[
					'name'   => 'n Jansen',
					'datum'  => gmdate( 'Y-m-d' ),
					'format' => 'vrijwilligerswerknl',
				],
				$person_id
			)
		);
	}
}
