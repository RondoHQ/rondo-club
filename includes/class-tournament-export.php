<?php
/**
 * CSV and print-friendly PDF exports for tournament planners.
 *
 * @package Rondo\Tournaments
 */

namespace Rondo\Tournaments;

use Rondo\Config\FinanceConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TournamentExport {

	private TournamentService $service;

	public function __construct( ?TournamentService $service = null ) {
		$this->service = $service ?? new TournamentService();
	}

	/** Build the shared export dataset. */
	public function data( int $tournament_id ) {
		$tournament = $this->service->format_tournament( $tournament_id, true );
		if ( empty( $tournament ) ) {
			return new \WP_Error( 'rondo_tournament_not_found', __( 'Toernooi niet gevonden.', 'rondo' ), [ 'status' => 404 ] );
		}
		return [
			'tournament' => $tournament,
			'totals'     => $tournament['totals'],
			'entries'    => $this->service->entries_for_tournament( $tournament_id ),
		];
	}

	/** Return an Excel-friendly UTF-8 semicolon-separated export. */
	public function csv( int $tournament_id ) {
		$data = $this->data( $tournament_id );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$stream = fopen( 'php://temp', 'w+' );
		if ( $stream === false ) {
			return new \WP_Error( 'rondo_tournament_export_failed', __( 'De CSV-export kon niet worden gemaakt.', 'rondo' ), [ 'status' => 500 ] );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- In-memory CSV stream, not a filesystem write.
		fwrite( $stream, "\xEF\xBB\xBF" );
		$tournament = $data['tournament'];
		fputcsv( $stream, [ 'Toernooi', $tournament['name'] ], ';' );
		fputcsv( $stream, [ 'Organisator', $tournament['organizer'] ], ';' );
		fputcsv( $stream, [ 'Locatie', $tournament['location'] ], ';' );
		fputcsv( $stream, [ 'Interne deadline', $this->date_label( $tournament['internal_deadline'] ) ], ';' );
		fputcsv( $stream, [ 'Betaaldeadline', $this->date_label( $tournament['payment_deadline'] ) ], ';' );
		fputcsv( $stream, [ 'Deadline organisatie', $this->date_label( $tournament['external_deadline'] ) ], ';' );
		fputcsv( $stream, [ 'Externe voortgang', $this->external_status_label( $tournament['external_status'] ) ], ';' );
		fputcsv( $stream, [], ';' );
		fputcsv( $stream, [ 'Totalen per leeftijdslaag' ], ';' );
		fputcsv( $stream, [ 'Leeftijdslaag', 'Geselecteerde Rondo-teams', 'Ingeschreven Rondo-teams', 'Deelnemende teams', 'Spelers', 'Te ontvangen', 'Ontvangen', 'Openstaand', 'Openstaande betalingen' ], ';' );
		foreach ( $data['totals']['by_age_group'] as $row ) {
			$this->write_total_row( $stream, $row );
		}
		$this->write_total_row( $stream, [ 'age_group' => 'Totaal' ] + $data['totals']['overall'] );
		fputcsv( $stream, [], ';' );
		fputcsv( $stream, [ 'Teams en betalingen' ], ';' );
		fputcsv( $stream, [ 'Leeftijdslaag', 'Rondo-team', 'Inschrijving', 'Deelnemende teams', 'Spelers', 'Verdeling spelers', 'Contactpersoon', 'E-mail', 'Mobiel', 'Bedrag', 'Betaalstatus', 'Betaaldatum', 'Interne notitie' ], ';' );
		foreach ( $data['entries'] as $entry ) {
			$distribution = implode(
				', ',
				array_map(
					static fn( array $team ): string => sprintf( 'team %d: %d', (int) ( $team['sequence'] ?? 0 ), (int) ( $team['player_count'] ?? 0 ) ),
					$entry['submitted_team_entries']
				)
			);
			fputcsv(
				$stream,
				[
					$entry['age_group'],
					$entry['team_name'],
					$entry['registration_status'] === 'submitted' ? 'Ingeschreven' : 'Niet ingeschreven',
					$entry['registration_status'] === 'submitted' ? $entry['registered_team_count'] : '',
					$entry['registration_status'] === 'submitted' ? $entry['player_count'] : '',
					$distribution,
					$entry['registration_status'] === 'submitted' ? $entry['contact_name'] : '',
					$entry['registration_status'] === 'submitted' ? $entry['contact_email'] : '',
					$entry['registration_status'] === 'submitted' ? $entry['contact_mobile'] : '',
					$entry['registration_status'] === 'submitted' ? $this->money_label( $entry['total_amount'] ) : '',
					$this->payment_status_label( $entry ),
					$this->date_label( $entry['paid_at'] ?? '' ),
					$entry['planner_note'],
				],
				';'
			);
		}
		rewind( $stream );
		$content = stream_get_contents( $stream );
		fclose( $stream );
		return $content;
	}

	/** Return a landscape A4 PDF as a binary string. */
	public function pdf( int $tournament_id ) {
		$data = $this->data( $tournament_id );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( ! class_exists( '\\Mpdf\\Mpdf' ) ) {
			return new \WP_Error( 'rondo_tournament_pdf_unavailable', __( 'De PDF-module is niet beschikbaar.', 'rondo' ), [ 'status' => 500 ] );
		}

		$upload = wp_upload_dir();
		$temp   = trailingslashit( $upload['basedir'] ) . 'rondo-mpdf-tmp';
		wp_mkdir_p( $temp );
		try {
			$branding = $this->branding();
			$mpdf     = new \Mpdf\Mpdf(
				[
					'format'        => 'A4-L',
					'margin_left'   => 10,
					'margin_right'  => 10,
					'margin_top'    => 12,
					'margin_bottom' => 14,
					'tempDir'       => $temp,
				]
			);
			$mpdf->SetTitle( 'Toernooioverzicht - ' . $data['tournament']['name'] );
			$mpdf->SetFooter( wp_strip_all_tags( $branding['club_name'] ) . ' toernooioverzicht||{PAGENO}/{nbpg}' );
			$mpdf->WriteHTML( $this->pdf_html( $data, $branding ) );
			return $mpdf->Output( '', \Mpdf\Output\Destination::STRING_RETURN );
		} catch ( \Throwable $error ) {
			return new \WP_Error(
				'rondo_tournament_pdf_failed',
				__( 'De PDF-export kon niet worden gemaakt.', 'rondo' ),
				[
					'status' => 500,
					'detail' => sanitize_text_field( $error->getMessage() ),
				]
			);
		}
	}

	private function pdf_html( array $data, array $branding ): string {
		$tournament = $data['tournament'];
		$accent     = esc_attr( $branding['accent_color'] );
		$background = esc_attr( $branding['accent_background_color'] );
		$contrast   = esc_attr( $this->contrast_text_color( $branding['accent_color'] ) );
		$logo       = $branding['logo_path'] !== '' ? '<img src="' . esc_attr( $branding['logo_path'] ) . '" alt="" style="height:52px" />' : '';
		$html       = '<style>body{font-family:sans-serif;color:#172033;font-size:9pt}h1{font-size:20pt;margin:0;color:' . $accent . '}h2{font-size:13pt;margin:7mm 0 2mm;color:' . $accent . '}.brand-header{border-bottom:0.8mm solid ' . $accent . ';margin-bottom:5mm}.brand-header td{border:0;padding:0 0 3mm}.brand-logo{width:30%;vertical-align:middle}.brand-title{text-align:right;vertical-align:middle}.club-name{color:#4b5563;font-size:9pt;margin-bottom:1mm}.meta-table{margin-bottom:3mm}.meta-table td{width:33%;border:0;padding:0 5mm 2mm 0;color:#4b5563}table{width:100%;border-collapse:collapse}th{background:' . $accent . ';color:' . $contrast . ';text-align:left;padding:2.2mm;font-size:8pt}td{border-bottom:0.2mm solid #d7dce5;padding:2mm;vertical-align:top}.num{text-align:right;white-space:nowrap}.muted{color:#6b7280}.total td{font-weight:bold;background:' . $background . '}.note{font-size:8pt;color:#4b5563}</style>';
		$html      .= '<table class="brand-header"><tbody><tr><td class="brand-logo">' . $logo . '</td><td class="brand-title"><div class="club-name">' . esc_html( $branding['club_name'] ) . '</div><h1>' . esc_html( $tournament['name'] ) . '</h1></td></tr></tbody></table>';
		$html      .= '<table class="meta-table"><tbody><tr><td><strong>Organisator:</strong><br>' . esc_html( $tournament['organizer'] ?: '-' ) . '</td><td><strong>Locatie:</strong><br>' . esc_html( $tournament['location'] ?: '-' ) . '</td><td><strong>Externe voortgang:</strong><br>' . esc_html( $this->external_status_label( $tournament['external_status'] ) ) . '</td></tr><tr><td><strong>Interne deadline:</strong><br>' . esc_html( $this->date_label( $tournament['internal_deadline'] ) ) . '</td><td><strong>Betaaldeadline:</strong><br>' . esc_html( $this->date_label( $tournament['payment_deadline'] ) ) . '</td><td><strong>Deadline organisatie:</strong><br>' . esc_html( $this->date_label( $tournament['external_deadline'] ) ) . '</td></tr></tbody></table>';
		$html      .= '<h2>Totalen per leeftijdslaag</h2><table><thead><tr><th>Leeftijd</th><th class="num">Geselecteerd</th><th class="num">Ingeschreven</th><th class="num">Teams</th><th class="num">Spelers</th><th class="num">Te ontvangen</th><th class="num">Ontvangen</th><th class="num">Openstaand</th><th class="num">Open betalingen</th></tr></thead><tbody>';
		foreach ( $data['totals']['by_age_group'] as $row ) {
			$html .= $this->pdf_total_row( $row );
		}
		$html .= str_replace( '<tr>', '<tr class="total">', $this->pdf_total_row( [ 'age_group' => 'Totaal' ] + $data['totals']['overall'] ) );
		$html .= '</tbody></table>';
		$html .= '<h2>Teams en betalingen</h2><table><thead><tr><th>Leeftijd</th><th>Rondo-team</th><th>Inschrijving</th><th class="num">Teams</th><th class="num">Spelers</th><th>Contactpersoon</th><th class="num">Bedrag</th><th>Betaling</th><th>Interne notitie</th></tr></thead><tbody>';
		foreach ( $data['entries'] as $entry ) {
			$submitted = $entry['registration_status'] === 'submitted';
			$contact   = $submitted ? esc_html( $entry['contact_name'] ) . '<br><span class="note">' . esc_html( $entry['contact_email'] ) . '<br>' . esc_html( $entry['contact_mobile'] ) . '</span>' : '<span class="muted">-</span>';
			$html     .= '<tr><td>' . esc_html( $entry['age_group'] ) . '</td><td>' . esc_html( $entry['team_name'] ) . '</td><td>' . ( $submitted ? 'Ingeschreven' : 'Niet ingeschreven' ) . '</td><td class="num">' . ( $submitted ? (int) $entry['registered_team_count'] : '-' ) . '</td><td class="num">' . ( $submitted ? (int) $entry['player_count'] : '-' ) . '</td><td>' . $contact . '</td><td class="num">' . ( $submitted ? esc_html( $this->money_label( $entry['total_amount'] ) ) : '-' ) . '</td><td>' . esc_html( $this->payment_status_label( $entry ) ) . ( ! empty( $entry['paid_at'] ) ? '<br><span class="note">' . esc_html( $this->date_label( $entry['paid_at'] ) ) . '</span>' : '' ) . '</td><td>' . esc_html( $entry['planner_note'] ?: '-' ) . '</td></tr>';
		}
		$html .= '</tbody></table><p class="note">Deze export ondersteunt de handmatige invoer bij de externe toernooiorganisatie.</p>';
		return $html;
	}

	/** Resolve the configured club identity for the PDF. */
	private function branding(): array {
		$config     = new FinanceConfig();
		$accent     = sanitize_hex_color( $config->get_accent_color() ) ?: '#0891b2';
		$background = sanitize_hex_color( $config->get_accent_background_color() ) ?: '#f8fafc';
		$logo_path  = '';
		$logo_id    = $config->get_club_logo_id();
		if ( $logo_id > 0 ) {
			$attached_file = get_attached_file( $logo_id );
			if ( is_string( $attached_file ) && file_exists( $attached_file ) ) {
				$logo_path = $attached_file;
			}
		}

		return [
			'accent_color'            => $accent,
			'accent_background_color' => $background,
			'club_name'               => $config->get_display_name(),
			'logo_path'               => $logo_path,
		];
	}

	/** Pick readable table-header text for the configured accent color. */
	private function contrast_text_color( string $hex ): string {
		$hex       = ltrim( $hex, '#' );
		$red       = hexdec( substr( $hex, 0, 2 ) ) / 255;
		$green     = hexdec( substr( $hex, 2, 2 ) ) / 255;
		$blue      = hexdec( substr( $hex, 4, 2 ) ) / 255;
		$luminance = ( 0.2126 * $red ) + ( 0.7152 * $green ) + ( 0.0722 * $blue );
		return $luminance < 0.52 ? '#ffffff' : '#172033';
	}

	private function write_total_row( $stream, array $row ): void {
		fputcsv( $stream, [ $row['age_group'], $row['selected_team_count'], $row['submitted_entry_count'], $row['registered_team_count'], $row['player_count'], $this->money_label( $row['receivable_amount'] ), $this->money_label( $row['received_amount'] ), $this->money_label( $row['outstanding_amount'] ), $row['open_payment_count'] ], ';' );
	}

	private function pdf_total_row( array $row ): string {
		return '<tr><td>' . esc_html( $row['age_group'] ) . '</td><td class="num">' . (int) $row['selected_team_count'] . '</td><td class="num">' . (int) $row['submitted_entry_count'] . '</td><td class="num">' . (int) $row['registered_team_count'] . '</td><td class="num">' . (int) $row['player_count'] . '</td><td class="num">' . esc_html( $this->money_label( $row['receivable_amount'] ) ) . '</td><td class="num">' . esc_html( $this->money_label( $row['received_amount'] ) ) . '</td><td class="num">' . esc_html( $this->money_label( $row['outstanding_amount'] ) ) . '</td><td class="num">' . (int) $row['open_payment_count'] . '</td></tr>';
	}

	private function payment_status_label( array $entry ): string {
		if ( $entry['registration_status'] !== 'submitted' ) {
			return 'Niet ingeschreven';
		}
		return [
			'paid'           => 'Betaald',
			'open'           => 'Betaling open',
			'creating'       => 'Betaling voorbereiden',
			'error'          => 'Betaallink mislukt',
			'expired'        => 'Betaling vervallen',
			'not_applicable' => 'Geen betaling nodig',
		][ $entry['payment_state'] ] ?? 'Ingeschreven';
	}

	private function external_status_label( string $status ): string {
		return [
			'not_processed' => 'Nog niet verwerkt',
			'submitted'     => 'Ingediend bij organisatie',
			'confirmed'     => 'Bevestigd door organisatie',
		][ $status ] ?? $status;
	}

	private function money_label( $amount ): string {
		return 'EUR ' . number_format_i18n( (float) $amount, 2 );
	}

	private function date_label( string $value ): string {
		return $value !== '' ? wp_date( 'j-m-Y', strtotime( $value ) ) : '';
	}
}
