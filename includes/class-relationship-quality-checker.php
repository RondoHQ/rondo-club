<?php
/**
 * RelationshipQualityChecker
 *
 * Surfacing data-quality issues in the `relationships` repeater on persons.
 * Initial check: parent/child pairs where the age delta makes that
 * relationship implausible (almost always a sibling miscategorisation).
 *
 * Rules:
 *   - Parent/child pair with a birthdate-based age gap below
 *     {@see MIN_PARENT_GAP_YEARS} → AGE_GAP_TOO_SMALL. Vangt ook het oude
 *     "zelfde leeftijdsgroep"-geval af, want twee personen in dezelfde
 *     `Onder N` bucket vallen automatisch onder de gap-drempel.
 *   - Sibling pair where the birthdate gap exceeds {@see MAX_SIBLING_GAP_YEARS}
 *     → SIBLING_GAP_TOO_LARGE (suspect uncle/aunt or generation confusion).
 *
 * Returns pair-shaped records — each suspect relationship is reported once,
 * keyed by `min(id_a, id_b) + '-' + max(id_a, id_b)`, so the same A↔B link
 * isn't reported twice even though InverseRelationships mirrors it on both
 * sides.
 *
 * @package Rondo\Volunteer
 */

namespace Rondo\Volunteer;

use Rondo\Data\InverseRelationships;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RelationshipQualityChecker {

	/**
	 * Transient key for the suspect-pairs result. Wiped via `invalidate_cache()`
	 * whenever a person is saved (zelfde hook als de eligibility cache).
	 */
	const CACHE_KEY         = 'rondo_relationship_quality_v1';
	const CACHE_TTL_SECONDS = 5 * MINUTE_IN_SECONDS;

	/**
	 * Minimum plausible age gap for a parent/child relation. 14 jaar is bewust
	 * laag — we willen liever te veel flaggen dan een echte fout missen.
	 */
	const MIN_PARENT_GAP_YEARS = 14;

	/**
	 * Maximum plausible age gap for siblings. Mensen kunnen halfbroers/zussen
	 * met groot leeftijdsverschil zijn; we flaggen pas vanaf 30 jaar verschil.
	 */
	const MAX_SIBLING_GAP_YEARS = 30;

	const REASON_AGE_GAP_TOO_SMALL = 'age_gap_too_small';
	const REASON_SIBLING_GAP       = 'sibling_gap_too_large';

	/**
	 * @return array<int, array{
	 *   pair_key: string,
	 *   person_a_id: int,
	 *   person_a_name: string,
	 *   person_b_id: int,
	 *   person_b_name: string,
	 *   claimed_relation: string,
	 *   reason: string,
	 *   detail: string,
	 *   age_gap_years: ?float,
	 *   leeftijdsgroep_a: string,
	 *   leeftijdsgroep_b: string,
	 * }>
	 */
	public function find_suspect_pairs(): array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$result = $this->compute_suspect_pairs();
		set_transient( self::CACHE_KEY, $result, self::CACHE_TTL_SECONDS );
		return $result;
	}

	public static function invalidate_cache(): void {
		delete_transient( self::CACHE_KEY );
	}

	private function compute_suspect_pairs(): array {
		$persons = get_posts(
			[
				'post_type'        => 'person',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'post_status'      => [ 'publish' ],
			]
		);

		$suspects = [];

		foreach ( $persons as $person_id ) {
			$person_id = (int) $person_id;
			$rels      = \Rondo\Fields\Fields::get_for_post( $person_id, 'relationships' );
			if ( ! is_array( $rels ) ) {
				continue;
			}

			foreach ( $rels as $rel ) {
				$type_id = $this->extract_term_id( $rel['relationship_type'] ?? null );
				$other   = $this->extract_person_id( $rel['related_person'] ?? null );
				if ( $type_id === null || $other === null || $other === $person_id ) {
					continue;
				}

				$pair_key = $this->pair_key( $person_id, $other );
				if ( isset( $suspects[ $pair_key ] ) ) {
					continue; // al gevonden vanaf de andere kant
				}

				$check = $this->classify_pair( $person_id, $other, $type_id );
				if ( $check === null ) {
					continue;
				}

				$suspects[ $pair_key ] = $check;
			}
		}

		// Stable display order: most-severe first, then alfabetisch.
		uasort(
			$suspects,
			static function ( $a, $b ) {
				$severity = [
					self::REASON_AGE_GAP_TOO_SMALL => 0,
					self::REASON_SIBLING_GAP       => 1,
				];
				$sa       = $severity[ $a['reason'] ] ?? 99;
				$sb       = $severity[ $b['reason'] ] ?? 99;
				if ( $sa !== $sb ) {
					return $sa <=> $sb;
				}
				return strcasecmp( $a['person_a_name'], $b['person_a_name'] );
			}
		);

		return array_values( $suspects );
	}

	/**
	 * Decide whether the pair (a, b, claimed_relation_from_a_to_b) is suspect.
	 * Returns null when nothing to flag.
	 */
	private function classify_pair( int $person_a_id, int $person_b_id, int $type_id ): ?array {
		// We only care about parent/child/sibling links here.
		if (
			! in_array(
				$type_id,
				[ InverseRelationships::TYPE_PARENT, InverseRelationships::TYPE_CHILD, InverseRelationships::TYPE_SIBLING ],
				true
			)
		) {
			return null;
		}

		$post_a = get_post( $person_a_id );
		$post_b = get_post( $person_b_id );
		if ( ! $post_a || ! $post_b ) {
			return null;
		}

		$age_a = $this->resolve_age_years( $person_a_id );
		$age_b = $this->resolve_age_years( $person_b_id );
		$gap   = ( $age_a !== null && $age_b !== null ) ? abs( $age_a - $age_b ) : null;

		$lg_a = (string) get_post_meta( $person_a_id, 'leeftijdsgroep', true );
		$lg_b = (string) get_post_meta( $person_b_id, 'leeftijdsgroep', true );

		$claimed = $this->claimed_relation_label( $type_id );
		$base    = [
			'pair_key'         => $this->pair_key( $person_a_id, $person_b_id ),
			'person_a_id'      => $person_a_id,
			'person_a_name'    => $post_a->post_title,
			'person_b_id'      => $person_b_id,
			'person_b_name'    => $post_b->post_title,
			'claimed_relation' => $claimed,
			'age_gap_years'    => $gap,
			'leeftijdsgroep_a' => $lg_a,
			'leeftijdsgroep_b' => $lg_b,
		];

		// Rule 1: ouder/kind met te klein leeftijdsverschil. Vangt ook het oude
		// "zelfde leeftijdsgroep"-geval af (twee personen in dezelfde Onder N
		// vallen sowieso onder de gap-drempel via age_group_number fallback).
		// "Zelfde leeftijdsgroep" alleen flaggen we niet meer — als twee mensen
		// in dezelfde Sportlink-groep zitten met >17 jaar verschil (bv. een
		// 25-jarige speler en zijn 45-jarige vader, beide Senioren) klopt dat.
		if (
			in_array( $type_id, [ InverseRelationships::TYPE_PARENT, InverseRelationships::TYPE_CHILD ], true )
			&& $gap !== null
			&& $gap < self::MIN_PARENT_GAP_YEARS
		) {
			return array_merge(
				$base,
				[
					'reason' => self::REASON_AGE_GAP_TOO_SMALL,
					'detail' => sprintf(
						'Slechts %s jaar verschil tussen ouder en kind — minder dan onze drempel van %d jaar.',
						number_format( $gap, 1, ',', '' ),
						self::MIN_PARENT_GAP_YEARS
					),
				]
			);
		}

		// Rule 3: sibling met te grote gap kan een generatie-fout zijn.
		if ( $type_id === InverseRelationships::TYPE_SIBLING && $gap !== null && $gap > self::MAX_SIBLING_GAP_YEARS ) {
			return array_merge(
				$base,
				[
					'reason' => self::REASON_SIBLING_GAP,
					'detail' => sprintf(
						'%s jaar verschil — siblings hebben zelden méér dan %d jaar verschil; mogelijk oom/tante of generatie-fout.',
						number_format( $gap, 1, ',', '' ),
						self::MAX_SIBLING_GAP_YEARS
					),
				]
			);
		}

		return null;
	}

	/**
	 * Best-effort age in years. Prefers `birthdate` canonical field; falls back to
	 * parsing the integer in "Onder N" leeftijdsgroep (using N as approx age).
	 */
	private function resolve_age_years( int $person_id ): ?float {
		// Direct post_meta — both fields are simple strings, no native field formatting needed.
		$birthdate = get_post_meta( $person_id, 'birthdate', true );
		if ( is_string( $birthdate ) && $birthdate !== '' ) {
			$ts = strtotime( $birthdate );
			if ( $ts !== false ) {
				return ( time() - $ts ) / ( 365.25 * DAY_IN_SECONDS );
			}
		}

		$lg = (string) get_post_meta( $person_id, 'leeftijdsgroep', true );
		if ( preg_match( '/^Onder\s+(\d+)/i', $lg, $m ) ) {
			// "Onder N" = under N years old, so an upper-bound approximation.
			// We use N - 0.5 as a midpoint guess.
			return max( 0, (int) $m[1] - 0.5 );
		}
		if ( $lg !== '' && stripos( $lg, 'senior' ) !== false ) {
			return 25.0; // ondergrens senior bracket
		}
		if ( $lg !== '' && stripos( $lg, 'veter' ) !== false ) {
			return 40.0;
		}
		return null;
	}

	private function claimed_relation_label( int $type_id ): string {
		switch ( $type_id ) {
			case InverseRelationships::TYPE_PARENT:
				return 'ouder';
			case InverseRelationships::TYPE_CHILD:
				return 'kind';
			case InverseRelationships::TYPE_SIBLING:
				return 'broer/zus';
			default:
				return 'onbekend';
		}
	}

	private function pair_key( int $a, int $b ): string {
		return $a < $b ? "$a-$b" : "$b-$a";
	}

	private function extract_term_id( $term ): ?int {
		if ( is_object( $term ) ) {
			return isset( $term->term_id ) ? (int) $term->term_id : null;
		}
		if ( is_array( $term ) ) {
			return isset( $term['term_id'] ) ? (int) $term['term_id'] : null;
		}
		if ( is_numeric( $term ) ) {
			return (int) $term;
		}
		return null;
	}

	private function extract_person_id( $person ): ?int {
		if ( is_object( $person ) ) {
			return isset( $person->ID ) ? (int) $person->ID : null;
		}
		if ( is_array( $person ) ) {
			return isset( $person['ID'] ) ? (int) $person['ID'] : null;
		}
		if ( is_numeric( $person ) ) {
			return (int) $person;
		}
		return null;
	}
}
