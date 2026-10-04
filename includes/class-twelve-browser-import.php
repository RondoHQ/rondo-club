<?php
/** Validated daily browser exports, stored using the existing report IDs. */
namespace Rondo\Twelve;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BrowserImport {
	/** Remove the retired email job, including already queued events. */
	public static function retire_pdf_import(): void {
		if ( wp_next_scheduled( 'rondo_twelve_daily_import' ) ) {
			wp_clear_scheduled_hook( 'rondo_twelve_daily_import' );
		}
	}

	private static function invalid() {
		return new \WP_Error( 'twelve_invalid_export', 'Ongeldig of onvolledig Twelve-exportbestand.', [ 'status' => 400 ] );
	}

	private static function valid_money( $value ): bool {
		return ( is_int( $value ) || is_float( $value ) ) && is_finite( (float) $value ) && abs( $value ) < 100000000 && abs( round( $value * 100 ) - $value * 100 ) < 0.00001;
	}

	/** Validate the financial contract before taking a lock or changing records. */
	public static function validate( $data ): bool {
		if ( ! is_array( $data ) || ! is_string( $data['club'] ?? null ) || ! is_array( $data['omzet'] ?? null ) || ! is_array( $data['producten'] ?? null ) || ! is_array( $data['no_sale_transactions'] ?? null ) ) {
			return false;
		}
		foreach ( [ 'period_start', 'period_end' ] as $key ) {
			if ( ! is_string( $data[ $key ] ?? null ) ) {
				return false;
			}
		}
		foreach ( [ 'type', 'client_id', 'observed_at', 'coverage_end', 'finance_sha256' ] as $key ) {
			if ( ! is_string( $data['source'][ $key ] ?? null ) ) {
				return false;
			}
		}
		$zone  = new \DateTimeZone( 'Europe/Amsterdam' );
		$start = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $data['period_start'] ?? '', $zone );
		$end   = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $data['period_end'] ?? '', $zone );
		if ( ! $start || ! $end || $start->format( 'Y-m-d H:i' ) !== $data['period_start'] || $start->format( 'H:i' ) !== '06:00' || $start->modify( '+1 day' )->format( 'Y-m-d H:i' ) !== $data['period_end'] ) {
			return false;
		}
		$source = $data['source'] ?? [];
		$seen   = \DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i:s.v\Z', $source['observed_at'] ?? '', new \DateTimeZone( 'UTC' ) );
		$cover  = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $source['coverage_end'] ?? '', $zone );
		if ( ( $source['type'] ?? '' ) !== 'twelve_browser' || ! preg_match( '/^\d+$/', $source['client_id'] ?? '' ) || ! $seen || ! $cover || $cover->format( 'Y-m-d H:i' ) !== $source['coverage_end'] || $seen->getTimestamp() > time() + 300 || $cover > $seen || $cover <= $start || $cover > $end || ! is_bool( $source['complete'] ?? null ) || $source['complete'] !== ( $cover->getTimestamp() === $end->getTimestamp() ) || ! preg_match( '/^[a-f0-9]{64}$/', $source['finance_sha256'] ?? '' ) || ! is_array( $source['audit'] ?? null ) || count( $source['audit'] ) !== 3 ) {
			return false;
		}
		foreach ( [ 'transactions', 'products', 'raw' ] as $kind ) {
			if ( ! is_string( $source['audit'][ $kind ] ?? null ) || ! preg_match( '/^[a-f0-9]{64}$/', $source['audit'][ $kind ] ) ) {
				return false;
			}
		}
		$total = 0;
		foreach ( $data['omzet'] as $row ) {
			if ( ! is_array( $row ) || ! in_array( $row['section'] ?? '', [ 'totaal', 'categorie', 'betaalmethode', 'btw_type', 'subtotaal' ], true ) || ! is_string( $row['label'] ?? null ) || ! is_int( $row['transacties'] ?? null ) || $row['transacties'] < 0 ) {
				return false;
			}
			foreach ( [ 'bedrag', 'netto', 'hoog', 'laag' ] as $key ) {
				if ( ! self::valid_money( $row[ $key ] ?? null ) ) {
					return false;
				}
			}
			if ( abs( round( ( $row['bedrag'] - $row['netto'] - $row['hoog'] - $row['laag'] ) * 100 ) ) > 1 ) {
				return false;
			}
			if ( $row['section'] === 'totaal' && $row['label'] === 'Omzet (excl. no-sale)' ) {
				++$total;
			}
		}
		$gross = 0;
		$count = 0;
		foreach ( $data['producten'] as $row ) {
			if ( ! is_string( $row['product'] ?? null ) || ! is_string( $row['btw_groep'] ?? null ) || ! is_int( $row['aantal'] ?? null ) ) {
				return false;
			}
			foreach ( [ 'bruto', 'netto', 'btw' ] as $key ) {
				if ( ! self::valid_money( $row[ $key ] ?? null ) ) {
					return false;
				}
			}
			if ( round( ( $row['bruto'] - $row['netto'] - $row['btw'] ) * 100 ) !== 0.0 ) {
				return false;
			}
			$gross += (int) round( $row['bruto'] * 100 );
			$count += $row['aantal'];
		}
		$ids = [];
		foreach ( $data['no_sale_transactions'] as $row ) {
			if ( ! is_array( $row ) || ! is_string( $row['transactionId'] ?? null ) || isset( $ids[ $row['transactionId'] ] ) || ( $row['day'] ?? '' ) !== $start->format( 'Y-m-d' ) || ! is_string( $row['category'] ?? null ) || ! is_int( $row['grossCents'] ?? null ) || ! is_bool( $row['partial'] ?? null ) || ! is_array( $row['products'] ?? null ) ) {
				return false;
			}
			if ( ! is_string( $row['localTime'] ?? null ) || ! preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $row['localTime'] ) || $row['localTime'] < $data['period_start'] || $row['localTime'] >= $source['coverage_end'] || ( isset( $row['terminal'] ) && ! is_string( $row['terminal'] ) ) || ( $row['partial'] && $row['products'] !== [] ) ) {
				return false;
			}
			if ( isset( $row['accountedCents'] ) && ! is_int( $row['accountedCents'] ) ) {
				return false;
			}
			$consumed = 0;
			foreach ( $row['products'] as $product ) {
				if ( ! is_array( $product ) || ! is_string( $product['name'] ?? null ) || ! is_int( $product['count'] ?? null ) || ! is_int( $product['grossCents'] ?? null ) ) {
					return false;
				}
				$consumed += $product['grossCents'];
			}
			if ( ! $row['partial'] && $consumed !== $row['grossCents'] ) {
				return false;
			}
			$ids[ $row['transactionId'] ] = true;
		}
		return $total === 1 && self::valid_money( $data['producten_totaal']['bruto'] ?? null ) && $gross === (int) round( $data['producten_totaal']['bruto'] * 100 ) && $count === ( $data['producten_totaal']['aantal'] ?? null );
	}

	/** A shared lock prevents report updates racing invoice creation. */
	public function import( string $json ) {
		if ( strlen( $json ) > 5000000 ) {
			return self::invalid();
		}
		$data = json_decode( $json, true );
		if ( ! self::validate( $data ) ) {
			return self::invalid();
		}
		if ( ! add_option( 'rondo_twelve_invoice_lock', time(), '', false ) ) {
			return new \WP_Error( 'twelve_import_busy', 'Twelve-rapporten worden al bijgewerkt. Probeer opnieuw.', [ 'status' => 409 ] );
		}
		try {
			return $this->store( $data, $json );
		} finally {
			delete_option( 'rondo_twelve_invoice_lock' );
		}
	}

	private function store( array $data, string $json ) {
		$client = get_option( 'rondo_twelve_client_id', '' );
		if ( $client !== '' && $client !== $data['source']['client_id'] ) {
			return new \WP_Error( 'twelve_wrong_client', 'De Twelve-club komt niet overeen.', [ 'status' => 409 ] );
		}
		$repo     = new ReportRepository();
		$posts    = get_posts(
			[
				'post_type'        => ReportRepository::POST_TYPE,
				'post_status'      => [ 'publish', 'draft', 'trash' ],
				'numberposts'      => -1,
				'meta_key'         => ReportRepository::META_PERIOD_START,
				'meta_value'       => $data['period_start'] . ':00',
				'suppress_filters' => true,
			]
			);
		$existing = array_map(
			static fn( $post ) => [
				'id'           => $post->ID,
				'period_start' => $data['period_start'],
				'data'         => json_decode( (string) get_post_meta( $post->ID, ReportRepository::META_DATA, true ), true ) ?: [],
			],
			$posts
			);
		if ( $posts && $posts[0]->post_status === 'trash' ) {
			return new \WP_Error( 'twelve_trashed_day', 'Rapport staat in de prullenbak; herstel het eerst.', [ 'status' => 409 ] );
		}
		if ( count( $existing ) > 1 ) {
			return new \WP_Error( 'twelve_duplicate_day', 'Meerdere bestaande rapporten voor deze dag.', [ 'status' => 409 ] );
		}
		$hash = hash( 'sha256', $json );
		$id   = $existing[0]['id'] ?? 0;
		$old  = $existing[0]['data'] ?? [];
		if ( $id && get_post_meta( $id, '_twelve_source_hash', true ) === $hash ) {
			return [
				'id'     => $id,
				'status' => 'unchanged',
				'hash'   => $hash,
			];
		}
		if ( ! empty( $old['source'] ) && ( $old['source']['observed_at'] > $data['source']['observed_at'] || $old['source']['coverage_end'] > $data['source']['coverage_end'] ) ) {
			return new \WP_Error( 'twelve_stale_export', 'Een nieuwer rapport is al opgeslagen.', [ 'status' => 409 ] );
		}
		if ( $id && ( get_post_meta( $id, BusinessclubInvoicing::CLAIM, true ) || get_post_meta( $id, BusinessclubInvoicing::BILLED, true ) ) ) {
			$before = ReportAggregator::businessclub_days( [ $existing[0] ] );
			$after  = ReportAggregator::businessclub_days(
				[
					[
						'period_start' => $data['period_start'],
						'data'         => $data,
					],
				]
				);
			if ( $before !== $after || ! $data['source']['complete'] ) {
				return new \WP_Error( 'twelve_invoice_conflict', 'Het Businessclub-bedrag is al gekoppeld aan een factuur; controleer het bronverschil.', [ 'status' => 409 ] );
			}
		}
		// History preceding the original import must not become billable implicitly.
		$billing_start = get_option( 'rondo_twelve_billing_start', '' );
		if ( $billing_start === '' ) {
			$history       = $repo->query( '1970-01-01', '9999-12-31' );
			$billing_start = $history ? substr( $history[0]['period_start'], 0, 10 ) : wp_date( 'Y-m-d', null, new \DateTimeZone( 'Europe/Amsterdam' ) );
			add_option( 'rondo_twelve_billing_start', $billing_start, '', false );
		}
		add_option( 'rondo_twelve_client_id', $data['source']['client_id'], '', false );
		if ( $id && ! get_post_meta( $id, '_twelve_original_report_data', true ) ) {
			if ( ! add_post_meta( $id, '_twelve_original_report_data', wp_slash( wp_json_encode( $old ) ), true ) ) {
				return new \WP_Error( 'twelve_backup_failed', 'Origineel rapport kon niet worden bewaard.', [ 'status' => 500 ] );
			}
		}
		$created = ! $id;
		if ( ! $id ) {
			$id = wp_insert_post(
				[
					'post_type'   => ReportRepository::POST_TYPE,
					'post_status' => 'draft',
					'post_title'  => 'Twelve rapportage ' . substr( $data['period_start'], 0, 10 ),
					'post_author' => 0,
					'meta_input'  => [ ReportRepository::META_PERIOD_START => $data['period_start'] . ':00' ],
				],
				true
				);
			if ( is_wp_error( $id ) ) {
				return $id;
			}
		}
		$meta = [
			ReportRepository::META_PERIOD_START => $data['period_start'] . ':00',
			ReportRepository::META_PERIOD_END   => $data['period_end'] . ':00',
			ReportRepository::META_TOTAL_GROSS  => $data['producten_totaal']['bruto'],
			'_twelve_historical'                => substr( $data['period_start'], 0, 10 ) < $billing_start ? '1' : '0',
			ReportRepository::META_DATA         => $json,
		];
		foreach ( $meta as $key => $value ) {
			update_post_meta( $id, $key, wp_slash( $value ) );
			if ( (string) get_post_meta( $id, $key, true ) !== (string) $value ) {
				return new \WP_Error( 'twelve_storage_failed', 'Rapport opslaan mislukt; import opnieuw proberen.', [ 'status' => 500 ] );
			}
		}
		$result = wp_update_post(
			[
				'ID'          => $id,
				'post_status' => 'publish',
			],
			true
			);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		update_post_meta( $id, '_twelve_source_hash', $hash );
		if ( get_post_meta( $id, '_twelve_source_hash', true ) !== $hash ) {
			return new \WP_Error( 'twelve_storage_failed', 'Importcontrole opslaan mislukt.', [ 'status' => 500 ] );
		}
		if ( $data['source']['observed_at'] > get_option( 'rondo_twelve_browser_last_success', '' ) ) {
			update_option( 'rondo_twelve_browser_last_success', $data['source']['observed_at'], false );
		}
		return [
			'id'     => $id,
			'status' => $created ? 'created' : 'updated',
			'hash'   => $hash,
		];
	}
}
