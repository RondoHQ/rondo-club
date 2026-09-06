<?php
/**
 * Member-facing contact and household profile changes.
 *
 * @package Rondo\Users
 */

namespace Rondo\Users;

use Rondo\Core\AccessControl;
use Rondo\Core\PhoneNormalizer;
use Rondo\Fields\Fields;
use Rondo\Identity\OidcIdentity;
use Rondo\Notifications\EmailTemplate;
use Rondo\Pages\PublicPageChrome;
use Rondo\People\CommunicationPolicy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MemberProfileService {

	private const TOKEN_PREFIX     = 'rondo_email_change_';
	private const TOKEN_TTL        = 2 * HOUR_IN_SECONDS;
	private const PENDING_META     = '_rondo_pending_email_change';
	private const RATE_USER_PREFIX = 'rondo_email_change_user_';
	private const RATE_IP_PREFIX   = 'rondo_email_change_ip_';
	private const PHONE_FIELDS     = [ 'mobile_1', 'mobile_2', 'telephone_1', 'telephone_2' ];

	/** Return the linked published person or a REST-ready error. */
	public static function linked_person_id( int $user_id ) {
		$person_id = (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );
		$person    = $person_id ? get_post( $person_id ) : null;
		if ( ! $person || $person->post_type !== 'person' || $person->post_status !== 'publish' ) {
			return new \WP_Error( 'rondo_profile_not_linked', 'Er is geen ledenprofiel aan dit account gekoppeld.', [ 'status' => 409 ] );
		}
		if ( CommunicationPolicy::is_deceased( $person_id ) ) {
			return new \WP_Error( 'rondo_profile_readonly', 'Dit ledenprofiel is alleen-lezen.', [ 'status' => 403 ] );
		}
		if ( (bool) Fields::try_get_for_post( $person_id, 'former_member' ) && ! user_can( $user_id, 'manage_options' ) ) {
			return new \WP_Error( 'rondo_former_member_readonly', 'Een oud-lid kan de ledengegevens niet zelf wijzigen.', [ 'status' => 403 ] );
		}
		return $person_id;
	}

	/** Request verification before changing or promoting an email address. */
	public static function request_email_change( int $user_id, string $slot, string $email, string $ip, ?int $requested_person_id = null ) {
		$person_id = self::editable_person_id( $user_id, $requested_person_id );
		if ( is_wp_error( $person_id ) ) {
			return $person_id;
		}
		if ( ! in_array( $slot, [ 'primary', 'secondary' ], true ) ) {
			return new \WP_Error( 'rondo_invalid_email_slot', 'Kies een geldig e-mailveld.', [ 'status' => 400 ] );
		}

		$email = strtolower( trim( sanitize_email( $email ) ) );
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'rondo_invalid_email', 'Vul een geldig e-mailadres in.', [ 'status' => 400 ] );
		}

		$primary   = self::email( $person_id, 'email_1' );
		$secondary = self::email( $person_id, 'email_2' );
		$current   = $slot === 'primary' ? $primary : $secondary;
		if ( $email === $current ) {
			return new \WP_Error( 'rondo_email_unchanged', 'Dit e-mailadres staat al op deze plaats.', [ 'status' => 400 ] );
		}
		if ( $slot === 'secondary' && $email === $primary ) {
			return new \WP_Error( 'rondo_duplicate_email', 'Dit is al het primaire e-mailadres.', [ 'status' => 400 ] );
		}
		if ( self::is_rate_limited( $user_id, $ip ) ) {
			return new \WP_Error( 'rondo_email_rate_limited', 'Er zijn te veel verificatiemails aangevraagd. Probeer het later opnieuw.', [ 'status' => 429 ] );
		}

		self::cancel_email_change( $user_id );
		self::record_attempt( $user_id, $ip );
		$operation = $slot === 'primary' && $email === $secondary ? 'promote' : 'replace';
		$token     = bin2hex( random_bytes( 32 ) );
		$hash      = hash( 'sha256', $token );
		$payload   = [
			'user_id'       => $user_id,
			'person_id'     => $person_id,
			'slot'          => $slot,
			'operation'     => $operation,
			'new_email'     => $email,
			'old_primary'   => $primary,
			'old_secondary' => $secondary,
			'expires_at'    => time() + self::TOKEN_TTL,
			'token_hash'    => $hash,
		];

		set_transient( self::TOKEN_PREFIX . $hash, $payload, self::TOKEN_TTL );
		update_user_meta( $user_id, self::PENDING_META, $payload );

		if ( ! self::send_verification_email( $email, $token, $slot, $operation ) ) {
			self::cancel_email_change( $user_id );
			return new \WP_Error( 'rondo_email_send_failed', 'De verificatiemail kon niet worden verstuurd.', [ 'status' => 500 ] );
		}

		return [
			'pending'    => true,
			'person_id'  => $person_id,
			'email'      => $email,
			'slot'       => $slot,
			'operation'  => $operation,
			'expires_at' => gmdate( DATE_ATOM, $payload['expires_at'] ),
		];
	}

	/** Return current pending verification without exposing the token hash. */
	public static function pending_email_change( int $user_id, ?int $person_id = null ): ?array {
		$pending = get_user_meta( $user_id, self::PENDING_META, true );
		if ( ! is_array( $pending ) || empty( $pending['token_hash'] ) || (int) ( $pending['expires_at'] ?? 0 ) <= time() ) {
			self::cancel_email_change( $user_id );
			return null;
		}
		if ( get_transient( self::TOKEN_PREFIX . $pending['token_hash'] ) === false ) {
			self::cancel_email_change( $user_id );
			return null;
		}
		if ( $person_id && (int) ( $pending['person_id'] ?? 0 ) !== $person_id ) {
			return null;
		}

		return [
			'person_id'  => (int) $pending['person_id'],
			'email'      => (string) $pending['new_email'],
			'slot'       => (string) $pending['slot'],
			'operation'  => (string) $pending['operation'],
			'expires_at' => gmdate( DATE_ATOM, (int) $pending['expires_at'] ),
		];
	}

	/** Cancel the current user's pending email change. */
	public static function cancel_email_change( int $user_id, ?int $person_id = null ): void {
		$pending = get_user_meta( $user_id, self::PENDING_META, true );
		if ( $person_id && is_array( $pending ) && (int) ( $pending['person_id'] ?? 0 ) !== $person_id ) {
			return;
		}
		if ( is_array( $pending ) && ! empty( $pending['token_hash'] ) ) {
			delete_transient( self::TOKEN_PREFIX . $pending['token_hash'] );
		}
		delete_user_meta( $user_id, self::PENDING_META );
	}

	/** Verify a public token and atomically apply the requested email action. */
	public static function verify_email_token( string $token ) {
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return new \WP_Error( 'rondo_invalid_email_token', 'Deze verificatielink is ongeldig of verlopen.' );
		}
		$hash    = hash( 'sha256', $token );
		$payload = get_transient( self::TOKEN_PREFIX . $hash );
		if ( ! is_array( $payload ) || ! hash_equals( (string) ( $payload['token_hash'] ?? '' ), $hash ) ) {
			return new \WP_Error( 'rondo_invalid_email_token', 'Deze verificatielink is ongeldig of verlopen.' );
		}

		$user_id   = (int) $payload['user_id'];
		$person_id = self::editable_person_id( $user_id, (int) $payload['person_id'] );
		$pending   = get_user_meta( $user_id, self::PENDING_META, true );
		if ( is_wp_error( $person_id ) || ! is_array( $pending ) || ! hash_equals( (string) ( $pending['token_hash'] ?? '' ), $hash ) ) {
			self::cancel_email_change( $user_id );
			return new \WP_Error( 'rondo_stale_email_token', 'Deze verificatielink hoort niet meer bij het huidige profiel.' );
		}
		if ( self::email( $person_id, 'email_1' ) !== (string) $payload['old_primary'] || self::email( $person_id, 'email_2' ) !== (string) $payload['old_secondary'] ) {
			self::cancel_email_change( $user_id );
			return new \WP_Error( 'rondo_stale_email_change', 'De e-mailgegevens zijn ondertussen gewijzigd. Vraag een nieuwe verificatielink aan.' );
		}

		$result = self::apply_verified_email_change( $payload );
		if ( ! is_wp_error( $result ) ) {
			delete_transient( self::TOKEN_PREFIX . $hash );
			delete_user_meta( $user_id, self::PENDING_META );
		}
		return $result;
	}

	/** Remove the secondary email immediately and mirror matching child slots. */
	public static function remove_secondary_email( int $user_id, ?int $requested_person_id = null ) {
		$person_id = self::editable_person_id( $user_id, $requested_person_id );
		if ( is_wp_error( $person_id ) ) {
			return $person_id;
		}
		$old = self::email( $person_id, 'email_2' );
		if ( $old === '' ) {
			return new \WP_Error( 'rondo_no_secondary_email', 'Er is geen tweede e-mailadres om te verwijderen.', [ 'status' => 400 ] );
		}

		$updates   = [ $person_id => [ 'email_2' => '' ] ];
		$linked_id = (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );
		if ( $person_id === $linked_id ) {
			foreach ( self::minor_child_ids( $user_id, $person_id ) as $child_id ) {
				if ( self::email( $child_id, 'email_2' ) === $old ) {
					$updates[ $child_id ] = [ 'email_2' => '' ];
				}
			}
		}

		return self::persist_changes( $updates, 'email_removed', false, $user_id );
	}

	/** Replace all four Rondo phone slots for the linked person. */
	public static function update_phones( int $user_id, array $values, ?int $requested_person_id = null ) {
		$person_id = self::editable_person_id( $user_id, $requested_person_id );
		if ( is_wp_error( $person_id ) ) {
			return $person_id;
		}

		$normalized = [];
		foreach ( self::PHONE_FIELDS as $field ) {
			$value = PhoneNormalizer::normalize( trim( (string) ( $values[ $field ] ?? '' ) ) );
			if ( $value !== '' && ! preg_match( '/^\+[1-9][0-9]{7,14}$/', (string) $value ) ) {
				return new \WP_Error( 'rondo_invalid_phone', 'Vul een geldig telefoonnummer met landcode in.', [ 'status' => 400 ] );
			}
			$normalized[ $field ] = $value;
		}

		return self::persist_changes( [ $person_id => $normalized ], 'phones', false, $user_id );
	}

	/** Update the Home address for every applicable person in the household. */
	public static function update_household_address( int $user_id, array $input ) {
		$person_id = self::linked_person_id( $user_id );
		if ( is_wp_error( $person_id ) ) {
			return $person_id;
		}

		$country      = sanitize_text_field( trim( (string) ( $input['country'] ?? '' ) ) );
		$country      = $country !== '' ? $country : 'Nederland';
		$country_code = strtoupper( sanitize_text_field( trim( (string) ( $input['country_code'] ?? '' ) ) ) );
		if ( $country_code === '' && in_array( strtolower( $country ), [ 'nederland', 'netherlands' ], true ) ) {
			$country_code = 'NL';
		}

		$address = [
			'address_label'         => 'Home',
			'street_name'           => sanitize_text_field( (string) ( $input['street_name'] ?? '' ) ),
			'house_number'          => sanitize_text_field( (string) ( $input['house_number'] ?? '' ) ),
			'house_number_addition' => sanitize_text_field( (string) ( $input['house_number_addition'] ?? '' ) ),
			'postal_code'           => strtoupper( sanitize_text_field( (string) ( $input['postal_code'] ?? '' ) ) ),
			'city'                  => sanitize_text_field( (string) ( $input['city'] ?? '' ) ),
			'state'                 => sanitize_text_field( (string) ( $input['state'] ?? '' ) ),
			'country'               => $country,
			'country_code'          => $country_code,
		];
		if ( $address['street_name'] === '' || $address['house_number'] === '' || $address['postal_code'] === '' || $address['city'] === '' ) {
			return new \WP_Error( 'rondo_incomplete_address', 'Vul straat, huisnummer, postcode en plaats in.', [ 'status' => 400 ] );
		}
		if ( $address['country'] === '' || ! preg_match( '/^[A-Z]{2,3}$/', $address['country_code'] ) ) {
			return new \WP_Error( 'rondo_invalid_country', 'Vul een land en een geldige landcode in.', [ 'status' => 400 ] );
		}
		if ( $address['country_code'] === 'NL' && ! preg_match( '/^[1-9][0-9]{3}\s?[A-Z]{2}$/', $address['postal_code'] ) ) {
			return new \WP_Error( 'rondo_invalid_postal_code', 'Vul een geldige Nederlandse postcode in.', [ 'status' => 400 ] );
		}
		$address['postal_code'] = preg_replace( '/^([1-9][0-9]{3})\s?([A-Z]{2})$/', '$1 $2', $address['postal_code'] );

		$updates = [];
		foreach ( AccessControl::get_visible_person_ids( $user_id ) as $target_id ) {
			if ( CommunicationPolicy::is_deceased( $target_id ) || (bool) Fields::get_for_post( $target_id, 'former_member' ) ) {
				continue;
			}
			$addresses = Fields::get_for_post( $target_id, 'addresses' );
			$addresses = is_array( $addresses ) ? $addresses : [];
			$home      = null;
			foreach ( $addresses as $index => $row ) {
				if ( strtolower( trim( (string) ( $row['address_label'] ?? '' ) ) ) === 'home' ) {
					$home = $index;
					break;
				}
			}
			if ( $home === null ) {
				array_unshift( $addresses, $address );
			} else {
				$addresses[ $home ] = array_merge( $addresses[ $home ], $address );
			}
			$updates[ $target_id ] = [ 'addresses' => $addresses ];
		}

		if ( empty( $updates ) ) {
			return new \WP_Error( 'rondo_no_address_targets', 'Er zijn geen schrijfbare gezinsleden gevonden.', [ 'status' => 409 ] );
		}
		return self::persist_changes( $updates, 'address', false, $user_id );
	}

	/** Apply the verified primary/secondary operation and matching child updates. */
	private static function apply_verified_email_change( array $payload ) {
		$user_id       = (int) $payload['user_id'];
		$person_id     = (int) $payload['person_id'];
		$new           = (string) $payload['new_email'];
		$old_primary   = (string) $payload['old_primary'];
		$old_secondary = (string) $payload['old_secondary'];
		$slot          = (string) $payload['slot'];
		$operation     = (string) $payload['operation'];

		if ( $operation === 'promote' ) {
			$updates = [
				$person_id => [
					'email_1' => $old_secondary,
					'email_2' => $old_primary,
				],
			];
			$type    = 'email_promoted';
		} else {
			$field   = $slot === 'primary' ? 'email_1' : 'email_2';
			$updates = [ $person_id => [ $field => $new ] ];
			$type    = $slot === 'primary' ? 'email_primary' : 'email_secondary';
		}

		$linked_id = (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );
		foreach ( $person_id === $linked_id ? self::minor_child_ids( $user_id, $person_id ) : [] as $child_id ) {
			$child_primary   = self::email( $child_id, 'email_1' );
			$child_secondary = self::email( $child_id, 'email_2' );
			$child_update    = [];
			if ( $operation === 'promote' ) {
				if ( $child_primary === $old_primary && $child_secondary === $old_secondary ) {
					$child_update = [
						'email_1' => $old_secondary,
						'email_2' => $old_primary,
					];
				}
			} elseif ( $slot === 'primary' ) {
				if ( $child_primary === $old_primary ) {
					$child_update['email_1'] = $new;
				}
				if ( $old_primary !== '' && $child_secondary === $old_primary ) {
					$child_update['email_2'] = $new;
				}
			} elseif ( $old_secondary !== '' && $child_secondary === $old_secondary ) {
				$child_update['email_2'] = $new;
			} elseif ( $old_secondary === '' && $child_primary === $old_primary && $child_secondary === '' ) {
				$child_update['email_2'] = $new;
			}
			if ( ! empty( $child_update ) ) {
				$updates[ $child_id ] = $child_update;
			}
		}

		$old_account = null;
		if ( $slot === 'primary' && $person_id === $linked_id ) {
			$old_account = self::sync_account_email( $user_id, $person_id, $new );
			if ( is_wp_error( $old_account ) ) {
				return $old_account;
			}
		}

		$result = self::persist_changes( $updates, $type, true, $user_id );
		if ( is_wp_error( $result ) && is_array( $old_account ) ) {
			wp_update_user(
				[
					'ID'         => $user_id,
					'user_email' => $old_account['user_email'],
				]
				);
			update_user_meta( $user_id, UserProvisioning::META_CONTACT_EMAIL, $old_account['contact_email'] );
		} elseif ( ! is_wp_error( $result ) && $slot === 'primary' && $person_id === $linked_id ) {
			OidcIdentity::mark_email_verified( $user_id, $new, 'profile_email_change' );
		}
		return $result;
	}

	/** Resolve the linked member or one of their visible, writable minor children. */
	private static function editable_person_id( int $user_id, ?int $requested_person_id = null ) {
		$linked_id = self::linked_person_id( $user_id );
		if ( is_wp_error( $linked_id ) ) {
			return $linked_id;
		}
		$person_id = $requested_person_id ?: $linked_id;
		if ( $person_id !== $linked_id && ! in_array( $person_id, AccessControl::get_visible_person_ids( $user_id ), true ) ) {
			return new \WP_Error( 'rondo_profile_target_forbidden', 'Je kunt alleen je eigen gegevens en die van je minderjarige kinderen wijzigen.', [ 'status' => 403 ] );
		}
		$person = get_post( $person_id );
		if ( ! $person || $person->post_type !== 'person' || $person->post_status !== 'publish' ) {
			return new \WP_Error( 'rondo_profile_target_not_found', 'Dit gezinslid kon niet worden gevonden.', [ 'status' => 404 ] );
		}
		if ( CommunicationPolicy::is_deceased( $person_id ) || ( (bool) Fields::try_get_for_post( $person_id, 'former_member' ) && ! user_can( $user_id, 'manage_options' ) ) ) {
			return new \WP_Error( 'rondo_profile_readonly', 'Dit ledenprofiel is alleen-lezen.', [ 'status' => 403 ] );
		}
		return $person_id;
	}

	/** Persist a set of canonical person changes, touch modified dates and log once. */
	private static function persist_changes( array $updates, string $type, bool $verified, int $actor_id ) {
		$log_changes = [];
		$rollback    = [];
		foreach ( $updates as $person_id => $fields ) {
			$person_id = (int) $person_id;
			$actual    = [];
			foreach ( $fields as $field => $new_value ) {
				$old_value = Fields::get_for_post( $person_id, $field );
				if ( maybe_serialize( $old_value ) === maybe_serialize( $new_value ) ) {
					continue;
				}
				$actual[ $field ]                 = $new_value;
				$rollback[ $person_id ][ $field ] = $old_value;
				$log_change                       = [
					'person_id'   => $person_id,
					'person_name' => get_the_title( $person_id ),
					'field'       => $field,
					'label'       => self::field_label( $field ),
					'action'      => self::change_action( $old_value, $new_value, $type ),
					'old'         => self::display_value( $field, $old_value ),
					'new'         => self::display_value( $field, $new_value ),
					'sync'        => $field !== 'telephone_2',
				];
				if ( $field === 'addresses' ) {
					$sync_fields               = self::changed_home_address_fields( $old_value, $new_value );
					$log_change['sync']        = ! empty( $sync_fields );
					$log_change['sync_fields'] = $sync_fields;
				}
				$log_changes[] = $log_change;
			}
			if ( empty( $actual ) ) {
				continue;
			}
			$result = Fields::update_many_for_post( $person_id, $actual );
			if ( is_wp_error( $result ) ) {
				self::rollback( $rollback );
				return $result;
			}
			wp_update_post( [ 'ID' => $person_id ] );
		}

		if ( empty( $log_changes ) ) {
			return new \WP_Error( 'rondo_profile_unchanged', 'Er zijn geen wijzigingen om op te slaan.', [ 'status' => 400 ] );
		}
		$log_id = ProfileChangeLog::record( $type, $log_changes, $verified, $actor_id );
		if ( is_wp_error( $log_id ) ) {
			self::rollback( $rollback );
			return $log_id;
		}

		return [
			'success'  => true,
			'log_id'   => $log_id,
			'affected' => array_values( array_unique( array_map( static fn( $change ) => (int) $change['person_id'], $log_changes ) ) ),
		];
	}

	private static function rollback( array $rollback ): void {
		foreach ( $rollback as $person_id => $fields ) {
			Fields::update_many_for_post( (int) $person_id, $fields );
			wp_update_post( [ 'ID' => (int) $person_id ] );
		}
	}

	/** Update account delivery/login mirrors and return their previous values. */
	private static function sync_account_email( int $user_id, int $person_id, string $email ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return new \WP_Error( 'rondo_user_not_found', 'Account niet gevonden.' );
		}
		$existing = email_exists( $email );
		$wp_email = ! $existing || (int) $existing === $user_id
			? $email
			: sprintf( 'person-%d@%s', $person_id, UserProvisioning::SYNTHETIC_EMAIL_DOMAIN );
		$old      = [
			'user_email'    => $user->user_email,
			'contact_email' => (string) get_user_meta( $user_id, UserProvisioning::META_CONTACT_EMAIL, true ),
		];
		$result   = wp_update_user(
			[
				'ID'         => $user_id,
				'user_email' => $wp_email,
			]
			);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		update_user_meta( $user_id, UserProvisioning::META_CONTACT_EMAIL, $email );
		return $old;
	}

	private static function minor_child_ids( int $user_id, int $self_id ): array {
		return array_values( array_diff( AccessControl::get_visible_person_ids( $user_id ), [ $self_id ] ) );
	}

	private static function email( int $person_id, string $field ): string {
		$email = strtolower( trim( sanitize_email( (string) Fields::get_for_post( $person_id, $field ) ) ) );
		return is_email( $email ) ? $email : '';
	}

	private static function send_verification_email( string $email, string $token, string $slot, string $operation ): bool {
		$branding = PublicPageChrome::branding();
		$url      = home_url( '/email-wijzigen/' . $token );
		$subject  = 'Bevestig je nieuwe e-mailadres';
		$action   = $operation === 'promote' ? 'primair maken' : ( $slot === 'primary' ? 'als primair e-mailadres instellen' : 'als tweede e-mailadres instellen' );
		$body     = '<p>Je hebt gevraagd om <strong>' . esc_html( $email ) . '</strong> ' . esc_html( $action ) . '.</p>'
			. '<p><strong>Let op:</strong> dit e-mailadres wordt ook aangepast in Sportlink, het systeem van de KNVB. Daarna moet je in Voetbal.nl inloggen met het nieuwe e-mailadres.</p>'
			. '<p>Deze link is twee uur geldig. Heb je dit niet aangevraagd, dan hoef je niets te doen.</p>';
		$html     = EmailTemplate::render(
			[
				'brand_name'   => $branding['name'],
				'preheader'    => $subject,
				'eyebrow'      => 'Mijn gegevens',
				'heading'      => $subject,
				'body_html'    => $body,
				'cta_url'      => $url,
				'cta_label'    => 'E-mailadres bevestigen',
				'accent_color' => $branding['accent_color'],
			]
		);
		$headers  = [
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', sanitize_text_field( $branding['name'] ), ActivationService::ACTIVATION_FROM_EMAIL ),
		];
		return (bool) wp_mail( $email, $subject, $html, $headers );
	}

	private static function is_rate_limited( int $user_id, string $ip ): bool {
		return (int) get_transient( self::RATE_USER_PREFIX . $user_id ) >= 3
			|| (int) get_transient( self::RATE_IP_PREFIX . md5( $ip ) ) >= 10;
	}

	private static function record_attempt( int $user_id, string $ip ): void {
		$user_key = self::RATE_USER_PREFIX . $user_id;
		$ip_key   = self::RATE_IP_PREFIX . md5( $ip );
		set_transient( $user_key, (int) get_transient( $user_key ) + 1, HOUR_IN_SECONDS );
		set_transient( $ip_key, (int) get_transient( $ip_key ) + 1, HOUR_IN_SECONDS );
	}

	private static function field_label( string $field ): string {
		return match ( $field ) {
			'email_1'      => 'Primair e-mailadres',
			'email_2'      => 'Tweede e-mailadres',
			'mobile_1'     => 'Mobiel',
			'mobile_2'     => 'Mobiel 2',
			'telephone_1'  => 'Telefoon',
			'telephone_2'  => 'Telefoon 2',
			'addresses'    => 'Woonadres',
			default        => $field,
		};
	}

	private static function change_action( $old, $new, string $type ): string {
		if ( $type === 'email_promoted' ) {
			return 'primair_maken';
		}
		if ( $new === '' || $new === null ) {
			return 'verwijderen';
		}
		if ( $old === '' || $old === null || $old === [] ) {
			return 'toevoegen';
		}
		return 'wijzigen';
	}

	private static function display_value( string $field, $value ) {
		if ( $field !== 'addresses' ) {
			return is_scalar( $value ) ? (string) $value : '';
		}
		$addresses = is_array( $value ) ? $value : [];
		foreach ( $addresses as $address ) {
			if ( strtolower( trim( (string) ( $address['address_label'] ?? '' ) ) ) !== 'home' ) {
				continue;
			}
			return trim(
				implode(
				', ',
				array_filter(
				[
					trim( (string) ( $address['street_name'] ?? '' ) . ' ' . (string) ( $address['house_number'] ?? '' ) . (string) ( $address['house_number_addition'] ?? '' ) ),
					trim( (string) ( $address['postal_code'] ?? '' ) . ' ' . (string) ( $address['city'] ?? '' ) ),
				]
				)
				)
				);
		}
		return '';
	}

	/** Return only Home-address fields whose value really changed. */
	private static function changed_home_address_fields( $old_addresses, $new_addresses ): array {
		$tracked = [ 'street_name', 'house_number', 'house_number_addition', 'postal_code', 'city', 'country_code' ];
		$old     = self::home_address_row( $old_addresses );
		$new     = self::home_address_row( $new_addresses );
		return array_values(
			array_filter(
				$tracked,
				static fn( string $field ): bool => (string) ( $old[ $field ] ?? '' ) !== (string) ( $new[ $field ] ?? '' )
			)
		);
	}

	private static function home_address_row( $addresses ): array {
		foreach ( is_array( $addresses ) ? $addresses : [] as $address ) {
			if ( strtolower( trim( (string) ( $address['address_label'] ?? '' ) ) ) === 'home' ) {
				return is_array( $address ) ? $address : [];
			}
		}
		return [];
	}
}
