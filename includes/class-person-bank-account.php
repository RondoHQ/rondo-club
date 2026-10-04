<?php
/** Person bank fields with independent field permissions and local audit. */
namespace Rondo\Finance;

use Rondo\Core\AccessControl;
use Rondo\Core\UserRoles;
use Rondo\Fields\Fields;
use Rondo\People\CommunicationPolicy;

final class PersonBankAccount {
	public const FIELDS = [ 'iban', 'bank_account_holder' ];

	public function __construct() {
		add_filter( 'rondo_fields_validate_value', [ $this, 'validate' ], 10, 4 );
		add_filter( 'rest_pre_insert_person', [ $this, 'guard' ], 5, 2 );
		add_action( 'rondo_fields_saved_post', [ $this, 'audit' ], 10, 2 );
	}

	/** Financial access never implies access to an otherwise hidden person. */
	public static function can_read( int $id ): bool {
		if ( ! is_user_logged_in() || get_post_type( $id ) !== 'person' || get_post_status( $id ) !== 'publish' ) {
			return false;
		}
		return (int) get_user_meta( get_current_user_id(), 'rondo_linked_person_id', true ) === $id
			|| ( ( current_user_can( 'manage_options' ) || UserRoles::can_manage_finances() ) && AccessControl::can_view_person( $id ) );
	}

	public static function can_write( int $id ): bool {
		return self::can_read( $id ) && ! CommunicationPolicy::is_deceased( $id )
			&& ( current_user_can( 'manage_options' ) || ! Fields::get_for_post( $id, 'former_member' ) );
	}

	/** Only explicit bank inputs are accepted; empty fields are valid until settlement. */
	public static function normalize( string $key, $value ) {
		if ( $value !== null && ! is_string( $value ) ) {
			return self::error( 'Gebruik tekst of null voor ' . $key . '.' );
		}
		$value = trim( $value ?? '' );
		if ( $key === 'iban' ) {
			$value = SepaCreditTransfer::normalize_iban( $value );
			if ( $value !== '' && ! SepaCreditTransfer::valid_iban( $value ) ) {
				return self::error( 'Vul een geldig SEPA-IBAN in.' );
			}
		} elseif ( mb_strlen( $value ) > 70 || preg_match( '/[\x00-\x1F\x7F<>]/', $value ) ) {
			return self::error( 'Vul een naam rekeninghouder van maximaal 70 tekens in, zonder opmaak.' );
		}
		return $value;
	}

	public function validate( $value, $id, $definition, $old ) {
		$key = $definition['canonical_name'];
		if ( ( $definition['context'] ?? '' ) !== 'person' || ! in_array( $key, self::FIELDS, true ) ) {
			return $value;
		}
		if ( ! self::can_write( (int) $id ) ) {
			return self::error( 'Je mag deze bankgegevens niet wijzigen.', 403 );
		}
		return self::normalize( $key, $value );
	}

	/** Reject unauthorized generic REST writes before any part of the person changes. */
	public function guard( $post, $request ) {
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		foreach ( (array) $request->get_param( 'fields' ) as $key => $value ) {
			if ( in_array( $key, self::FIELDS, true ) ) {
				if ( ! self::can_write( (int) ( $post->ID ?? 0 ) ) ) {
					return self::error( 'Je mag deze bankgegevens niet wijzigen.', 403 );
				}
				$result = self::normalize( $key, $value );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
		}
		return $post;
	}

	public static function read( int $id ) {
		if ( ! self::can_read( $id ) ) {
			return self::error( 'Geen toegang tot deze bankgegevens.', 403 );
		}
		return [
			'iban'                => Fields::get_for_post( $id, 'iban' ) ?: null,
			'bank_account_holder' => Fields::get_for_post( $id, 'bank_account_holder' ) ?: null,
			'can_edit'            => self::can_write( $id ),
		];
	}

	public static function update( int $id, array $input ) {
		if ( ! self::can_write( $id ) ) {
			return self::error( 'Je mag deze bankgegevens niet wijzigen.', 403 );
		}
		if ( ! $input || array_diff( array_keys( $input ), self::FIELDS ) ) {
			return self::error( 'Alleen IBAN en naam rekeninghouder zijn toegestaan.' );
		}
		$result = Fields::update_many_for_post( $id, $input );
		return is_wp_error( $result ) ? $result : self::read( $id );
	}

	/** No bank values are copied into general profile logs or sync queues. */
	public function audit( int $id, array $changes ): void {
		if ( get_post_type( $id ) !== 'person' ) {
			return;
		}
		$keys = array_intersect( array_map( static fn( $change ) => $change[0]['canonical_name'], $changes ), self::FIELDS );
		if ( $keys ) {
			add_post_meta(
				$id,
				'_rondo_bank_audit',
				[
					'actor'  => get_current_user_id(),
					'at'     => current_datetime()->format( DATE_ATOM ),
					'fields' => array_values( $keys ),
				]
				);
		}
	}

	private static function error( string $message, int $status = 400 ): \WP_Error {
		return new \WP_Error( 'rondo_bank_account', $message, [ 'status' => $status ] );
	}
}
