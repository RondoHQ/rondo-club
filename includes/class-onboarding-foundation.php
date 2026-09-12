<?php
/** Non-sending onboarding observations, rounds and simulation. */

namespace Rondo\Onboarding;

use Rondo\Core\VolunteerStatus;
use Rondo\Fields\Fields;
use Rondo\People\CommunicationPolicy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Foundation {

	const TYPE     = 'rondo_onboard_round';
	const META     = '_rondo_onboarding_observation';
	const COVERAGE = [ 'person', 'parents', 'teams', 'functions', 'vog' ];

	/** Internal records are never exposed via generic WordPress endpoints. */
	public static function register(): void {
		register_post_type(
			self::TYPE,
			[
				'public'       => false,
				'show_ui'      => false,
				'show_in_rest' => false,
				'rewrite'      => false,
				'supports'     => [],
				'can_export'   => false,
				'map_meta_cap' => false,
				'capabilities' => array_fill_keys( [ 'read', 'read_post', 'edit_post', 'delete_post', 'edit_posts', 'create_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'edit_others_posts' ], 'do_not_allow' ),
			]
			);
	}

	/** Persistent fail-closed lock; a crashed writer is not silently replaced. */
	public static function locked( string $key, callable $callback ) {
		$key = 'rondo_onboarding_lock_' . hash( 'sha256', $key );
		// add_option uses ON DUPLICATE KEY UPDATE. An invariant value is essential:
		// a changing timestamp could make a concurrent duplicate report success.
		if ( ! add_option( $key, 'locked', '', false ) ) {
			return new \WP_Error( 'onboarding_busy', 'Deze registratie wordt verwerkt of moet na een storing worden gecontroleerd.', [ 'status' => 409 ] );
		}
		try {
			return $callback();
		} finally {
			delete_option( $key );
		}
	}

	/** Hash the actual stored data that the completed source check must cover. */
	public static function snapshot_hash( int $person_id ): string {
		$data = [];
		foreach ( [ 'knvb_id', 'first_name', 'birthdate', 'email_1', 'email_2', 'relationships', 'work_history', 'lid_sinds', 'lid_tot', 'type_lid', 'former_member', 'wacht_op_overschrijving', 'datum_overlijden', 'datum_vog' ] as $field ) {
			$data[ $field ] = Fields::get_for_post( $person_id, $field );
		}
		$data['recipients'] = Recipients::for_person( $person_id );
		return hash( 'sha256', wp_json_encode( $data ) );
	}

	/**
	 * Receive a complete, source-owned observation. First observation is baseline.
	 * A transition from a confirmed non-member or ended period opens a new round.
	 * This method never schedules, renders or sends mail.
	 */
	public static function observe( int $person_id, array $input ) {
		if ( get_post_type( $person_id ) !== 'person' || get_post_status( $person_id ) !== 'publish' ) {
			return new \WP_Error( 'onboarding_person', 'Persoon niet gevonden.', [ 'status' => 404 ] );
		}
		$required = [ 'observation_id', 'knvb_id', 'observed_at', 'membership_state', 'snapshot_hash', 'coverage' ];
		if ( array_diff( $required, array_keys( $input ) ) || array_diff( array_keys( $input ), $required ) ) {
			return new \WP_Error( 'onboarding_contract', 'Onvolledige of onbekende observatievelden.', [ 'status' => 400 ] );
		}
		if ( ! is_string( $input['observation_id'] ) || ! preg_match( '/^[a-zA-Z0-9_-]{8,100}$/D', $input['observation_id'] )
			|| ! in_array( $input['membership_state'], [ 'not_member', 'preregistration', 'definitive', 'ended' ], true )
			|| ! is_string( $input['observed_at'] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})$/D', $input['observed_at'] )
			|| ! is_array( $input['coverage'] ) || count( $input['coverage'] ) !== count( self::COVERAGE )
			|| array_diff( self::COVERAGE, array_keys( $input['coverage'] ) )
			|| count( array_filter( $input['coverage'], 'is_bool' ) ) !== count( self::COVERAGE ) ) {
			return new \WP_Error( 'onboarding_incomplete', 'Alle bronnen moeten aantoonbaar opgehaald en opgeslagen zijn.', [ 'status' => 400 ] );
		}
		try {
			$observed = new \DateTimeImmutable( $input['observed_at'] );
			$errors   = \DateTimeImmutable::getLastErrors();
			if ( $errors && ( $errors['warning_count'] || $errors['error_count'] ) ) {
				throw new \InvalidArgumentException( 'Invalid date' );
			}
		} catch ( \Exception $e ) {
			return new \WP_Error( 'onboarding_date', 'Ongeldig controletijdstip.', [ 'status' => 400 ] );
		}
		if ( $observed->getTimestamp() > time() + MINUTE_IN_SECONDS || ! is_string( $input['knvb_id'] )
			|| $input['knvb_id'] === '' || $input['knvb_id'] !== Fields::get_for_post( $person_id, 'knvb_id' ) ) {
			return new \WP_Error( 'onboarding_identity', 'Identiteit of controletijdstip komt niet overeen.', [ 'status' => 409 ] );
		}
		return self::locked(
			'person:' . $person_id,
			static function () use ( $person_id, $input, $observed ) {
				$previous = get_post_meta( $person_id, self::META, true ) ?: [];
				$hash     = hash( 'sha256', wp_json_encode( $input ) );
				if ( ( $previous['observation_id'] ?? '' ) === $input['observation_id'] ) {
					return ( $previous['input_hash'] ?? '' ) === $hash ? $previous : new \WP_Error( 'onboarding_conflict', 'Deze controlesleutel is al met andere gegevens gebruikt.', [ 'status' => 409 ] );
				}
				if ( $previous && ( $previous['knvb_id'] !== $input['knvb_id'] || strtotime( $previous['observed_at'] ) >= $observed->getTimestamp() ) ) {
					return new \WP_Error( 'onboarding_stale', 'Verouderde controle of gewijzigde bronidentiteit.', [ 'status' => 409 ] );
				}
				if ( ! is_string( $input['snapshot_hash'] ) || ! hash_equals( self::snapshot_hash( $person_id ), $input['snapshot_hash'] ) ) {
					return new \WP_Error( 'onboarding_changed', 'De gegevens zijn na de controle gewijzigd.', [ 'status' => 409 ] );
				}
				$complete = ! in_array( false, $input['coverage'], true );
				if ( ! $complete ) {
					$state                     = $input + [
						'input_hash' => $hash,
						'round_id'   => (int) ( $previous['round_id'] ?? 0 ),
						'baseline'   => ! $previous,
					];
					$state['membership_state'] = $previous['membership_state'] ?? 'unknown';
					$state['end_date']         = $previous['end_date'] ?? null;
					update_post_meta( $person_id, self::META, $state );
					return get_post_meta( $person_id, self::META, true ) === $state ? $state : new \WP_Error( 'onboarding_storage', 'Controle kon niet worden opgeslagen.', [ 'status' => 500 ] );
				}
				$start = Recipients::date( (string) Fields::get_for_post( $person_id, 'lid_sinds' ) );
				$end   = Recipients::date( (string) Fields::get_for_post( $person_id, 'lid_tot' ) );
				if ( in_array( $previous['membership_state'] ?? '', [ 'definitive', 'ended' ], true ) && in_array( $input['membership_state'], [ 'not_member', 'preregistration' ], true ) ) {
					return new \WP_Error( 'onboarding_membership_gap', 'Een tijdelijke bronafwezigheid mag geen bestaande lidmaatschapsperiode vervangen.', [ 'status' => 409 ] );
				}
				if ( ( $input['membership_state'] === 'definitive' && ( ! $start || Fields::get_for_post( $person_id, 'former_member' ) ) )
				|| ( $input['membership_state'] === 'ended' && ( ! $end || $end > current_datetime() ) ) ) {
					return new \WP_Error( 'onboarding_evidence', 'De lidmaatschapsdatums bevestigen deze overgang niet.', [ 'status' => 409 ] );
				}
				$state             = $input + [
					'input_hash' => $hash,
					'round_id'   => (int) ( $previous['round_id'] ?? 0 ),
					'baseline'   => ! $previous,
				];
				$state['end_date'] = $end ? $end->format( 'Y-m-d' ) : null;
				$new               = $previous && $input['membership_state'] === 'definitive' && in_array( $previous['membership_state'], [ 'not_member', 'preregistration', 'ended' ], true );
				if ( $new && $previous['membership_state'] === 'ended' && ( ! $previous['end_date'] || $start->format( 'Y-m-d' ) <= $previous['end_date'] ) ) {
					return new \WP_Error( 'onboarding_rejoin', 'Geen nieuwe ingangsdatum na de bevestigde beëindiging.', [ 'status' => 409 ] );
				}
				if ( $new ) {
					// Server receipt time, never a backdated source time, starts recognition.
					$recognized = time();
					$due        = max( $recognized, $start->getTimestamp() ) + DAY_IN_SECONDS;
					$key        = hash( 'sha256', $person_id . ':' . $input['observation_id'] );
					$ids        = get_posts(
					[
						'post_type'      => self::TYPE,
						'post_status'    => 'private',
						'fields'         => 'ids',
						'posts_per_page' => 1,
						'meta_key'       => '_onboarding_key',
						'meta_value'     => $key,
					]
					);
					$id         = $ids[0] ?? wp_insert_post(
					[
						'post_type'   => self::TYPE,
						'post_status' => 'private',
						'post_parent' => $person_id,
						'post_title'  => 'Onboardingronde',
						'meta_input'  => [
							'_onboarding_key'        => $key,
							'_onboarding_due'        => $due,
							'_onboarding_recognized' => $recognized,
						],
					],
					true
					);
					if ( is_wp_error( $id ) ) {
						return $id;
					}
					$state['round_id'] = (int) $id;
				}
				update_post_meta( $person_id, self::META, $state );
				if ( get_post_meta( $person_id, self::META, true ) !== $state ) {
					return new \WP_Error( 'onboarding_storage', 'Controle kon niet worden opgeslagen.', [ 'status' => 500 ] );
				}
				return $state;
			}
			);
	}

	/** Read-only live simulation. Unknown source facts remain explicit blockers. */
	public static function simulate( int $person_id ): array {
		$recipients = Recipients::for_person( $person_id );
		$state      = get_post_meta( $person_id, self::META, true ) ?: [];
		$round      = (int) ( $state['round_id'] ?? 0 );
		$due        = $round ? (int) get_post_meta( $round, '_onboarding_due', true ) : 0;
		$blockers   = [];
		$start      = Recipients::date( (string) Fields::get_for_post( $person_id, 'lid_sinds' ) );
		$end        = Recipients::date( (string) Fields::get_for_post( $person_id, 'lid_tot' ) );
		if ( $due && $start ) {
			$due = max( $due, $start->getTimestamp() + DAY_IN_SECONDS );
		}
		if ( $round && ! $due ) {
			$blockers[] = 'De opgeslagen welkomstronde is onvolledig; controle nodig.';
		}
		if ( ! $start || ( $end && $end <= current_datetime() ) ) {
			$blockers[] = 'Geen geldige actuele lidmaatschapsperiode.';
		}
		if ( ! $state ) {
			$blockers[] = 'Nog geen bevestigde volledige broncontrole; instroom niet vastgesteld.';
		} elseif ( ! hash_equals( $state['snapshot_hash'], self::snapshot_hash( $person_id ) ) ) {
			$blockers[] = 'Gegevens gewijzigd sinds de volledige broncontrole.';
		}
		if ( $state && in_array( false, $state['coverage'], true ) ) {
			$blockers[] = 'De laatste broncontrole is onvolledig of mislukt.';
		}
		if ( ! $round ) {
			$blockers[] = $state ? 'Geen nieuwe welkomstronde; eerste waarneming geldt als uitgangssituatie.' : 'Startdatum voor de welkomstmail nog niet bevestigd.';
		}
		if ( $state && $state['membership_state'] !== 'definitive' ) {
			$blockers[] = 'Geen bevestigde definitieve inschrijving.';
		}
		if ( Fields::get_for_post( $person_id, 'former_member' ) || ! CommunicationPolicy::may_contact( $person_id ) ) {
			$blockers[] = 'Oud-lid of overleden; onboarding geblokkeerd.';
		}
		if ( Fields::get_for_post( $person_id, 'wacht_op_overschrijving' ) ) {
			$blockers[] = 'Overschrijving nog niet afgerond.';
		}
		if ( $due > time() ) {
			$blockers[] = 'De wachttijd van 24 uur is nog niet voorbij.';
		}
		if ( ! array_filter( $recipients['recipients'], static fn( $recipient ) => ! $recipient['blocked'] ) ) {
			$blockers[] = 'Geen geldig, bereikbaar ontvangeradres.';
		}
		$roles = [];
		foreach ( Fields::get_for_post( $person_id, 'work_history' ) ?: [] as $position ) {
			if ( VolunteerStatus::is_position_current( $position ) && VolunteerStatus::is_volunteer_position( $position ) ) {
				$roles[] = (string) ( $position['job_title'] ?? '' );
			}
		}
		$blocks = [ 'Ledeninformatie uit de ingestelde welkomstmail', 'Account activeren via de vaste link (accounttoegang voor dit adres nog controleren)' ];
		if ( $roles ) {
			$blocks[] = 'Vrijwilligerswelkom (gecombineerd met ledeninformatie)';
			if ( \Rondo\VOG\VOGRequirement::is_required( $person_id ) ) {
				$submission = \Rondo\VOG\VogSubmissions::get( \Rondo\VOG\VogSubmissions::latest_id( $person_id ) );
				$status     = $submission['status'] ?? '';
				$blocks[]   = in_array( $status, [ 'checking', 'technical', 'review' ], true )
					? 'We hebben je VOG ontvangen en controleren deze. Je hoeft nu niets te doen.'
					: 'VOG-blok: aanvraagronde en geldigheid nog te controleren';
			}
			$blocks[] = 'Kledingblok: functie-selectie en eerdere uitgifte nog te controleren';
		}
		return [
			'person_id'       => $person_id,
			'name'            => get_the_title( $person_id ),
			'simulation_only' => true,
			'sending_enabled' => false,
			'snapshot_hash'   => self::snapshot_hash( $person_id ),
			'coverage'        => $state['coverage'] ?? array_fill_keys( self::COVERAGE, false ),
			'observed_at'     => $state['observed_at'] ?? null,
			'round_id'        => $round ?: null,
			'due_at'          => $due ? gmdate( 'c', $due ) : null,
			'minor'           => $recipients['minor'],
			'recipients'      => $recipients['recipients'],
			'warnings'        => $recipients['warnings'],
			'blockers'        => $blockers,
			'roles'           => array_values( array_unique( $roles ) ),
			'blocks'          => $blocks,
			'limitations'     => [ 'Gerichte broncontrole vanuit Sync wordt nog aangesloten.', 'Mailblokken, accountcontext en verzendherstel worden in volgende bouwstappen voltooid.' ],
		];
	}
}
