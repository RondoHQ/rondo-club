<?php
/** Alternate WordPress entry points must preserve the domain's access policy. */
namespace Rondo\Core;

use Rondo\Fields\RestFields;

final class WordPressAccess {
	public function __construct() {
		add_filter( 'xmlrpc_enabled', '__return_false', PHP_INT_MAX );
		add_filter( 'xmlrpc_methods', '__return_empty_array', PHP_INT_MAX );
		add_filter( 'rest_pre_dispatch', [ $this, 'guard' ], 9, 3 );
		add_filter( 'rest_pre_insert_person', [ $this, 'guard_person_fields' ], 5, 2 );
		add_filter( 'rest_prepare_person', [ $this, 'redact_raw_meta' ], PHP_INT_MAX, 3 );
	}

	public function guard( $result, $server, $request ) {
		if ( preg_match( '#^/wp/v2/comments(?:/|$)#i', $request->get_route() ) && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error( 'rest_forbidden', 'Gebruik de Rondo-routes voor notities en activiteiten.', [ 'status' => rest_authorization_required_code() ] );
		}
		return $result;
	}

	/** Validate before core persists any part of the request. */
	public function guard_person_fields( $post, $request ) {
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$meta = $request->get_param( 'meta' );
		if ( $meta !== null ) {
			// Preserve the two existing consumers until they move to canonical fields:
			// the administrator sync's team label and finance's explicit fee exemption.
			$allowed = current_user_can( 'manage_options' ) ? [ 'team', '_exclude_from_contributie' ] : [];
			if ( UserRoles::can_manage_finances() ) {
				$allowed[] = '_exclude_from_contributie';
			}
			if ( ! is_array( $meta ) || array_diff( array_keys( $meta ), $allowed ) ) {
				return $this->denied( [ 'meta' ] );
			}
		}
		if ( current_user_can( 'manage_options' ) ) {
			return $post;
		}
		foreach ( RestFields::request_payload( $request, 'person' ) as $field => $value ) {
			if ( ! self::can_write_field( $field, (int) ( $post->ID ?? 0 ) ) ) {
				return $this->denied( [ $field ] );
			}
		}
		return $post;
	}

	/** Sensitive fields have an explicit writer, independent of visibility. */
	public static function can_write_field( string $field, int $person_id ): bool {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		if ( $field === 'vrijwilliger_sinds' || AccessControl::field_is_hidden( $field ) ) {
			return false;
		}
		if ( in_array( $field, [ 'financiele_blokkade', 'nikki_contributie_status', 'iban', 'bank_account_holder', 'nmbrs_name' ], true ) ) {
			return UserRoles::can_manage_finances();
		}
		if ( in_array( $field, [ 'datum_vog', 'vog_email_sent_date', 'vog_justis_submitted_date', 'vog_reminder_sent_date' ], true ) ) {
			return current_user_can( 'vog' );
		}
		if ( in_array( $field, [ 'iva_approved', 'datum_iva' ], true ) ) {
			return current_user_can( 'rondo_iva_approve' );
		}
		if ( in_array( $field, [ 'betaalde_vrijwilliger', 'vergoeding_reden', 'vergoeding_tot', 'vrijgesteld_handmatig', 'vrijstelling_reden', 'vrijstelling_seizoen' ], true ) ) {
			return current_user_can( 'vrijwilligers' );
		}
		return true;
	}

	private function denied( array $fields ): \WP_Error {
		return new \WP_Error(
			'rondo_person_field_scope',
			'Je mag deze velden niet wijzigen.',
			[
				'status'         => 403,
				'blocked_fields' => $fields,
			]
			);
	}

	public function redact_raw_meta( $response, $post, $request ) {
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$data    = $response->get_data();
		$allowed = [ 'team' ];
		if ( current_user_can( 'manage_options' ) || UserRoles::can_manage_finances() ) {
			$allowed[] = '_exclude_from_contributie';
		}
		if ( array_key_exists( 'meta', $data ) ) {
			$data['meta'] = array_intersect_key( (array) $data['meta'], array_flip( $allowed ) );
		}
		$response->set_data( $data );
		return $response;
	}
}
