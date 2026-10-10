<?php
/** Shared minimal identities and contacts for personal rosters. */

namespace Rondo\People;

use Rondo\Fields\Fields;

final class RosterPerson {
	/** Explicit dates take precedence over stale flags; the end date is inclusive. */
	public static function is_current_position( array $position ): bool {
		if ( \Rondo\Core\WorkHistory::is_inactive_without_end_date( $position ) ) {
			return false;
		}

		$today = current_datetime()->format( 'Ymd' );
		foreach ( [ 'start_date', 'end_date' ] as $key ) {
			$value = str_replace( '-', '', trim( (string) ( $position[ $key ] ?? '' ) ) );
			if ( $value === '' ) {
				continue;
			}
			$date = \DateTimeImmutable::createFromFormat( '!Ymd', $value, wp_timezone() );
			if ( ! $date || $date->format( 'Ymd' ) !== $value || ( $key === 'start_date' ? $value > $today : $value < $today ) ) {
				return false;
			}
		}
		return ! empty( $position['team'] );
	}

	public static function is_published_person( int $person_id ): bool {
		return $person_id > 0 && get_post_type( $person_id ) === 'person' && get_post_status( $person_id ) === 'publish';
	}

	/** Minimal roster identity only; never read contact fields for player-only access. */
	public static function identity( int $person_id ): array {
		$parts = [];
		foreach ( [ 'first_name', 'infix', 'last_name' ] as $field ) {
			$parts[] = trim( (string) Fields::get_for_post( $person_id, $field ) );
		}
		$name = implode( ' ', array_filter( $parts ) );
		return [
			'id'   => $person_id,
			'name' => $name ?: html_entity_decode( get_the_title( $person_id ), ENT_QUOTES, 'UTF-8' ),
		];
	}

	/** An explicit allowlist; never serialize a general person or user response. */
	public static function contact( int $person_id ): array {
		$contact = self::identity( $person_id ) + [
			'emails'        => [],
			'phones'        => [],
			'mobile_phones' => [],
		];
		foreach ( [ 'email_1', 'email_2', 'mobile_1', 'mobile_2', 'telephone_1', 'telephone_2' ] as $field ) {
			$value = trim( (string) Fields::get_for_post( $person_id, $field ) );
			if ( $value === '' ) {
				continue;
			}
			$is_email                     = str_starts_with( $field, 'email' );
			$key                          = $is_email ? 'emails' : 'phones';
			$identity                     = $is_email ? strtolower( $value ) : preg_replace( '/[^+0-9]/', '', $value );
			$contact[ $key ][ $identity ] = $value;
			if ( str_starts_with( $field, 'mobile' ) ) {
				$contact['mobile_phones'][ $identity ] = $value;
			}
		}
		$contact['emails']        = array_values( $contact['emails'] );
		$contact['phones']        = array_values( $contact['phones'] );
		$contact['mobile_phones'] = array_values( $contact['mobile_phones'] );
		return $contact;
	}
}
