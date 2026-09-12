<?php
/** Durable, non-sending delivery reservation. No cron or provider calls. */

namespace Rondo\Onboarding;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Dispatch {

	const TYPE = 'rondo_onboard_mail';
	const META = '_rondo_onboarding_dispatch';

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

	/**
	 * Reserve once using WordPress's unique option name, before any external work.
	 * A crash leaves a durable reservation; it must not expire into a duplicate send.
	 * The payload includes the entire future provider request, including metadata.
	 */
	public static function reserve( int $round_id, string $kind, string $email, array $payload ) {
		$email = strtolower( trim( $email ) );
		if ( get_post_type( $round_id ) !== Foundation::TYPE || ! is_email( $email ) || ! preg_match( '/^[a-z0-9_-]{1,64}$/D', $kind ) || ! $payload ) {
			return new \WP_Error( 'onboarding_dispatch_invalid', 'Ongeldige verzendregistratie.', [ 'status' => 400 ] );
		}
		$key = hash( 'sha256', $round_id . ':' . $kind . ':' . $email );
		// Never change this marker: WordPress add_option updates duplicate rows.
		// Identical payload makes an interleaved duplicate a no-op (false).
		if ( ! add_option( 'rondo_onboarding_dispatch_' . $key, 'reserved', '', false ) ) {
			return new \WP_Error( 'onboarding_dispatch_reserved', 'Deze verzending is al geregistreerd; controleer de bestaande uitkomst.', [ 'status' => 409 ] );
		}
		$data = [
			'key'          => $key,
			'round_id'     => $round_id,
			'kind'         => $kind,
			'email'        => $email,
			'payload'      => $payload,
			'payload_hash' => hash( 'sha256', wp_json_encode( $payload ) ),
			'status'       => 'reserved',
			'reserved_at'  => gmdate( 'c' ),
			'provider_id'  => null,
		];
		$id   = wp_insert_post(
			[
				'post_type'   => self::TYPE,
				'post_status' => 'private',
				'post_parent' => $round_id,
				'post_title'  => 'Verzendregistratie',
				'meta_input'  => [
					self::META        => $data,
					'_onboarding_key' => $key,
				],
			],
			true
			);
		if ( is_wp_error( $id ) ) {
			// Keep the reservation: uncertain persistence must fail closed.
			return $id;
		}
		return [ 'id' => $id ] + $data;
	}

	/** Store acceptance only with the provider's actual message identifier. */
	public static function accepted( int $id, string $provider_id ) {
		if ( get_post_type( $id ) !== self::TYPE || trim( $provider_id ) === '' ) {
			return new \WP_Error( 'onboarding_provider_id', 'Een berichtnummer van de maildienst is vereist.', [ 'status' => 400 ] );
		}
		return Foundation::locked(
			'dispatch:' . $id,
			static function () use ( $id, $provider_id ) {
				$data = get_post_meta( $id, self::META, true );
				if ( ! $data || ( $data['provider_id'] && $data['provider_id'] !== $provider_id ) ) {
					return new \WP_Error( 'onboarding_provider_conflict', 'Het berichtnummer komt niet overeen.', [ 'status' => 409 ] );
				}
				$data['status']      = 'accepted';
				$data['provider_id'] = $provider_id;
				update_post_meta( $id, self::META, $data );
				return get_post_meta( $id, self::META, true ) === $data ? $data : new \WP_Error( 'onboarding_dispatch_storage', 'Verzenduitkomst kon niet worden opgeslagen.', [ 'status' => 500 ] );
			}
			);
	}
}
