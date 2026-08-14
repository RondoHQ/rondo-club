<?php
/**
 * Guided, recoverable person merging.
 *
 * @package Rondo\Data
 */

namespace Rondo\Data;

use Rondo\Core\AccessControl;
use Rondo\Fields\Fields;
use Rondo\Fields\Formatter;
use Rondo\Fields\Registry;
use Rondo\Users\GuardianAccountService;
use Rondo\Users\UserProvisioning;
use Rondo\Volunteer\ShiftEmailScheduler;
use Rondo\Volunteer\VolunteerEligibilityService;
use Rondo\Volunteer\VolunteerObligationCalculator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Combines two person posts while preserving references and an audit trail.
 */
final class PersonMergeService {

	private const STABLE_ID_FIELDS = [
		'knvb_id',
		'sponsit_contact_id',
		'sponsit_person_id',
		'freescout_id',
	];

	private const EMAIL_FIELDS = [ 'email_1', 'email_2' ];

	private const PHONE_FIELDS = [ 'mobile_1', 'mobile_2', 'telephone_1', 'telephone_2' ];

	private const SHIFT_META_PREFIXES = [
		'_shift_signup_at_',
		'_shift_signup_user_',
		'_shift_signup_guardian_name_',
		'_shift_confirmation_queued_at_',
		'_shift_email_confirmation_sent_',
		'_shift_email_cancellation_sent_',
		'_shift_email_reminder_sent_',
		'_shift_email_survey_sent_',
		'_shift_assigned_by_',
		'_shift_assigned_at_',
		'_no_show_',
		'_volunteer_fine_invoice_',
	];

	private const PERSON_META_TO_COMBINE = [
		'_shared_with',
		'_rondo_inactive_emails',
	];

	private const PERSON_META_TO_FILL = [
		UserProvisioning::META_EMAIL_SENT,
		'vog_email_sent_date',
		'vog_justis_submitted_date',
		'vog_reminder_sent_date',
		'_volunteer_start_date',
		'_exclude_from_contributie',
	];

	/**
	 * Build the exact plan shown in the confirmation dialog.
	 *
	 * @return array|\WP_Error
	 */
	public function preview( int $primary_id, int $duplicate_id ) {
		$validation = $this->validate_pair( $primary_id, $duplicate_id );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$plan = $this->build_plan( $primary_id, $duplicate_id, [] );

		return [
			'primary'                => $this->person_summary( $primary_id ),
			'duplicate'              => $this->person_summary( $duplicate_id ),
			'conflicts'              => $plan['conflicts'],
			'blocking_conflicts'     => $plan['blocking_conflicts'],
			'automatic_changes'      => $plan['automatic_changes'],
			'automatic_change_count' => count( $plan['automatic_changes'] ),
			'references'             => $this->reference_summary( $duplicate_id ),
		];
	}

	/**
	 * Merge the duplicate into the primary and move the duplicate to trash.
	 *
	 * @param array<string,string> $resolutions Conflict choices keyed by canonical field name.
	 * @return array|\WP_Error
	 */
	public function merge( int $primary_id, int $duplicate_id, array $resolutions, int $user_id ) {
		$validation = $this->validate_pair( $primary_id, $duplicate_id );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$plan = $this->build_plan( $primary_id, $duplicate_id, $resolutions );
		if ( $plan['blocking_conflicts'] ) {
			return new \WP_Error(
				'rondo_person_merge_blocked',
				__( 'Deze personen kunnen niet veilig worden samengevoegd. Los eerst de blokkerende verschillen op.', 'rondo' ),
				[
					'status'    => 409,
					'conflicts' => $plan['blocking_conflicts'],
				]
			);
		}

		$missing = [];
		foreach ( $plan['conflicts'] as $conflict ) {
			$choice = $resolutions[ $conflict['field'] ] ?? '';
			if ( ! in_array( $choice, [ 'primary', 'duplicate' ], true ) ) {
				$missing[] = $conflict['field'];
			}
		}
		if ( $missing ) {
			return new \WP_Error(
				'rondo_person_merge_choices_required',
				__( 'Kies voor elk verschil welke waarde bewaard moet blijven.', 'rondo' ),
				[
					'status' => 400,
					'fields' => $missing,
				]
			);
		}

		$update_result = Fields::update_many_for_post( $primary_id, $plan['updates'] );
		if ( is_wp_error( $update_result ) ) {
			return $update_result;
		}

		$this->move_person_references( $duplicate_id, $primary_id );
		$this->move_linked_account( $duplicate_id, $primary_id );
		$this->move_comments( $duplicate_id, $primary_id );
		$this->move_attachments( $duplicate_id, $primary_id );
		$this->move_person_meta( $duplicate_id, $primary_id );

		// Deletion is guarded while relationships still exist. At this point every
		// inverse reference has been repointed, so the obsolete side can be cleared.
		Fields::update_for_post( $duplicate_id, 'relationships', [] );

		$now   = current_time( 'mysql', true );
		$audit = [
			'duplicate_id'   => $duplicate_id,
			'duplicate_name' => get_the_title( $duplicate_id ),
			'merged_at'      => $now,
			'merged_by'      => $user_id,
			'resolutions'    => $resolutions,
		];
		add_post_meta( $primary_id, '_rondo_person_merge_history', $audit, false );
		update_post_meta( $duplicate_id, '_rondo_merged_into_person_id', $primary_id );
		update_post_meta( $duplicate_id, '_rondo_merged_at', $now );
		update_post_meta( $duplicate_id, '_rondo_merged_by', $user_id );

		$trashed = wp_trash_post( $duplicate_id );
		if ( ! $trashed ) {
			return new \WP_Error(
				'rondo_person_merge_trash_failed',
				__( 'De gegevens zijn samengevoegd, maar het dubbele profiel kon niet naar de prullenbak.', 'rondo' ),
				[ 'status' => 500 ]
			);
		}

		AccessControl::flush_visible_person_ids_cache();
		VolunteerEligibilityService::invalidate_cache();
		VolunteerObligationCalculator::invalidate_cache();
		clean_post_cache( $primary_id );
		clean_post_cache( $duplicate_id );

		return [
			'success'      => true,
			'person_id'    => $primary_id,
			'person_name'  => get_the_title( $primary_id ),
			'duplicate_id' => $duplicate_id,
			'merged_at'    => $now,
		];
	}

	/** @return true|\WP_Error */
	private function validate_pair( int $primary_id, int $duplicate_id ) {
		if ( $primary_id <= 0 || $duplicate_id <= 0 || $primary_id === $duplicate_id ) {
			return new \WP_Error( 'rondo_person_merge_invalid_pair', __( 'Kies twee verschillende personen.', 'rondo' ), [ 'status' => 400 ] );
		}

		foreach ( [ $primary_id, $duplicate_id ] as $person_id ) {
			$post = get_post( $person_id );
			if ( ! $post || $post->post_type !== 'person' || $post->post_status !== 'publish' ) {
				return new \WP_Error( 'rondo_person_merge_not_found', __( 'Een van de personen bestaat niet of is niet gepubliceerd.', 'rondo' ), [ 'status' => 404 ] );
			}
		}

		return true;
	}

	/**
	 * @param array<string,string> $resolutions Conflict choices.
	 * @return array{updates: array<string,mixed>, conflicts: array<int,array<string,mixed>>, blocking_conflicts: array<int,array<string,mixed>>, automatic_changes: string[]}
	 */
	private function build_plan( int $primary_id, int $duplicate_id, array $resolutions ): array {
		$primary   = Fields::all_for_post( $primary_id );
		$duplicate = Fields::all_for_post( $duplicate_id );
		$updates   = [];
		$conflicts = [];
		$blocking  = [];
		$automatic = [];
		$skip      = array_flip( array_merge( self::EMAIL_FIELDS, self::PHONE_FIELDS ) );

		$this->plan_contact_fields( $primary, $duplicate, $updates, $automatic, $blocking );

		foreach ( Registry::fields_for( 'person' ) as $field => $definition ) {
			if ( $definition['storage_name'] === null || isset( $skip[ $field ] ) ) {
				continue;
			}

			$primary_value   = $primary[ $field ] ?? null;
			$duplicate_value = $duplicate[ $field ] ?? null;
			$label           = (string) ( $definition['label'] ?? $field );

			if ( $field === 'person_type' ) {
				$value = $primary_value === 'member' || $duplicate_value === 'member' ? 'member' : 'contact';
				$this->add_update( $updates, $automatic, $field, $label, $primary_value, $value );
				continue;
			}

			if ( $field === 'is_sponsor' ) {
				$value = (bool) $primary_value || (bool) $duplicate_value;
				$this->add_update( $updates, $automatic, $field, $label, (bool) $primary_value, $value );
				continue;
			}

			if ( $field === 'relationships' ) {
				$value = $this->merge_relationships( (array) $primary_value, (array) $duplicate_value, $primary_id, $duplicate_id );
				$this->add_update( $updates, $automatic, $field, $label, $primary_value, $value );
				continue;
			}

			if ( $field === 'addresses' ) {
				$value = $this->merge_addresses( (array) $primary_value, (array) $duplicate_value );
				$this->add_update( $updates, $automatic, $field, $label, $primary_value, $value );
				continue;
			}

			if ( in_array( $definition['type'], [ 'repeater', 'relationship', 'gallery', 'checkbox' ], true ) ) {
				$value = $this->merge_lists( (array) $primary_value, (array) $duplicate_value );
				$this->add_update( $updates, $automatic, $field, $label, $primary_value, $value );
				continue;
			}

			if ( $this->values_equal( $definition, $primary_value, $duplicate_value ) || $this->is_empty( $duplicate_value ) ) {
				continue;
			}

			if ( $this->is_empty( $primary_value ) ) {
				$updates[ $field ] = $duplicate_value;
				$automatic[]       = $label;
				continue;
			}

			if ( in_array( $field, self::STABLE_ID_FIELDS, true ) ) {
				$blocking[] = [
					'field'           => $field,
					'label'           => $label,
					'primary_value'   => $this->display_value( $definition, $primary_value ),
					'duplicate_value' => $this->display_value( $definition, $duplicate_value ),
					/* translators: %s: external identifier field label. */
					'message'         => sprintf( __( 'Beide profielen hebben een ander %s.', 'rondo' ), $label ),
				];
				continue;
			}

			$conflicts[] = [
				'field'           => $field,
				'label'           => $label,
				'primary_value'   => $this->display_value( $definition, $primary_value ),
				'duplicate_value' => $this->display_value( $definition, $duplicate_value ),
			];

			if ( ( $resolutions[ $field ] ?? '' ) === 'duplicate' ) {
				$updates[ $field ] = $duplicate_value;
			}
		}

		$account_block = $this->linked_account_conflict( $primary_id, $duplicate_id );
		if ( $account_block ) {
			$blocking[] = $account_block;
		}

		return [
			'updates'            => $updates,
			'conflicts'          => $conflicts,
			'blocking_conflicts' => $blocking,
			'automatic_changes'  => array_values( array_unique( $automatic ) ),
		];
	}

	/**
	 * Combine email and phone slots without storing the same value twice.
	 *
	 * @param array<string,mixed> $primary Primary fields.
	 * @param array<string,mixed> $duplicate Duplicate fields.
	 * @param array<string,mixed> $updates Planned updates.
	 * @param string[]            $automatic Automatic field labels.
	 * @param array<int,array<string,mixed>> $blocking Blocking conflicts.
	 */
	private function plan_contact_fields( array $primary, array $duplicate, array &$updates, array &$automatic, array &$blocking ): void {
		$emails = [];
		foreach ( [ $primary, $duplicate ] as $fields ) {
			foreach ( self::EMAIL_FIELDS as $field ) {
				$value = trim( (string) ( $fields[ $field ] ?? '' ) );
				$key   = strtolower( $value );
				if ( $value !== '' && ! isset( $emails[ $key ] ) ) {
					$emails[ $key ] = $value;
				}
			}
		}
		$email_values = array_values( $emails );
		if ( count( $email_values ) > count( self::EMAIL_FIELDS ) ) {
			$blocking[] = [
				'field'           => 'email_addresses',
				'label'           => __( 'E-mailadressen', 'rondo' ),
				'primary_value'   => implode( ', ', array_filter( array_map( 'strval', array_intersect_key( $primary, array_flip( self::EMAIL_FIELDS ) ) ) ) ),
				'duplicate_value' => implode( ', ', array_filter( array_map( 'strval', array_intersect_key( $duplicate, array_flip( self::EMAIL_FIELDS ) ) ) ) ),
				'message'         => __( 'Samen bevatten de profielen meer dan twee unieke e-mailadressen.', 'rondo' ),
			];
		}
		$email_values = array_slice( $email_values, 0, count( self::EMAIL_FIELDS ) );
		foreach ( self::EMAIL_FIELDS as $index => $field ) {
			$value = $email_values[ $index ] ?? '';
			$this->add_update( $updates, $automatic, $field, $index === 0 ? 'E-mailadres' : 'E-mailadres (2e)', $primary[ $field ] ?? '', $value );
		}

		$phones = [];
		foreach ( [ $primary, $duplicate ] as $fields ) {
			foreach ( self::PHONE_FIELDS as $field ) {
				$value      = trim( (string) ( $fields[ $field ] ?? '' ) );
				$normalized = $this->normalize_phone( $value );
				if ( $normalized !== '' && ! isset( $phones[ $normalized ] ) ) {
					$phones[ $normalized ] = [
						'field' => $field,
						'value' => $normalized,
					];
				}
			}
		}

		$phone_values = array_values( $phones );
		$mobile       = array_values( array_filter( $phone_values, static fn( array $item ): bool => str_starts_with( $item['field'], 'mobile_' ) ) );
		$telephone    = array_values( array_filter( $phone_values, static fn( array $item ): bool => str_starts_with( $item['field'], 'telephone_' ) ) );
		if ( count( $mobile ) > 2 || count( $telephone ) > 2 ) {
			$blocking[] = [
				'field'           => 'phone_numbers',
				'label'           => __( 'Telefoonnummers', 'rondo' ),
				'primary_value'   => implode( ', ', array_filter( array_map( 'strval', array_intersect_key( $primary, array_flip( self::PHONE_FIELDS ) ) ) ) ),
				'duplicate_value' => implode( ', ', array_filter( array_map( 'strval', array_intersect_key( $duplicate, array_flip( self::PHONE_FIELDS ) ) ) ) ),
				'message'         => __( 'Samen bevatten de profielen meer telefoonnummers dan in de beschikbare velden passen.', 'rondo' ),
			];
		}
		foreach ( [ 'mobile_1', 'mobile_2' ] as $index => $field ) {
			$value = $mobile[ $index ]['value'] ?? '';
			$this->add_update( $updates, $automatic, $field, $index === 0 ? 'Mobiel' : 'Mobiel (2e)', $primary[ $field ] ?? '', $value );
		}
		foreach ( [ 'telephone_1', 'telephone_2' ] as $index => $field ) {
			$value = $telephone[ $index ]['value'] ?? '';
			$this->add_update( $updates, $automatic, $field, $index === 0 ? 'Telefoon' : 'Telefoon (2e)', $primary[ $field ] ?? '', $value );
		}
	}

	private function normalize_phone( string $value ): string {
		$value = preg_replace( '/[\x{200B}-\x{200D}\x{FEFF}\x{200E}\x{200F}\x{202A}-\x{202E}]/u', '', trim( $value ) );
		if ( ! is_string( $value ) || $value === '' ) {
			return '';
		}
		if ( str_starts_with( $value, '+' ) ) {
			return '+' . preg_replace( '/[^0-9]/', '', substr( $value, 1 ) );
		}
		if ( str_starts_with( $value, '00' ) ) {
			return '+' . preg_replace( '/[^0-9]/', '', substr( $value, 2 ) );
		}
		if ( str_starts_with( $value, '0' ) ) {
			return '+31' . preg_replace( '/[^0-9]/', '', substr( $value, 1 ) );
		}
		return (string) preg_replace( '/[^0-9]/', '', $value );
	}

	/** @param array<string,mixed> $updates @param string[] $automatic */
	private function add_update( array &$updates, array &$automatic, string $field, string $label, $old_value, $new_value ): void {
		if ( maybe_serialize( $old_value ) === maybe_serialize( $new_value ) ) {
			return;
		}
		$updates[ $field ] = $new_value;
		$automatic[]       = $label;
	}

	/** @return array<int,array<string,mixed>> */
	private function merge_relationships( array $primary, array $duplicate, int $primary_id, int $duplicate_id ): array {
		$merged = [];
		$seen   = [];
		foreach ( array_merge( $primary, $duplicate ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$related = (int) ( $row['related_person_id'] ?? $row['related_person'] ?? 0 );
			$type    = (int) ( $row['relationship_type_id'] ?? $row['relationship_type'] ?? 0 );
			if ( $related === $duplicate_id ) {
				$related = $primary_id;
			}
			if ( $related <= 0 || $related === $primary_id ) {
				continue;
			}
			$key = $related . ':' . $type;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ]                = true;
			$row['related_person_id']    = $related;
			$row['relationship_type_id'] = $type;
			unset( $row['related_person'], $row['relationship_type'] );
			$merged[] = $row;
		}
		return $merged;
	}

	/** @return array<int,array<string,mixed>> */
	private function merge_addresses( array $primary, array $duplicate ): array {
		$merged = [];
		$index  = [];
		foreach ( array_merge( $primary, $duplicate ) as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$key = strtolower(
				preg_replace(
					'/\s+/',
					'',
					implode( '|', [ $row['postal_code'] ?? '', $row['house_number'] ?? '', $row['house_number_addition'] ?? '', $row['street_name'] ?? '', $row['city'] ?? '' ] )
				)
			);
			if ( $key !== '' && isset( $index[ $key ] ) ) {
				$position = $index[ $key ];
				foreach ( $row as $field => $value ) {
					if ( $this->is_empty( $merged[ $position ][ $field ] ?? null ) && ! $this->is_empty( $value ) ) {
						$merged[ $position ][ $field ] = $value;
					}
				}
				continue;
			}
			$index[ $key ] = count( $merged );
			$merged[]      = $row;
		}
		return $merged;
	}

	/** @return array<int,mixed> */
	private function merge_lists( array $primary, array $duplicate ): array {
		$merged = [];
		$seen   = [];
		foreach ( array_merge( $primary, $duplicate ) as $value ) {
			$key = maybe_serialize( $this->sort_recursive( $value ) );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$merged[]     = $value;
		}
		return $merged;
	}

	private function sort_recursive( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		foreach ( $value as &$item ) {
			$item = $this->sort_recursive( $item );
		}
		unset( $item );
		$is_list = $value === [] || array_keys( $value ) === range( 0, count( $value ) - 1 );
		if ( ! $is_list ) {
			ksort( $value );
		}
		return $value;
	}

	private function values_equal( array $definition, $primary, $duplicate ): bool {
		$name = (string) $definition['canonical_name'];
		try {
			$primary_wire   = Formatter::for_wire( 'person', [ $name => $primary ] )[ $name ];
			$duplicate_wire = Formatter::for_wire( 'person', [ $name => $duplicate ] )[ $name ];
		} catch ( \Throwable $error ) {
			$primary_wire   = $primary;
			$duplicate_wire = $duplicate;
		}
		return maybe_serialize( $primary_wire ) === maybe_serialize( $duplicate_wire );
	}

	private function is_empty( $value ): bool {
		return $value === null || $value === '' || $value === [];
	}

	private function display_value( array $definition, $value ): string {
		$name = (string) $definition['canonical_name'];
		try {
			$value = Formatter::for_wire( 'person', [ $name => $value ] )[ $name ];
		} catch ( \Throwable $error ) {
			// The stored value is still safe to summarize below.
		}
		if ( is_bool( $value ) ) {
			return $value ? __( 'Ja', 'rondo' ) : __( 'Nee', 'rondo' );
		}
		if ( is_array( $value ) ) {
			/* translators: %d: number of values in the field. */
			return sprintf( _n( '%d item', '%d items', count( $value ), 'rondo' ), count( $value ) );
		}
		$text = wp_strip_all_tags( (string) $value );
		return mb_strlen( $text ) > 180 ? mb_substr( $text, 0, 177 ) . '...' : $text;
	}

	/** @return array<string,mixed> */
	private function person_summary( int $person_id ): array {
		$fields = Fields::all_for_post( $person_id );
		return [
			'id'          => $person_id,
			'name'        => get_the_title( $person_id ),
			'person_type' => $fields['person_type'] ?? '',
			'is_sponsor'  => (bool) ( $fields['is_sponsor'] ?? false ),
			'knvb_id'     => (string) ( $fields['knvb_id'] ?? '' ),
			'sponsit_id'  => (string) ( $fields['sponsit_person_id'] ?? '' ),
			'emails'      => array_values( array_filter( [ $fields['email_1'] ?? '', $fields['email_2'] ?? '' ] ) ),
			'phones'      => array_values( array_filter( [ $fields['mobile_1'] ?? '', $fields['mobile_2'] ?? '', $fields['telephone_1'] ?? '', $fields['telephone_2'] ?? '' ] ) ),
			'thumbnail'   => get_the_post_thumbnail_url( $person_id, 'thumbnail' ) ?: null,
		];
	}

	/** @return array<string,int> */
	private function reference_summary( int $person_id ): array {
		return [
			'relationships' => count( (array) Fields::get_for_post( $person_id, 'relationships' ) ),
			'comments'      => (int) get_comments(
				[
					'post_id' => $person_id,
					'status'  => 'all',
					'count'   => true,
				]
				),
			'attachments'   => count(
				get_children(
				[
					'post_parent' => $person_id,
					'post_type'   => 'attachment',
					'fields'      => 'ids',
				]
				)
				),
			'accounts'      => count( $this->linked_user_ids( $person_id ) ),
			'shifts'        => $this->count_array_meta_references( 'dienst_shift', 'assigned_persons', $person_id ),
			'todos'         => $this->count_array_meta_references( 'rondo_todo', 'related_persons', $person_id ),
			'invoices'      => $this->count_scalar_meta_references( 'rondo_invoice', 'person', $person_id ),
			'cases'         => $this->count_scalar_meta_references( 'discipline_case', 'person', $person_id ),
		];
	}

	private function count_array_meta_references( string $post_type, string $meta_key, int $person_id ): int {
		return count(
			get_posts(
				[
					'post_type'      => $post_type,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_query'     => [
						'relation' => 'OR',
						[
							'key'     => $meta_key,
							'value'   => 'i:' . $person_id . ';',
							'compare' => 'LIKE',
						],
						[
							'key'     => $meta_key,
							'value'   => '"' . $person_id . '"',
							'compare' => 'LIKE',
						],
						[
							'key'     => $meta_key,
							'value'   => (string) $person_id,
							'compare' => '=',
						],
					],
				]
			)
		);
	}

	private function count_scalar_meta_references( string $post_type, string $meta_key, int $person_id ): int {
		return count(
			get_posts(
				[
					'post_type'      => $post_type,
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_key'       => $meta_key,
					'meta_value'     => $person_id,
				]
			)
		);
	}

	/** @return array<string,mixed>|null */
	private function linked_account_conflict( int $primary_id, int $duplicate_id ): ?array {
		$primary_users   = $this->linked_user_ids( $primary_id );
		$duplicate_users = $this->linked_user_ids( $duplicate_id );
		$all             = array_values( array_unique( array_merge( $primary_users, $duplicate_users ) ) );
		if ( count( $all ) <= 1 ) {
			return null;
		}

		return [
			'field'           => 'linked_account',
			'label'           => __( 'Gekoppeld account', 'rondo' ),
			'primary_value'   => implode( ', ', $primary_users ),
			'duplicate_value' => implode( ', ', $duplicate_users ),
			'message'         => __( 'Beide profielen zijn aan een ander WordPress-account gekoppeld.', 'rondo' ),
		];
	}

	/** @return int[] */
	private function linked_user_ids( int $person_id ): array {
		$ids     = array_map(
			'intval',
			get_users(
				[
					'meta_key'   => 'rondo_linked_person_id',
					'meta_value' => $person_id,
					'fields'     => 'ID',
				]
				)
		);
		$forward = (int) get_post_meta( $person_id, UserProvisioning::META_USER_ID, true );
		if ( $forward > 0 && get_userdata( $forward ) ) {
			$ids[] = $forward;
		}
		return array_values( array_unique( $ids ) );
	}

	private function move_person_references( int $source_id, int $target_id ): void {
		$this->move_relationship_references( $source_id, $target_id );
		$this->move_array_field_references( 'dienst_shift', 'assigned_persons', $source_id, $target_id );
		$this->move_array_field_references( 'rondo_todo', 'related_persons', $source_id, $target_id );
		$this->move_scalar_field_references( 'discipline_case', 'person', $source_id, $target_id );
		$this->move_scalar_field_references( 'rondo_invoice', 'person', $source_id, $target_id );
		$this->move_scalar_meta_references( 'clothing_assignment', '_clothing_person_id', $source_id, $target_id );
		$this->move_scalar_meta_references( 'rondo_invoice', '_volunteer_fine_no_show_person_id', $source_id, $target_id );
		$this->move_scalar_meta_references( 'rondo_todo', 'related_person', $source_id, $target_id );
		$this->move_shift_meta( $source_id, $target_id );

		foreach ( get_users(
			[
				'meta_key'   => GuardianAccountService::META_CHILD_ID,
				'meta_value' => $source_id,
				'fields'     => 'ID',
			]
			) as $user_id ) {
			update_user_meta( (int) $user_id, GuardianAccountService::META_CHILD_ID, $target_id );
		}
	}

	private function move_relationship_references( int $source_id, int $target_id ): void {
		$people = get_posts(
			[
				'post_type'      => 'person',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
			);
		foreach ( $people as $person_id ) {
			$person_id = (int) $person_id;
			if ( $person_id === $source_id || get_post_status( $person_id ) === 'trash' ) {
				continue;
			}
			$rows    = (array) Fields::get_for_post( $person_id, 'relationships' );
			$changed = false;
			foreach ( $rows as &$row ) {
				$related = (int) ( $row['related_person'] ?? $row['related_person_id'] ?? 0 );
				if ( $related === $source_id ) {
					if ( isset( $row['related_person_id'] ) ) {
						$row['related_person_id'] = $target_id;
					} else {
						$row['related_person'] = $target_id;
					}
					$changed = true;
				}
			}
			unset( $row );
			if ( $changed ) {
				Fields::update_for_post( $person_id, 'relationships', $rows );
			}
		}
	}

	private function move_array_field_references( string $post_type, string $field, int $source_id, int $target_id ): void {
		$posts = get_posts(
			[
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
			);
		foreach ( $posts as $post_id ) {
			$ids = array_map( 'intval', (array) Fields::get_for_post( (int) $post_id, $field ) );
			if ( ! in_array( $source_id, $ids, true ) ) {
				continue;
			}
			$ids = array_values( array_unique( array_map( static fn( int $id ): int => $id === $source_id ? $target_id : $id, $ids ) ) );
			Fields::update_for_post( (int) $post_id, $field, $ids );
		}
	}

	private function move_scalar_field_references( string $post_type, string $field, int $source_id, int $target_id ): void {
		$posts = get_posts(
			[
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => $field,
				'meta_value'     => $source_id,
				'no_found_rows'  => true,
			]
			);
		foreach ( $posts as $post_id ) {
			Fields::update_for_post( (int) $post_id, $field, $target_id );
		}
	}

	private function move_scalar_meta_references( string $post_type, string $meta_key, int $source_id, int $target_id ): void {
		$posts = get_posts(
			[
				'post_type'      => $post_type,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => $meta_key,
				'meta_value'     => $source_id,
				'no_found_rows'  => true,
			]
			);
		foreach ( $posts as $post_id ) {
			update_post_meta( (int) $post_id, $meta_key, $target_id );
		}
	}

	private function move_shift_meta( int $source_id, int $target_id ): void {
		$shift_ids   = get_posts(
			[
				'post_type'      => 'dienst_shift',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			]
			);
		$moved_queue = false;
		foreach ( $shift_ids as $shift_id ) {
			foreach ( self::SHIFT_META_PREFIXES as $prefix ) {
				$source_key = $prefix . $source_id;
				$target_key = $prefix . $target_id;
				if ( ! metadata_exists( 'post', (int) $shift_id, $source_key ) ) {
					continue;
				}
				if ( ! metadata_exists( 'post', (int) $shift_id, $target_key ) ) {
					update_post_meta( (int) $shift_id, $target_key, get_post_meta( (int) $shift_id, $source_key, true ) );
				}
				delete_post_meta( (int) $shift_id, $source_key );
				if ( $prefix === '_shift_confirmation_queued_at_' ) {
					$moved_queue = true;
				}
			}
		}

		if ( $moved_queue ) {
			$timestamp = wp_next_scheduled( ShiftEmailScheduler::SIGNUP_CONFIRMATION_CRON_HOOK, [ $source_id ] );
			wp_clear_scheduled_hook( ShiftEmailScheduler::SIGNUP_CONFIRMATION_CRON_HOOK, [ $source_id ] );
			if ( $timestamp && ! wp_next_scheduled( ShiftEmailScheduler::SIGNUP_CONFIRMATION_CRON_HOOK, [ $target_id ] ) ) {
				wp_schedule_single_event( max( time() + 1, (int) $timestamp ), ShiftEmailScheduler::SIGNUP_CONFIRMATION_CRON_HOOK, [ $target_id ] );
			}
		}
	}

	private function move_linked_account( int $source_id, int $target_id ): void {
		$user_ids = $this->linked_user_ids( $source_id );
		if ( ! $user_ids ) {
			return;
		}
		$user_id = $user_ids[0];
		update_user_meta( $user_id, 'rondo_linked_person_id', $target_id );
		update_post_meta( $target_id, UserProvisioning::META_USER_ID, $user_id );
		delete_post_meta( $source_id, UserProvisioning::META_USER_ID );

		$knvb_id = trim( (string) Fields::get_for_post( $target_id, 'knvb_id' ) );
		if ( $knvb_id !== '' ) {
			update_user_meta( $user_id, UserProvisioning::META_KNVB_ID, $knvb_id );
		}
	}

	private function move_comments( int $source_id, int $target_id ): void {
		foreach ( get_comments(
			[
				'post_id' => $source_id,
				'status'  => 'all',
			]
			) as $comment ) {
			wp_update_comment(
				[
					'comment_ID'      => (int) $comment->comment_ID,
					'comment_post_ID' => $target_id,
				]
				);
		}
	}

	private function move_attachments( int $source_id, int $target_id ): void {
		$source_thumbnail = (int) get_post_thumbnail_id( $source_id );
		$target_thumbnail = (int) get_post_thumbnail_id( $target_id );
		$attachment_ids   = get_children(
			[
				'post_parent' => $source_id,
				'post_type'   => 'attachment',
				'post_status' => 'any',
				'fields'      => 'ids',
			]
			);
		if ( $source_thumbnail > 0 && ! in_array( $source_thumbnail, $attachment_ids, true ) ) {
			$attachment_ids[] = $source_thumbnail;
		}
		foreach ( $attachment_ids as $attachment_id ) {
			wp_update_post(
				[
					'ID'          => (int) $attachment_id,
					'post_parent' => $target_id,
				]
				);
		}
		if ( $target_thumbnail <= 0 && $source_thumbnail > 0 ) {
			set_post_thumbnail( $target_id, $source_thumbnail );
		}
	}

	private function move_person_meta( int $source_id, int $target_id ): void {
		foreach ( self::PERSON_META_TO_COMBINE as $meta_key ) {
			$target = (array) get_post_meta( $target_id, $meta_key, true );
			$source = (array) get_post_meta( $source_id, $meta_key, true );
			$value  = $this->merge_lists( $target, $source );
			if ( $value ) {
				update_post_meta( $target_id, $meta_key, $value );
			}
		}
		foreach ( self::PERSON_META_TO_FILL as $meta_key ) {
			if ( ! metadata_exists( 'post', $target_id, $meta_key ) && metadata_exists( 'post', $source_id, $meta_key ) ) {
				update_post_meta( $target_id, $meta_key, get_post_meta( $source_id, $meta_key, true ) );
			}
		}
	}
}
