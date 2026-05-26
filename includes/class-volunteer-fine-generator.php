<?php
/**
 * VolunteerFineGenerator
 *
 * Implements Fase E #7 — boete-pipeline. Triggered by the
 * `rondo_volunteer_no_show_marked` action (see ShiftScheduler).
 *
 * Bestuursbesluiten 2026-05-26:
 *   - Trigger:    alleen no-show direct (geen eindseizoen-batch)
 *   - Ontvanger:  primaire ouder uit relationships voor JO15- kind,
 *                 speler zelf voor O17+ (eligibility kind = speler)
 *   - Bedrag:     €30 per gemiste dienst
 *   - Vrijkoop:   nee — plicht blijft staan, boete is sanctie
 *
 * Each no-show event produces one `rondo_invoice` of `invoice_type=volunteer_fine`
 * with one line item describing the shift, routed to the primary parent (or
 * the player themselves for adult units). Idempotency guard: the shift carries
 * a `_volunteer_fine_invoice_{person_id}` meta after first generation; repeated
 * triggers are no-ops.
 *
 * @package Rondo\Volunteer
 */

namespace Rondo\Volunteer;

use Rondo\Data\InverseRelationships;
use Rondo\Finance\InvoiceNumbering;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VolunteerFineGenerator {

	const FINE_AMOUNT_EUR     = 30.0;
	const INVOICE_TYPE        = 'volunteer_fine';
	const INVOICE_KIND        = 'person';
	const SHIFT_INVOICE_META  = '_volunteer_fine_invoice_';

	/**
	 * Generate a boete invoice for the given (shift, person) no-show.
	 *
	 * @return int|\WP_Error Invoice post ID on success, WP_Error on failure.
	 */
	public static function on_no_show( int $shift_id, int $person_id, ?int $triggered_by = null ) {
		// Idempotency: never double-bill a (shift, person) pair.
		$existing = (int) get_post_meta( $shift_id, self::SHIFT_INVOICE_META . $person_id, true );
		if ( $existing > 0 && get_post_type( $existing ) === 'rondo_invoice' ) {
			return $existing;
		}

		$shift_post = get_post( $shift_id );
		if ( ! $shift_post || $shift_post->post_type !== 'dienst_shift' ) {
			return new \WP_Error( 'invalid_shift', 'Invalid shift ID.', [ 'status' => 404 ] );
		}

		$person_post = get_post( $person_id );
		if ( ! $person_post || $person_post->post_type !== 'person' ) {
			return new \WP_Error( 'invalid_person', 'Invalid person ID.', [ 'status' => 404 ] );
		}

		$invoice_recipient = self::resolve_recipient( $person_id );
		if ( $invoice_recipient === null ) {
			return new \WP_Error(
				'no_recipient',
				'Could not resolve a primary parent for this person.',
				[ 'status' => 422 ]
			);
		}

		$dienst_type_id   = (int) get_post_meta( $shift_id, 'dienst_type_id', true );
		$dienst_type_name = $dienst_type_id > 0 ? get_the_title( $dienst_type_id ) : 'Vrijwilligersdienst';
		$start_datetime   = (string) get_post_meta( $shift_id, 'start_datetime', true );
		$shift_date_label = $start_datetime !== '' ? gmdate( 'd-m-Y', strtotime( $start_datetime ) ) : '';

		$description = sprintf(
			'Gemiste vrijwilligersdienst%s — %s (%s)',
			$shift_date_label !== '' ? ' op ' . $shift_date_label : '',
			$dienst_type_name,
			$person_post->post_title
		);

		$invoice_number = InvoiceNumbering::generate_next( self::INVOICE_TYPE );

		$invoice_id = wp_insert_post(
			[
				'post_type'   => 'rondo_invoice',
				'post_title'  => $invoice_number,
				'post_status' => 'rondo_draft',
				'post_author' => $triggered_by ?: get_current_user_id(),
			],
			true
		);

		if ( is_wp_error( $invoice_id ) || $invoice_id === 0 ) {
			return new \WP_Error(
				'invoice_create_failed',
				'Could not create the volunteer fine invoice.',
				[ 'status' => 500 ]
			);
		}

		update_field( 'invoice_number', $invoice_number, $invoice_id );
		update_field( 'invoice_type', self::INVOICE_TYPE, $invoice_id );
		update_field( 'person', $invoice_recipient, $invoice_id );
		update_field( 'total_amount', self::FINE_AMOUNT_EUR, $invoice_id );
		update_field(
			'line_items',
			[
				[
					'description' => $description,
					'amount'      => self::FINE_AMOUNT_EUR,
				],
			],
			$invoice_id
		);
		update_field( 'status', 'draft', $invoice_id );

		update_post_meta( $invoice_id, '_invoice_kind', self::INVOICE_KIND );
		update_post_meta( $invoice_id, '_volunteer_fine_shift_id', $shift_id );
		update_post_meta( $invoice_id, '_volunteer_fine_no_show_person_id', $person_id );

		// Back-reference so we can avoid double-billing.
		update_post_meta( $shift_id, self::SHIFT_INVOICE_META . $person_id, $invoice_id );

		/**
		 * Fires after a volunteer-fine invoice is created. Use to plug in
		 * automatic Mollie link generation, immediate dispatch, etc.
		 *
		 * @param int $invoice_id  Newly created rondo_invoice post ID.
		 * @param int $shift_id    Source dienst_shift post ID.
		 * @param int $person_id   The person who was no-show'd.
		 * @param int $recipient   The invoice recipient (parent or self).
		 */
		do_action( 'rondo_volunteer_fine_created', $invoice_id, $shift_id, $person_id, $invoice_recipient );

		return $invoice_id;
	}

	/**
	 * Resolve the invoice recipient for a no-show'd person.
	 *
	 *   - Adults (no parent relationships, or O17+ league member) → the person themselves.
	 *   - JO15- children → the FIRST `relationship_type = TYPE_PARENT` entry on the
	 *     child's relationships repeater (oldest entry in array, consistent with
	 *     the "primaire ouder" intent of the bestuursbesluit).
	 *
	 * Returns null when there is genuinely no candidate.
	 */
	public static function resolve_recipient( int $person_id ): ?int {
		$eligibility = new VolunteerEligibilityService();
		$age         = $eligibility->age_group_number( $person_id );

		// O17+ or unknown-but-adult → person themselves.
		if ( $age === null || $age >= VolunteerEligibilityService::ADULT_MIN_AGE ) {
			return $person_id;
		}

		// JO15- → primary parent from relationships.
		$rels = get_field( 'relationships', $person_id );
		if ( is_array( $rels ) ) {
			foreach ( $rels as $rel ) {
				$type_id = self::extract_term_id( $rel['relationship_type'] ?? null );
				if ( $type_id !== InverseRelationships::TYPE_PARENT ) {
					continue;
				}
				$parent_id = self::extract_person_id( $rel['related_person'] ?? null );
				if ( $parent_id ) {
					return (int) $parent_id;
				}
			}
		}

		// Fallback: any adult housemate from the eligibility unit.
		$unit = $eligibility->get_eligible_unit_for_person( $person_id );
		if ( $unit !== null ) {
			foreach ( (array) ( $unit['person_ids'] ?? [] ) as $candidate ) {
				$candidate = (int) $candidate;
				if ( $candidate === $person_id ) {
					continue;
				}
				$candidate_age = $eligibility->age_group_number( $candidate );
				if ( $candidate_age === null || $candidate_age >= VolunteerEligibilityService::ADULT_MIN_AGE ) {
					return $candidate;
				}
			}
		}

		return null;
	}

	private static function extract_term_id( $value ): ?int {
		if ( is_object( $value ) ) {
			return isset( $value->term_id ) ? (int) $value->term_id : null;
		}
		if ( is_array( $value ) ) {
			return isset( $value['term_id'] ) ? (int) $value['term_id'] : null;
		}
		if ( is_numeric( $value ) ) {
			return (int) $value;
		}
		return null;
	}

	private static function extract_person_id( $value ): ?int {
		if ( is_object( $value ) ) {
			return isset( $value->ID ) ? (int) $value->ID : null;
		}
		if ( is_array( $value ) ) {
			return isset( $value['ID'] ) ? (int) $value['ID'] : null;
		}
		if ( is_numeric( $value ) ) {
			return (int) $value;
		}
		return null;
	}
}
