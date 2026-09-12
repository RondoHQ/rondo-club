<?php
/** Source inventory and pending targeted checks. Never sends mail. */

namespace Rondo\Onboarding;

final class Sources {

	const OPTION = 'rondo_onboarding_sources';

	/** Receive a successfully completed source search, retaining absent identities. */
	public static function ingest( array $input ) {
		if ( ! isset( $input['sources'], $input['observed_at'] ) || ! is_array( $input['sources'] ) || ! $input['sources'] || count( $input['sources'] ) > 20000 ) {
			return new \WP_Error( 'onboarding_sources', 'Een volledige, niet-lege bronnenlijst is vereist.', [ 'status' => 400 ] );
		}
		$timestamp = is_string( $input['observed_at'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})$/D', $input['observed_at'] ) ? strtotime( $input['observed_at'] ) : false;
		if ( ! $timestamp || $timestamp > time() + MINUTE_IN_SECONDS || $timestamp < time() - DAY_IN_SECONDS ) {
			return new \WP_Error( 'onboarding_sources_time', 'De bronnenlijst is niet actueel.', [ 'status' => 400 ] );
		}
		$records = [];
		foreach ( $input['sources'] as $source ) {
			if ( ! is_array( $source ) || ! isset( $source['knvb_id'], $source['fingerprint'], $source['membership_state'] )
				|| ! is_string( $source['knvb_id'] ) || ! preg_match( '/^[a-zA-Z0-9_-]{1,40}$/D', $source['knvb_id'] )
				|| ! is_string( $source['fingerprint'] ) || ! preg_match( '/^[a-f0-9]{64}$/D', $source['fingerprint'] )
				|| ! in_array( $source['membership_state'], [ 'unknown', 'preregistration', 'definitive', 'ended', 'not_member' ], true )
				|| isset( $records[ $source['knvb_id'] ] ) ) {
				return new \WP_Error( 'onboarding_source_record', 'Ongeldige of dubbele bronidentiteit.', [ 'status' => 400 ] );
			}
			$records[ $source['knvb_id'] ] = [
				'fingerprint'      => $source['fingerprint'],
				'membership_state' => $source['membership_state'],
			];
		}
		$check_ids = $input['check_ids'] ?? [];
		if ( ! is_array( $check_ids ) || count( $check_ids ) > 10 || count( array_filter( $check_ids, 'is_string' ) ) !== count( $check_ids ) || array_diff( $check_ids, array_keys( $records ) ) ) {
			return new \WP_Error( 'onboarding_check_ids', 'Controleer maximaal tien aanwezige bronidentiteiten.', [ 'status' => 400 ] );
		}
		return Foundation::locked(
			'sources',
			static function () use ( $records, $timestamp, $check_ids ) {
				$inventory = get_option( self::OPTION, [] );
				if ( $inventory && $timestamp < $inventory['observed_at'] ) {
					return new \WP_Error( 'onboarding_sources_stale', 'Verouderde bronnenlijst.', [ 'status' => 409 ] );
				}
				$initial = ! $inventory;
				if ( $initial ) {
					$inventory = [
						'started_at' => time(),
						'records'    => [],
					];
				}
				foreach ( $records as $id => $record ) {
					$previous = $inventory['records'][ $id ] ?? null;
					if ( ! $previous ) {
						$inventory['records'][ $id ] = $record + [
							'baseline'      => $initial,
							'initial_state' => $record['membership_state'],
							'first_seen'    => time(),
							'pending'       => ! $initial,
						];
					} elseif ( $previous['fingerprint'] !== $record['fingerprint'] ) {
						$inventory['records'][ $id ] = array_merge( $previous, $record, [ 'pending' => true ] );
					}
				}
				foreach ( $check_ids as $id ) {
					$inventory['records'][ $id ]['pending'] = true;
				}
				$inventory['observed_at'] = $timestamp;
				// Source absence never deletes the baseline or proves a membership end.
				update_option( self::OPTION, $inventory, false );
				if ( get_option( self::OPTION ) !== $inventory ) {
					return new \WP_Error( 'onboarding_sources_storage', 'Bronnenlijst kon niet worden opgeslagen.', [ 'status' => 500 ] );
				}
				$pending = [];
				foreach ( $records as $id => $record ) {
					$stored = $inventory['records'][ $id ];
					if ( $stored['pending'] ) {
						$pending[] = [ 'knvb_id' => (string) $id ] + $stored;
					}
				}
				// New registrations precede changed baseline records; unsuccessful checks rotate.
				usort( $pending, static fn( $a, $b ) => [ ! in_array( $a['knvb_id'], $check_ids, true ), $a['baseline'], $a['attempted_at'] ?? 0, $a['first_seen'] ] <=> [ ! in_array( $b['knvb_id'], $check_ids, true ), $b['baseline'], $b['attempted_at'] ?? 0, $b['first_seen'] ] );
				return [
					'baseline_initialized' => true,
					'baseline_count'       => count( $inventory['records'] ),
					'pending_count'        => count( $pending ),
					'checks'               => array_slice( $pending, 0, 10 ),
					'sending_enabled'      => false,
				];
			}
			);
	}

	/** Conservative first-registration proof: never welcome an older recovery import. */
	public static function is_new_registration( string $knvb_id, \DateTimeImmutable $start ): bool {
		$inventory = get_option( self::OPTION, [] );
		$source    = $inventory['records'][ $knvb_id ] ?? null;
		return $source && ( ! $source['baseline'] || ( $source['initial_state'] ?? 'definitive' ) === 'unknown' ) && $source['membership_state'] === 'definitive'
			&& $start->format( 'Y-m-d' ) >= wp_date( 'Y-m-d', $inventory['started_at'] );
	}

	public static function is_pending( string $knvb_id ): bool {
		$inventory = get_option( self::OPTION, [] );
		return ! empty( $inventory['records'][ $knvb_id ]['pending'] );
	}

	/** Acknowledge only the same source fingerprint, after the observation is stored. */
	public static function finish( array $input ) {
		return Foundation::locked(
			'sources',
			static function () use ( $input ) {
				$inventory = get_option( self::OPTION, [] );
				$id        = $input['knvb_id'] ?? '';
				if ( ! is_string( $id ) || ! isset( $inventory['records'][ $id ] ) || ( $input['fingerprint'] ?? null ) !== $inventory['records'][ $id ]['fingerprint'] ) {
					return new \WP_Error( 'onboarding_source_changed', 'De bron is inmiddels gewijzigd.', [ 'status' => 409 ] );
				}
				$state                                       = get_post_meta( (int) ( $input['person_id'] ?? 0 ), Foundation::META, true );
				$complete                                    = is_array( $state ) && ( $state['observation_id'] ?? '' ) === ( $input['observation_id'] ?? null )
					&& $state['knvb_id'] === $id && ! in_array( false, $state['coverage'], true )
					&& hash_equals( Foundation::snapshot_hash( (int) $input['person_id'] ), $state['snapshot_hash'] );
				$inventory['records'][ $id ]['attempted_at'] = time();
				$inventory['records'][ $id ]['pending']      = ! $complete;
				update_option( self::OPTION, $inventory, false );
				return get_option( self::OPTION ) === $inventory ? [ 'complete' => $complete ] : new \WP_Error( 'onboarding_sources_storage', 'Controle-uitkomst kon niet worden opgeslagen.', [ 'status' => 500 ] );
			}
			);
	}
}
