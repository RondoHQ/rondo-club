<?php
/**
 * Private, single-payment SEPA exports for credit invoices.
 *
 * @package Rondo\Finance
 */

namespace Rondo\Finance;

use Rondo\Fields\Fields;

/** Never contacts a bank or changes the settlement status of an invoice. */
class CreditSepaExport {

	private const META = '_credit_sepa_export';
	private const NS   = 'urn:iso:std:iso:20022:tech:xsd:pain.001.001.09';

	/** Read defaults and the existing export without creating anything. */
	public static function prepare( int $id, array $account ) {
		if ( $id <= 0 ) {
			return self::error( 'Creditfactuur niet gevonden.', 404 );
		}
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== 'rondo_invoice' || get_post_meta( $id, '_invoice_kind', true ) !== 'credit' ) {
			return self::error( 'Creditfactuur niet gevonden.', 404 );
		}
		$amount = abs( (int) round( (float) Fields::get_for_post( $id, 'total_amount' ) * 100 ) );
		$reason = '';
		if ( ! in_array( $post->post_status, [ 'rondo_sent', 'rondo_overdue' ], true ) ) {
			$reason = 'Een betaalbestand is alleen beschikbaar voor verstuurde, openstaande creditfacturen.';
		} elseif ( $amount < 1 || $amount > 99999999999 ) {
			$reason = 'Deze creditfactuur heeft geen geldig terug te betalen bedrag.';
		}
		$export = get_post_meta( $id, self::META, true );
		$export = is_array( $export ) ? $export : null;
		if ( $export && $export['amount_cents'] !== $amount ) {
			$reason = 'Het creditbedrag is gewijzigd sinds de export. Controleer eerst de eerdere betaalopdracht.';
		}
		$source = (int) get_post_meta( $id, '_credit_source_invoice_id', true );
		if ( $source <= 0 || get_post_type( $source ) !== 'rondo_invoice' ) {
			$source = 0;
		}
		$name = $source ? (string) get_post_meta( $source, '_mollie_consumer_name', true ) : '';
		$iban = $source ? (string) get_post_meta( $source, '_mollie_consumer_account', true ) : '';
		if ( $name === '' ) {
			$name   = (string) get_post_meta( $id, '_customer_name', true );
			$person = (int) Fields::get_for_post( $id, 'person' );
			if ( $name === '' && $person > 0 && get_post_type( $person ) === 'person' ) {
				$name = html_entity_decode( get_the_title( $person ), ENT_QUOTES, 'UTF-8' );
			}
		}
		$defaults = [
			'creditor_name'  => $name,
			'creditor_iban'  => self::normalize_iban( $iban ),
			'debtor_name'    => (string) ( $account['account_holder'] ?? '' ),
			'debtor_iban'    => self::normalize_iban( (string) ( $account['iban'] ?? '' ) ),
			'execution_date' => current_time( 'Y-m-d' ),
		];
		$summary  = $export;
		if ( $summary ) {
			unset( $summary['xml'], $summary['request_id'], $summary['fingerprint'] );
		}
		return [
			'amount_cents'   => $amount,
			'blocked_reason' => $reason,
			'defaults'       => $export ? $export['payment'] : $defaults,
			'export'         => $summary,
			'today'          => current_time( 'Y-m-d' ),
		];
	}

	/** Create one immutable export; confirmed repeat downloads return the same XML and IDs. */
	public static function create( int $id, array $input, array $account ) {
		$lock = fopen( get_temp_dir() . 'rondo-sepa-' . md5( ABSPATH . ':' . $id ) . '.lock', 'c' );
		if ( ! $lock || ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
			if ( $lock ) {
				fclose( $lock );
			}
			return self::error( 'Er wordt al een betaalbestand gemaakt. Probeer het opnieuw.', 409 );
		}
		try {
			// Another worker may have created an export while this request was waiting.
			wp_cache_delete( $id, 'post_meta' );
			$context = self::prepare( $id, $account );
			if ( is_wp_error( $context ) ) {
				return $context;
			}
			if ( $context['blocked_reason'] !== '' ) {
				return self::error( $context['blocked_reason'], 409 );
			}
			if ( ( $input['confirmed'] ?? null ) !== true ) {
				return self::error( 'Bevestig dat de gegevens kloppen en dit bedrag nog niet is terugbetaald of verrekend.' );
			}
			if ( ( $input['expected_amount_cents'] ?? null ) !== $context['amount_cents'] ) {
				return self::error( 'Het bedrag is gewijzigd. Vernieuw de pagina en controleer de creditfactuur.', 409 );
			}
			$request_id = $input['request_id'] ?? '';
			if ( ! is_string( $request_id ) || ! preg_match( '/^[a-zA-Z0-9-]{16,80}$/D', $request_id ) ) {
				return self::error( 'Ongeldige aanvraagcode. Vernieuw de pagina.' );
			}
			$fingerprint = hash( 'sha256', wp_json_encode( $input ) . ':' . get_current_user_id() );
			$existing    = get_post_meta( $id, self::META, true );
			if ( is_array( $existing ) ) {
				$replay = $existing['request_id'] === $request_id && hash_equals( $existing['fingerprint'], $fingerprint );
				if ( ! $replay && ( $input['previous_export_id'] ?? '' ) !== $existing['export_id'] ) {
					return self::error( 'Er is al een betaalbestand voor deze creditfactuur. Vernieuw de pagina om het bestaande bestand opnieuw te downloaden.', 409 );
				}
				return self::download( $existing );
			}
			$payment = [];
			foreach ( array_keys( $context['defaults'] ) as $key ) {
				if ( ! is_string( $input[ $key ] ?? null ) ) {
					return self::error( 'Vul alle betaalgegevens in.' );
				}
				$payment[ $key ] = sanitize_text_field( $input[ $key ] );
			}
			foreach ( [ 'debtor', 'creditor' ] as $party ) {
				$payment[ $party . '_iban' ] = self::normalize_iban( $payment[ $party . '_iban' ] );
				if ( ! self::valid_iban( $payment[ $party . '_iban' ] ) ) {
					return self::error( $party === 'debtor' ? 'Vul een geldig IBAN van de club in.' : 'Vul een geldig SEPA-IBAN van de ontvanger in.' );
				}
				$name = $payment[ $party . '_name' ];
				if ( $name === '' || mb_strlen( $name ) > 70 ) {
					return self::error( 'Vul voor beide rekeningen een tenaamstelling van maximaal 70 tekens in.' );
				}
				if ( preg_match( '/[\x00-\x1F\x7F]/', $name ) ) {
					return self::error( 'De tenaamstelling bevat ongeldige tekens. Vul de naam opnieuw in.' );
				}
			}
			if ( ! preg_match( '/^NL\d{2}RABO\d{10}$/D', $payment['debtor_iban'] ) ) {
				return self::error( 'Gebruik een Nederlandse Rabobank-rekening van de club als afschrijfrekening.' );
			}
			if ( $payment['debtor_iban'] === $payment['creditor_iban'] ) {
				return self::error( 'De rekening van de ontvanger moet verschillen van de afschrijfrekening.' );
			}
			$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $payment['execution_date'], wp_timezone() );
			if ( ! $date || $date->format( 'Y-m-d' ) !== $payment['execution_date'] || $payment['execution_date'] < current_time( 'Y-m-d' ) || $date > current_datetime()->modify( '+1 year' ) ) {
				return self::error( 'Kies een uitvoerdatum vanaf vandaag, maximaal één jaar vooruit.' );
			}
			$export             = [
				'export_id'    => 'RC' . str_replace( '-', '', wp_generate_uuid4() ),
				'request_id'   => $request_id,
				'fingerprint'  => $fingerprint,
				'created_at'   => current_datetime()->format( DATE_ATOM ),
				'created_by'   => get_current_user_id(),
				'amount_cents' => $context['amount_cents'],
				'payment'      => $payment,
				'description'  => 'Creditfactuur ' . (string) Fields::get_for_post( $id, 'invoice_number' ),
			];
			$export['filename'] = 'credit-' . $id . '-' . $export['export_id'] . '.xml';
			$export['xml']      = self::xml( $export );
			// Store before returning: a lost response can only replay this same payment instruction.
			if ( ! update_post_meta( $id, self::META, wp_slash( $export ) ) ) {
				return self::error( 'Het betaalbestand kon niet worden geregistreerd. Probeer het opnieuw.', 500 );
			}
			return self::download( $export );
		} finally {
			flock( $lock, LOCK_UN );
			fclose( $lock );
		}
	}

	/** Private REST response payload; no file is written to public uploads. */
	private static function download( array $export ): array {
		return array_intersect_key( $export, array_flip( [ 'xml', 'filename', 'export_id', 'created_at' ] ) );
	}

	private static function normalize_iban( string $iban ): string {
		return strtoupper( preg_replace( '/\s+/', '', $iban ) );
	}

	/** SEPA country lengths and ISO 13616 checksum, without integer overflow. */
	public static function valid_iban( string $iban ): bool {
		$lengths = [
			'AD' => 24,
			'AL' => 28,
			'AT' => 20,
			'BE' => 16,
			'BG' => 22,
			'CH' => 21,
			'CY' => 28,
			'CZ' => 24,
			'DE' => 22,
			'DK' => 18,
			'EE' => 20,
			'ES' => 24,
			'FI' => 18,
			'FR' => 27,
			'GB' => 22,
			'GI' => 23,
			'GR' => 27,
			'HR' => 21,
			'HU' => 28,
			'IE' => 22,
			'IS' => 26,
			'IT' => 27,
			'LI' => 21,
			'LT' => 20,
			'LU' => 20,
			'LV' => 21,
			'MC' => 27,
			'MD' => 24,
			'ME' => 22,
			'MK' => 19,
			'MT' => 31,
			'NL' => 18,
			'NO' => 15,
			'PL' => 28,
			'PT' => 25,
			'RO' => 24,
			'RS' => 22,
			'SE' => 24,
			'SI' => 19,
			'SK' => 24,
			'SM' => 27,
			'VA' => 22,
		];
		if ( ! preg_match( '/^[A-Z]{2}\d{2}[A-Z0-9]+$/D', $iban ) || strlen( $iban ) !== ( $lengths[ substr( $iban, 0, 2 ) ] ?? 0 ) ) {
			return false;
		}
		$digits = substr( $iban, 4 ) . substr( $iban, 0, 4 );
		$mod    = 0;
		foreach ( str_split( $digits ) as $char ) {
			foreach ( str_split( ctype_alpha( $char ) ? (string) ( ord( $char ) - 55 ) : $char ) as $digit ) {
				$mod = ( $mod * 10 + (int) $digit ) % 97;
			}
		}
		return $mod === 1;
	}

	/** Build the Rabobank SEPA Credit Transfer profile (pain.001.001.09). */
	private static function xml( array $export ): string {
		$xml               = new \DOMDocument( '1.0', 'UTF-8' );
		$xml->formatOutput = true;
		$root              = $xml->appendChild( $xml->createElementNS( self::NS, 'Document' ) );
		$add               = static function ( $parent, string $name, ?string $value = null ) use ( $xml ) {
			$node = $parent->appendChild( $xml->createElementNS( self::NS, $name ) );
			if ( $value !== null ) {
				$node->appendChild( $xml->createTextNode( $value ) );
			}
			return $node;
		};
		$amount            = number_format( $export['amount_cents'] / 100, 2, '.', '' );
		$data              = $export['payment'];
		$body              = $add( $root, 'CstmrCdtTrfInitn' );
		$header            = $add( $body, 'GrpHdr' );
		$add( $header, 'MsgId', $export['export_id'] );
		$add( $header, 'CreDtTm', $export['created_at'] );
		$add( $header, 'NbOfTxs', '1' );
		$add( $header, 'CtrlSum', $amount );
		$add( $add( $header, 'InitgPty' ), 'Nm', $data['debtor_name'] );
		$payment = $add( $body, 'PmtInf' );
		$add( $payment, 'PmtInfId', $export['export_id'] );
		$add( $payment, 'PmtMtd', 'TRF' );
		$add( $payment, 'BtchBookg', 'false' );
		$add( $payment, 'NbOfTxs', '1' );
		$add( $payment, 'CtrlSum', $amount );
		$add( $add( $add( $payment, 'PmtTpInf' ), 'SvcLvl' ), 'Cd', 'SEPA' );
		$add( $add( $payment, 'ReqdExctnDt' ), 'Dt', $data['execution_date'] );
		$add( $add( $payment, 'Dbtr' ), 'Nm', $data['debtor_name'] );
		$add( $add( $add( $payment, 'DbtrAcct' ), 'Id' ), 'IBAN', $data['debtor_iban'] );
		$add( $add( $add( $payment, 'DbtrAgt' ), 'FinInstnId' ), 'BICFI', 'RABONL2U' );
		$add( $payment, 'ChrgBr', 'SLEV' );
		$transfer = $add( $payment, 'CdtTrfTxInf' );
		$add( $add( $transfer, 'PmtId' ), 'EndToEndId', $export['export_id'] );
		$add( $add( $transfer, 'Amt' ), 'InstdAmt', $amount )->setAttribute( 'Ccy', 'EUR' );
		$add( $add( $transfer, 'Cdtr' ), 'Nm', $data['creditor_name'] );
		$add( $add( $add( $transfer, 'CdtrAcct' ), 'Id' ), 'IBAN', $data['creditor_iban'] );
		$add( $add( $transfer, 'RmtInf' ), 'Ustrd', mb_substr( $export['description'], 0, 140 ) );
		return $xml->saveXML();
	}

	private static function error( string $message, int $status = 400 ): \WP_Error {
		return new \WP_Error( 'credit_sepa_export', $message, [ 'status' => $status ] );
	}
}
