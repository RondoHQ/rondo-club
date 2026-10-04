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
		$payer = $source ? self::source_payer( $source ) : [];
		$name  = $payer['name'] ?? '';
		$iban  = $payer['iban'] ?? '';
		if ( $name === '' ) {
			$name   = (string) get_post_meta( $id, '_customer_name', true );
			$person = (int) Fields::get_for_post( $id, 'person' );
			if ( $name === '' && $person > 0 && get_post_type( $person ) === 'person' ) {
				$name = html_entity_decode( get_the_title( $person ), ENT_QUOTES, 'UTF-8' );
			}
		}
		$defaults = [
			'creditor_name'  => $name,
			'creditor_iban'  => SepaCreditTransfer::normalize_iban( $iban ),
			'debtor_name'    => (string) ( $account['account_holder'] ?? '' ),
			'debtor_iban'    => SepaCreditTransfer::normalize_iban( (string) ( $account['iban'] ?? '' ) ),
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

	/** Only prefill when the original payments identify one unambiguous SEPA account. */
	private static function source_payer( int $source ): array {
		$prefixes = [ '_mollie_' ];
		$count    = (int) get_post_meta( $source, '_installment_count', true );
		for ( $n = 1; $n <= $count; $n++ ) {
			if ( get_post_meta( $source, '_installment_' . $n . '_status', true ) === 'betaald' ) {
				$prefixes[] = '_installment_' . $n . '_mollie_';
			}
		}
		$payers = [];
		foreach ( $prefixes as $prefix ) {
			$iban = SepaCreditTransfer::normalize_iban( (string) get_post_meta( $source, $prefix . 'consumer_account', true ) );
			if ( ! self::valid_iban( $iban ) ) {
				continue;
			}
			$name            = (string) get_post_meta( $source, $prefix . 'consumer_name', true );
			$payers[ $iban ] = [
				'iban' => $iban,
				'name' => $name !== '' ? $name : ( $payers[ $iban ]['name'] ?? '' ),
			];
		}
		return count( $payers ) === 1 ? reset( $payers ) : [];
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
			$payment = SepaCreditTransfer::validate_payment( $payment );
			if ( is_wp_error( $payment ) ) {
				return $payment;
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
			$export['xml']      = SepaCreditTransfer::xml( $export );
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

	/** Compatibility entry point for existing consumers. */
	public static function valid_iban( string $iban ): bool {
		return SepaCreditTransfer::valid_iban( $iban );
	}

	private static function error( string $message, int $status = 400 ): \WP_Error {
		return new \WP_Error( 'credit_sepa_export', $message, [ 'status' => $status ] );
	}
}
