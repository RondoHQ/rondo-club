<?php
/** Onboarding recipient policy, shared by simulation and future delivery. */

namespace Rondo\Onboarding;

use Rondo\Fields\Fields;
use Rondo\People\CommunicationPolicy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Recipients {

	/** Strict canonical/storage date parsing; never repair impossible dates. */
	public static function date( string $value ): ?\DateTimeImmutable {
		$value = str_replace( '-', '', $value );
		if ( ! preg_match( '/^\d{8}$/D', $value ) ) {
			return null;
		}
		$date = \DateTimeImmutable::createFromFormat( '!Ymd', $value, wp_timezone() );
		return $date && $date->format( 'Ymd' ) === $value ? $date : null;
	}

	/** Resolve only explicit relationships. A shared mailbox is not a relationship. */
	public static function for_person( int $person_id, ?\DateTimeImmutable $now = null ): array {
		$now      = $now ?? current_datetime();
		$birth    = self::date( (string) Fields::get_for_post( $person_id, 'birthdate' ) );
		$minor    = $birth && $birth <= $now ? $birth->diff( $now )->y < 18 : null;
		$result   = [
			'minor'      => $minor,
			'recipients' => [],
			'warnings'   => [],
		];
		$sources  = [ $person_id => 'Eigen adres' ];
		$excluded = get_option( 'rondo_lettermint_suppressed_emails', [] );
		if ( ! CommunicationPolicy::may_contact( $person_id ) ) {
			$result['warnings'][] = 'Persoon is overleden; communicatie geblokkeerd.';
			return $result;
		}
		if ( $minor === null ) {
			$result['warnings'][] = 'Leeftijd onbekend; ouderadressen worden niet toegevoegd.';
		}
		$term = get_term_by( 'slug', 'parent', 'relationship_type' );
		if ( $minor && $term ) {
			foreach ( Fields::get_for_post( $person_id, 'relationships' ) ?: [] as $row ) {
				$types = (array) ( $row['relationship_type'] ?? [] );
				if ( in_array( (int) $term->term_id, array_map( 'intval', $types ), true ) ) {
					$id = (int) ( $row['related_person'] ?? 0 );
					if ( get_post_type( $id ) === 'person' && get_post_status( $id ) === 'publish' ) {
						$sources[ $id ] = 'Ouder/verzorger';
					}
				}
			}
		}
		foreach ( $sources as $id => $label ) {
			foreach ( CommunicationPolicy::email_addresses( $id ) as $email ) {
				// Synthetic login identifiers must never become delivery addresses.
				if ( str_ends_with( $email, '.invalid' ) ) {
					continue;
				}
				if ( ! isset( $result['recipients'][ $email ] ) ) {
					$result['recipients'][ $email ] = [
						'email'   => $email,
						'sources' => [],
						'blocked' => isset( $excluded[ $email ] ),
					];
				}
				$result['recipients'][ $email ]['sources'][] = [
					'person_id' => $id,
					'label'     => $label,
				];
			}
		}
		$result['recipients'] = array_values( $result['recipients'] );
		return $result;
	}
}
