<?php
/**
 * VolunteerEligibilityService
 *
 * Derives the "eligible units" — the entities that owe the 2-diensten-per-jaar
 * volunteer obligation — from existing Sportlink data + relationships.
 *
 * The eligibility set is a PURE DERIVED VIEW. No boolean is stored per person;
 * we recompute on demand from:
 *   - `leeftijdsgroep` (Sportlink AgeClassDescription, e.g. "Onder 11", "Senioren")
 *   - `relationships` (parent-of relations on the person CPT)
 *   - `addresses` (first address as gezin fallback when relations are missing)
 *
 * Two unit kinds:
 *   - GEZIN  : one obligation per huishouden with one or more JO16- players
 *              (in te vullen door één of meerdere ouders samen)
 *   - SPELER : one obligation per O17+ player
 *
 * The two are CUMULATIVE, not alternatives. An O17+ player who is also the parent
 * of a JO16- player owes both duties. Shifts fill the speler duty first and spill
 * into the gezin duty afterwards.
 *
 * Owing an obligation is separate from being ALLOWED to volunteer: any active person
 * may claim shifts (see may_volunteer()); the unit only decides what is required.
 *
 * Multi-child scaling (bestuursbesluit 2026-05-26): per kind tellend met
 * contributie-korting voor opvolgende kinderen. Kid 1 = 2, kid 2 = 1.5 (75%),
 * kid 3 en verder = 1 (50%). Resultaat wordt naar beneden afgerond (floor) —
 * in voordeel van het lid, consistent met "korting"-intentie.
 *
 *   1 kind  → 2 diensten
 *   2 kids  → floor(2 + 1.5)         = 3
 *   3 kids  → floor(2 + 1.5 + 1)     = 4
 *   4 kids  → floor(2 + 1.5 + 1 + 1) = 5
 *   n (≥3)  → floor(1.5 + n)         = n + 1
 *
 * @package Rondo\Volunteer
 */

namespace Rondo\Volunteer;

use Rondo\Core\VolunteerStatus;
use Rondo\Data\InverseRelationships;
use Rondo\Fees\SeasonKey;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VolunteerEligibilityService {

	const UNIT_KIND_GEZIN  = 'gezin';
	const UNIT_KIND_SPELER = 'speler';

	/**
	 * Transient key prefix. Bumped via `invalidate_cache()` on any person save.
	 * The full result of `get_eligibility_view()` is cached for CACHE_TTL_SECONDS
	 * so a dashboard refresh costs one O(1) transient read instead of an
	 * O(N²) full scan with thousands of native field calls.
	 */
	const CACHE_PREFIX      = 'rondo_eligibility_view_';
	const CACHE_TTL_SECONDS = 5 * MINUTE_IN_SECONDS;

	/**
	 * In-memory cache for the address→adults map, used during a single
	 * eligibility pass. One DB query feeds all orphan lookups instead of
	 * one query per orphan.
	 *
	 * @var array<string, int[]>|null
	 */
	private ?array $address_to_adults_cache = null;

	/**
	 * Highest leeftijdsgroep number that still triggers ouderplicht (t/m JO16).
	 * Players in "Onder N" with N <= 16 are youth → parents owe the obligation.
	 * Bestuursbesluit: opgerekt van JO15 naar JO16.
	 */
	const YOUTH_MAX_AGE = 16;

	/**
	 * Lowest leeftijdsgroep number that triggers spelersplicht (vanaf O17).
	 * Players in "Onder N" with N >= 17 (or Senioren/Veteranen/etc.) → speler owes.
	 */
	const ADULT_MIN_AGE = 17;

	/**
	 * Base obligation count per kind for the parent duty.
	 */
	const BASE_OBLIGATION = 2;

	/**
	 * Multi-child contribution discount for the parent duty.
	 * Kid 1: 100% (2 diensten), Kid 2: 75% (1.5), Kid 3+: 50% (1 each).
	 * Indexed by 1-based child position; positions beyond the array fall back to the last value.
	 */
	private const CHILD_OBLIGATION_FACTORS = [ 1.0, 0.75, 0.5 ];

	/**
	 * Get every eligible unit for a given season.
	 *
	 * Returns one entry per unit. Each entry carries enough information for the
	 * counter service (#6) and the UI to render the obligation.
	 *
	 * @param string|null $season Defaults to current KNVB season.
	 * @return array<int, array{
	 *     unit_id: string,
	 *     kind: string,
	 *     person_ids: int[],
	 *     trigger_person_ids: int[],
	 *     required_count: int,
	 *     address_key: ?string,
	 * }>
	 */
	public function get_eligible_units( ?string $season = null ): array {
		return $this->get_eligibility_view( $season )['units'];
	}

	/**
	 * Full eligibility view — units plus diagnostic counts of people who couldn't
	 * be placed cleanly. The dashboard surfaces these so admins know how many
	 * records need data attention (missing leeftijdsgroep, missing parents).
	 *
	 * @return array{
	 *   units: array<int, array>,
	 *   diagnostics: array{
	 *     skipped_no_leeftijdsgroep: int,
	 *     gezinnen_with_parents: int,
	 *     gezinnen_via_address: int,
	 *     gezinnen_orphan: int,
	 *   },
	 * }
	 */
	public function get_eligibility_view( ?string $season = null ): array {
		$season    = $season ?: SeasonKey::current();
		$cache_key = self::CACHE_PREFIX . md5( $season );

		$cached = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$result = $this->compute_eligibility_view( $season );
		set_transient( $cache_key, $result, self::CACHE_TTL_SECONDS );
		return $result;
	}

	/**
	 * Wipe every cached eligibility view + every cached drill-down. Hooked on
	 * person save (and accessible to other classes that mutate person data).
	 */
	public static function invalidate_cache(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				 WHERE option_name LIKE %s
				    OR option_name LIKE %s",
				'_transient_' . self::CACHE_PREFIX . '%',
				'_transient_timeout_' . self::CACHE_PREFIX . '%'
			)
		);
	}

	/**
	 * Actual eligibility computation — cached behind get_eligibility_view().
	 * Uses get_post_meta() directly for hot scalar fields (10–100× faster than
	 * get_field for simple strings) and pre-builds an address→adults map so
	 * orphan lookups are O(1) instead of N DB queries.
	 */
	private function compute_eligibility_view( string $season ): array {
		$this->address_to_adults_cache = null; // reset per pass

		$query = new \WP_Query(
			[
				'post_type'        => 'person',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'post_status'      => [ 'publish' ],
			]
		);

		$gezin_units               = [];
		$speler_units              = [];
		$skipped_no_leeftijdsgroep = 0;

		foreach ( $query->posts as $person_id ) {
			$person_id = (int) $person_id;
			$age       = $this->age_group_number( $person_id );

			if ( $age === null ) {
				// Alleen spelende leden horen een Sportlink-leeftijdsgroep te
				// hebben. Niet-spelende ouders, sponsorcontacten en andere
				// contacten zonder spelactiviteit zijn dus geen data-issue.
				// Spelende dubbelrollen (bijvoorbeeld ouder + speler of sponsor +
				// speler) blijven juist wel zichtbaar wanneer de groep ontbreekt.
				if (
					self::is_active_member( $person_id )
					&& self::is_playing_member( $person_id )
					&& ! self::is_current_volunteer( $person_id )
					&& ! self::has_active_honorary_role( $person_id )
				) {
					++$skipped_no_leeftijdsgroep;
				}
				continue;
			}

			// Alleen actieve leden tellen mee voor de plicht. Ex-leden vallen
			// af. Contributie-vrijstelling is bewust géén exclusion-grond hier
			// — die loopt via VolunteerExemptionResolver of honorary role.
			if ( ! self::is_active_member( $person_id ) ) {
				continue;
			}

			if ( $age >= self::ADULT_MIN_AGE ) {
				$unit                             = $this->build_speler_unit( $person_id );
				$speler_units[ $unit['unit_id'] ] = $unit;
				continue;
			}

			if ( $age <= self::YOUTH_MAX_AGE ) {
				$gezin_unit = $this->build_gezin_unit( $person_id );
				$key        = $gezin_unit['unit_id'];

				if ( isset( $gezin_units[ $key ] ) ) {
					$gezin_units[ $key ]['trigger_person_ids'] = array_values(
						array_unique(
							array_merge(
								$gezin_units[ $key ]['trigger_person_ids'],
								$gezin_unit['trigger_person_ids']
							)
						)
					);
					$gezin_units[ $key ]['person_ids']         = array_values(
						array_unique(
							array_merge(
								$gezin_units[ $key ]['person_ids'],
								$gezin_unit['person_ids']
							)
						)
					);
				} else {
					$gezin_units[ $key ] = $gezin_unit;
				}
			}
		}

		// Apply multi-child scaling — required_count depends on the FINAL number
		// of JO16- triggers in each gezin, which we only know after merging.
		$counts_by_quality = [
			'ok'               => 0,
			'address_fallback' => 0,
			'orphan'           => 0,
		];
		foreach ( $gezin_units as &$unit ) {
			$child_count            = count( $unit['trigger_person_ids'] );
			$unit['required_count'] = self::calculate_gezin_required_count( $child_count );
			$unit['child_count']    = $child_count;
			$quality                = $unit['data_quality'] ?? 'ok';
			if ( isset( $counts_by_quality[ $quality ] ) ) {
				++$counts_by_quality[ $quality ];
			}
		}
		unset( $unit );

		return [
			'units'       => array_values( array_merge( $gezin_units, $speler_units ) ),
			'diagnostics' => [
				'skipped_no_leeftijdsgroep' => $skipped_no_leeftijdsgroep,
				'gezinnen_with_parents'     => $counts_by_quality['ok'],
				'gezinnen_via_address'      => $counts_by_quality['address_fallback'],
				'gezinnen_orphan'           => $counts_by_quality['orphan'],
				'speler_units'              => count( $speler_units ),
			],
		];
	}

	/**
	 * Is this person an actief lid for purposes of the vrijwilligers-doelgroep?
	 *
	 * Only `former_member` excludes — contributie-vrijstelling (`_exclude_from_contributie`)
	 * heeft niets met vrijwilligerstaken te maken: een donateur, erelid of
	 * contributievrij lid kan nog steeds vrijwilligers-plicht hebben. Eventuele
	 * vrijstelling daarvoor loopt via VolunteerExemptionResolver (commissie,
	 * staf-rol, betaalde vrijwilliger, handmatige vrijstelling).
	 */
	public static function is_active_member( int $person_id ): bool {
		// Direct post meta avoids constructing the full logical field payload.
		// definition tree, which is much slower in hot loops with thousands
		// of persons.
		$former = get_post_meta( $person_id, 'former_member', true );
		return ! ( $former === '1' || $former === 1 || $former === true );
	}

	/**
	 * Does Sportlink mark this person as a playing member?
	 *
	 * A leeftijdsgroep is only expected when `spelactiviteit` contains an
	 * actual activity. Parents, sponsor-only records and other contacts normally
	 * have no activity (or `-`) and must not be reported as data-quality gaps.
	 */
	public static function is_playing_member( int $person_id ): bool {
		$activity = trim( (string) get_post_meta( $person_id, 'spelactiviteit', true ) );
		return $activity !== '' && $activity !== '-';
	}

	/**
	 * Is this person flagged as a current volunteer?
	 *
	 * Current volunteers are exempt from the obligation entirely — they're
	 * already bijdragend via hun functie. We use this to keep them out of the
	 * "missing leeftijdsgroep" data-quality bucket: a vrijwilliger zonder
	 * spelactiviteit *hoort* geen leeftijdsgroep te hebben, dus zou daar
	 * onterecht als data-issue verschijnen.
	 */
	public static function is_current_volunteer( int $person_id ): bool {
		$flag = get_post_meta( $person_id, 'huidig-vrijwilliger', true );
		return $flag === '1' || $flag === 1 || $flag === true;
	}

	/**
	 * Does this person hold an honorary / membership role (Donateur, Erelid,
	 * Lid van Verdienste, Verenigingslid voor het leven) — i.e. a job_title
	 * in `VolunteerStatus::get_excluded_roles()` — on a current work_history
	 * entry? Honorary leden hebben geen spelactiviteit en horen niet als
	 * data-gap te verschijnen.
	 *
	 * Reads work_history through the native field API — acceptably priced
	 * because callers only invoke this for the small set of personen zonder
	 * leeftijdsgroep, niet voor de volledige ledenpopulatie.
	 */
	public static function has_active_honorary_role( int $person_id ): bool {
		$work_history = \Rondo\Fields\Fields::get_for_post( $person_id, 'work_history' );
		if ( empty( $work_history ) || ! is_array( $work_history ) ) {
			return false;
		}

		$excluded = array_map( 'strtolower', VolunteerStatus::get_excluded_roles() );
		if ( empty( $excluded ) ) {
			return false;
		}

		$today = strtotime( 'today' );
		foreach ( $work_history as $job ) {
			if ( ! is_array( $job ) ) {
				continue;
			}
			$job_title = strtolower( trim( (string) ( $job['job_title'] ?? '' ) ) );
			if ( $job_title === '' || ! in_array( $job_title, $excluded, true ) ) {
				continue;
			}

			// Only count CURRENT positions: is_current flag, no end_date, or end_date in future.
			if ( ! empty( $job['is_current'] ) ) {
				return true;
			}
			$end_date = (string) ( $job['end_date'] ?? '' );
			if ( $end_date === '' ) {
				return true;
			}
			$ts = strtotime( $end_date );
			if ( $ts !== false && $ts >= $today ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Compute the parent obligation for a gezin given the number of JO16- children.
	 *
	 * Multi-child scaling (bestuursbesluit 2026-05-26): per kind tellend met
	 * contributie-korting. Kid 1 = 100%, kid 2 = 75%, kid 3+ = 50% each.
	 * Resultaat naar beneden afgerond (floor).
	 *
	 *   1 kind  → 2
	 *   2 kids  → floor(2 + 1.5)         = 3
	 *   3 kids  → floor(2 + 1.5 + 1)     = 4
	 *   4 kids  → floor(2 + 1.5 + 1 + 1) = 5
	 *
	 * @param int $child_count Number of JO16- children triggering the gezin's plicht.
	 * @return int Floor-rounded total required diensten for the gezin this season.
	 */
	public static function calculate_gezin_required_count( int $child_count ): int {
		if ( $child_count <= 0 ) {
			return 0;
		}

		$factors = self::CHILD_OBLIGATION_FACTORS;
		$tail    = end( $factors ); // factor used for children beyond the configured tier list

		$total = 0.0;
		for ( $i = 1; $i <= $child_count; $i++ ) {
			$factor = $factors[ $i - 1 ] ?? $tail;
			$total += self::BASE_OBLIGATION * $factor;
		}

		return (int) floor( $total );
	}

	/**
	 * Whether a person is allowed to claim shifts at all.
	 *
	 * Deliberately broader than "owes an obligation". A sponsor, a grandparent, or a
	 * parent whose children have all aged out owes nothing yet may still want to help,
	 * and the club has no reason to refuse them. Exempt members were already allowed
	 * through on the same reasoning.
	 *
	 * @param int $person_id Person post ID.
	 * @return bool Whether this person may sign up for diensten.
	 */
	public function may_volunteer( int $person_id ): bool {
		return self::is_active_member( $person_id );
	}

	/**
	 * Resolve every eligible unit a person is responsible for.
	 *
	 * A person can carry two duties at once: an O17+ player who is also the parent
	 * of a JO16- player owes their own spelersplicht *and* the family's ouderplicht.
	 * Both are returned; the speler unit always comes first, which is the order in
	 * which completed shifts are attributed (see VolunteerObligationCalculator).
	 *
	 * Returns an empty array when the person is not in the doelgroep — a JO16- player
	 * (their parents owe), or an adult with no youth children who does not play.
	 *
	 * @param int         $person_id Person post ID.
	 * @param string|null $season    Defaults to current KNVB season.
	 * @return array[] Zero, one or two unit arrays, shape matching get_eligible_units().
	 */
	public function get_eligible_units_for_person( int $person_id, ?string $season = null ): array {
		$units = [];
		$age   = $this->age_group_number( $person_id );

		if ( $age !== null && $age <= self::YOUTH_MAX_AGE ) {
			// The person themselves owes nothing — their parent(s) do.
			return [];
		}

		if ( $age !== null && $age >= self::ADULT_MIN_AGE ) {
			$units[] = $this->build_speler_unit( $person_id );
		}

		// Any adult — playing or not — may also owe via a JO16- child.
		$gezin = $this->merged_gezin_unit_for_parent( $person_id );
		if ( $gezin !== null ) {
			$units[] = $gezin;
		}

		return $units;
	}

	/**
	 * Resolve the merged gezin unit triggered by a youth person.
	 *
	 * Used only while an account is temporarily operated by a parent but still
	 * linked to the child. The regular per-person accessor deliberately returns
	 * no duty for a youth person.
	 */
	public function get_gezin_unit_for_youth( int $person_id, ?string $season = null ): ?array {
		$age = $this->age_group_number( $person_id );
		if ( $age === null || $age > self::YOUTH_MAX_AGE ) {
			return null;
		}

		foreach ( $this->get_eligibility_view( $season )['units'] as $unit ) {
			if (
				( $unit['kind'] ?? '' ) === self::UNIT_KIND_GEZIN
				&& in_array( $person_id, array_map( 'intval', (array) ( $unit['trigger_person_ids'] ?? [] ) ), true )
			) {
				return $unit;
			}
		}

		return null;
	}

	/**
	 * Resolve the eligible unit for a single person, if any.
	 *
	 * Returns null when the person is not in the doelgroep (e.g. age 16, no kids,
	 * not a Senior/Veteraan player).
	 *
	 * @deprecated Use get_eligible_units_for_person(), which also returns the gezin
	 *             unit of a playing parent. Kept for VolunteerFineGenerator, which
	 *             only needs a household roster.
	 *
	 * @param int         $person_id Person post ID.
	 * @param string|null $season    Defaults to current KNVB season.
	 * @return array|null Unit array shape matches get_eligible_units(), or null.
	 */
	public function get_eligible_unit_for_person( int $person_id, ?string $season = null ): ?array {
		$age = $this->age_group_number( $person_id );

		if ( $age !== null ) {
			if ( $age >= self::ADULT_MIN_AGE ) {
				return $this->build_speler_unit( $person_id );
			}
			if ( $age <= self::YOUTH_MAX_AGE ) {
				// The person themselves owes nothing — their parent(s) do.
				return null;
			}
		}

		return $this->merged_gezin_unit_for_parent( $person_id );
	}

	/**
	 * Build the gezin unit an adult owes through their JO16- children, or null.
	 *
	 * Merges the per-child units so the trigger list covers ALL youth children before
	 * multi-child scaling is applied. Otherwise an adult with 3 youth children would
	 * see "2 diensten" (single trigger) instead of "4 diensten" (3 triggers, floor).
	 *
	 * @param int $person_id Adult person post ID.
	 * @return array|null Merged gezin unit, or null when they have no youth children.
	 */
	private function merged_gezin_unit_for_parent( int $person_id ): ?array {
		$children = $this->find_youth_children( $person_id );
		if ( empty( $children ) ) {
			return null;
		}

		$merged = null;
		foreach ( $children as $child_id ) {
			$child_unit = $this->build_gezin_unit( (int) $child_id );
			if ( $merged === null ) {
				$merged = $child_unit;
				continue;
			}
			$merged['trigger_person_ids'] = array_values(
				array_unique( array_merge( $merged['trigger_person_ids'], $child_unit['trigger_person_ids'] ) )
			);
			$merged['person_ids']         = array_values(
				array_unique( array_merge( $merged['person_ids'], $child_unit['person_ids'] ) )
			);
		}

		if ( $merged !== null ) {
			$merged['child_count']    = count( $merged['trigger_person_ids'] );
			$merged['required_count'] = self::calculate_gezin_required_count( $merged['child_count'] );
		}

		return $merged;
	}

	/**
	 * Parse the integer behind "Onder N" leeftijdsgroep strings.
	 *
	 * Returns:
	 *   - integer (6, 7, …, 19) for "Onder N" values
	 *   - 99 for adult buckets (Senioren, Veteranen, Champions league, etc.)
	 *   - null when no leeftijdsgroep is set or unparseable
	 */
	public function age_group_number( int $person_id ): ?int {
		// Direct post_meta — leeftijdsgroep is a simple string, no native field formatting needed.
		$raw = get_post_meta( $person_id, 'leeftijdsgroep', true );
		if ( ! is_string( $raw ) || trim( $raw ) === '' ) {
			return null;
		}

		$normalized = trim( $raw );
		// Strip " Meiden" / " Vrouwen" suffix — same convention as FeeCategoryResolver.
		$normalized = preg_replace( '/\s+(Meiden|Vrouwen)$/i', '', $normalized );

		if ( preg_match( '/^Onder\s+(\d+)/i', $normalized, $m ) ) {
			return (int) $m[1];
		}

		// Adult buckets we know about → treat as 99 (definitely O17+).
		$adult_keywords = [ 'Senioren', 'Veteranen', 'Recreanten', 'Champions league', 'Walking' ];
		foreach ( $adult_keywords as $needle ) {
			if ( stripos( $normalized, $needle ) !== false ) {
				return 99;
			}
		}

		return null;
	}

	/**
	 * Build a SPELER unit for an O17+ player.
	 *
	 * The speler is both the trigger AND the only person responsible.
	 */
	private function build_speler_unit( int $person_id ): array {
		return [
			'unit_id'            => 'speler-' . $person_id,
			'kind'               => self::UNIT_KIND_SPELER,
			'person_ids'         => [ $person_id ],
			'trigger_person_ids' => [ $person_id ],
			'required_count'     => self::BASE_OBLIGATION,
			'address_key'        => null,
		];
	}

	/**
	 * Build a GEZIN unit for a JO16- player. Always returns a unit — orphans
	 * (no parents in relationships, no adult housemates) get a per-child unit
	 * tagged `data_quality=orphan` so the dashboard can surface them as data
	 * issues without silently losing the obligation.
	 *
	 * The unit is keyed by, in order of preference:
	 *   1. sorted parent person IDs from the `relationships` repeater (best)
	 *   2. address key (postal + housenumber) of the youth player (fallback)
	 *   3. the youth player's own ID (orphan — invites admin to fix relations)
	 *
	 * Later children at the same key will be merged on top by the caller, so
	 * kids that share parents/addresses naturally collapse into one gezin.
	 */
	private function build_gezin_unit( int $youth_person_id ): array {
		$parents = $this->find_parents( $youth_person_id );

		if ( ! empty( $parents ) ) {
			sort( $parents );
			return [
				'unit_id'            => 'gezin-rel-' . implode( '-', $parents ),
				'kind'               => self::UNIT_KIND_GEZIN,
				'person_ids'         => array_values( array_unique( array_merge( $parents, [ $youth_person_id ] ) ) ),
				'trigger_person_ids' => [ $youth_person_id ],
				'required_count'     => self::BASE_OBLIGATION,
				'address_key'        => $this->address_key( $youth_person_id ),
				'data_quality'       => 'ok',
			];
		}

		$address_key = $this->address_key( $youth_person_id );
		if ( $address_key !== null ) {
			$housemates = $this->find_adults_at_address( $address_key, $youth_person_id );
			if ( ! empty( $housemates ) ) {
				return [
					'unit_id'            => 'gezin-adr-' . $address_key,
					'kind'               => self::UNIT_KIND_GEZIN,
					'person_ids'         => array_values( array_unique( array_merge( $housemates, [ $youth_person_id ] ) ) ),
					'trigger_person_ids' => [ $youth_person_id ],
					'required_count'     => self::BASE_OBLIGATION,
					'address_key'        => $address_key,
					'data_quality'       => 'address_fallback',
				];
			}
		}

		// Orphan: keep the kid as the lone person_id so the obligation stays
		// visible. Multiple orphans at the same address (when known) still merge.
		$orphan_id = $address_key !== null
			? 'gezin-orphan-adr-' . $address_key
			: 'gezin-orphan-' . $youth_person_id;

		return [
			'unit_id'            => $orphan_id,
			'kind'               => self::UNIT_KIND_GEZIN,
			'person_ids'         => [ $youth_person_id ],
			'trigger_person_ids' => [ $youth_person_id ],
			'required_count'     => self::BASE_OBLIGATION,
			'address_key'        => $address_key,
			'data_quality'       => 'orphan',
		];
	}

	/**
	 * Find the parents of a person via the relationships repeater.
	 *
	 * A parent is either:
	 *   - someone whose relationship to this person has term_id = TYPE_PARENT, OR
	 *   - someone who in their own record has a TYPE_CHILD relationship pointing here.
	 *
	 * We trust the InverseRelationships sync to keep both sides in step; reading
	 * one direction (this person's relationships) is sufficient in practice.
	 *
	 * @return int[] Parent person IDs.
	 */
	public function find_parents( int $person_id ): array {
		$rels = \Rondo\Fields\Fields::get_for_post( $person_id, 'relationships' );
		if ( empty( $rels ) || ! is_array( $rels ) ) {
			return [];
		}

		$parents = [];
		foreach ( $rels as $rel ) {
			$type_id = $this->extract_type_id( $rel['relationship_type'] ?? null );
			if ( $type_id !== InverseRelationships::TYPE_PARENT ) {
				continue;
			}
			$related = $this->extract_person_id( $rel['related_person'] ?? null );
			if ( $related ) {
				$parents[] = (int) $related;
			}
		}

		return array_values( array_unique( $parents ) );
	}

	/**
	 * Does this person have ANY child-relationship at all (TYPE_CHILD outgoing
	 * of de inverse TYPE_PARENT op de gerelateerde persoon)? Onafhankelijk van
	 * de leeftijdsgroep van het kind — zodat we ouders ook kunnen herkennen
	 * als hun kind nog geen leeftijdsgroep heeft (sync issue) of als het kind
	 * al O17+ is.
	 *
	 * Gebruikt door de "missing leeftijdsgroep"-bucket om ouders uit de lijst
	 * te filteren: een ouder heeft terecht geen Sportlink-spelactiviteit.
	 */
	public function is_parent( int $person_id ): bool {
		$rels = \Rondo\Fields\Fields::get_for_post( $person_id, 'relationships' );
		if ( empty( $rels ) || ! is_array( $rels ) ) {
			return false;
		}
		foreach ( $rels as $rel ) {
			$type_id = $this->extract_type_id( $rel['relationship_type'] ?? null );
			if ( $type_id === InverseRelationships::TYPE_CHILD ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Find the youth children (JO16-) of an adult.
	 *
	 * @return int[] Child person IDs.
	 */
	public function find_youth_children( int $person_id ): array {
		$rels = \Rondo\Fields\Fields::get_for_post( $person_id, 'relationships' );
		if ( empty( $rels ) || ! is_array( $rels ) ) {
			return [];
		}

		$children = [];
		foreach ( $rels as $rel ) {
			$type_id = $this->extract_type_id( $rel['relationship_type'] ?? null );
			if ( $type_id !== InverseRelationships::TYPE_CHILD ) {
				continue;
			}
			$related = $this->extract_person_id( $rel['related_person'] ?? null );
			if ( ! $related ) {
				continue;
			}
			$age = $this->age_group_number( (int) $related );
			if ( $age !== null && $age <= self::YOUTH_MAX_AGE ) {
				$children[] = (int) $related;
			}
		}

		return array_values( array_unique( $children ) );
	}

	/**
	 * Compute the address key for a person — same convention as FamilyGroupingService.
	 *
	 * Format: "POSTALCODE-HOUSENUMBER" (e.g. "1234AB-12A"). Null when address incomplete.
	 */
	public function address_key( int $person_id ): ?string {
		$addresses = \Rondo\Fields\Fields::get_for_post( $person_id, 'addresses' );
		if ( empty( $addresses ) || ! is_array( $addresses ) ) {
			return null;
		}

		$primary      = $addresses[0];
		$postal_raw   = (string) ( $primary['postal_code'] ?? '' );
		$house_number = (string) ( $primary['house_number'] ?? '' );
		$house_suffix = (string) ( $primary['house_number_addition'] ?? '' );

		if ( $postal_raw === '' || $house_number === '' ) {
			return null;
		}

		$normalized = strtoupper( preg_replace( '/\s+/', '', trim( $postal_raw ) ) );
		if ( ! preg_match( '/^\d{4}[A-Z]{2}$/', $normalized ) ) {
			return null;
		}

		return $normalized . '-' . trim( $house_number . $house_suffix );
	}

	/**
	 * Find adult housemates (anyone NOT youth) at a given address, excluding $exclude_id.
	 *
	 * Used as the fallback when no relationship data ties parents to children.
	 * Queries by the postal_code meta to keep it index-friendly, then filters
	 * the small result set in PHP.
	 *
	 * @return int[]
	 */
	private function find_adults_at_address( string $address_key, int $exclude_id ): array {
		$map    = $this->address_adults_map();
		$adults = $map[ $address_key ] ?? [];
		if ( empty( $adults ) ) {
			return [];
		}
		// Strip the youth player themselves and dedupe.
		return array_values( array_filter( $adults, fn( $pid ) => (int) $pid !== $exclude_id ) );
	}

	/**
	 * Build (once per eligibility pass) a map of address_key → adult person IDs.
	 *
	 * Replaces the previous "one DB query per orphaned youth player" pattern.
	 * Reads ALL `addresses_N_postal_code` + `addresses_N_house_number` +
	 * `addresses_N_house_number_addition` meta rows in two queries, joins them
	 * up in PHP, and bucketed adults (no leeftijdsgroep or >= ADULT_MIN_AGE)
	 * per address key.
	 *
	 * @return array<string, int[]>
	 */
	private function address_adults_map(): array {
		if ( $this->address_to_adults_cache !== null ) {
			return $this->address_to_adults_cache;
		}

		global $wpdb;

		// Pull every address-component meta row for any published person.
		// Returned columns: post_id, meta_key (addresses_N_*), meta_value.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT pm.post_id, pm.meta_key, pm.meta_value
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE p.post_type = 'person'
			   AND p.post_status = 'publish'
			   AND ( pm.meta_key LIKE 'addresses\\_%\\_postal\\_code'
			      OR pm.meta_key LIKE 'addresses\\_%\\_house\\_number'
			      OR pm.meta_key LIKE 'addresses\\_%\\_house\\_number\\_addition' )"
		);

		// Group per (person_id, address_index) → component → value
		$by_person = [];
		foreach ( (array) $rows as $row ) {
			if ( ! preg_match( '/^addresses_(\d+)_(postal_code|house_number|house_number_addition)$/', $row->meta_key, $m ) ) {
				continue;
			}
			$pid                                     = (int) $row->post_id;
			$idx                                     = (int) $m[1];
			$component                               = $m[2];
			$by_person[ $pid ][ $idx ][ $component ] = (string) $row->meta_value;
		}

		// Pull leeftijdsgroep for every person in one shot so we can classify
		// without per-person get_post_meta calls.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$lg_rows      = $wpdb->get_results(
			"SELECT pm.post_id, pm.meta_value
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE p.post_type = 'person'
			   AND p.post_status = 'publish'
			   AND pm.meta_key = 'leeftijdsgroep'"
		);
		$lg_by_person = [];
		foreach ( (array) $lg_rows as $row ) {
			$lg_by_person[ (int) $row->post_id ] = (string) $row->meta_value;
		}

		$map = [];
		foreach ( $by_person as $pid => $addresses ) {
			// Use address index 0 (primary) only — matches address_key() behavior.
			if ( ! isset( $addresses[0] ) ) {
				continue;
			}
			$primary  = $addresses[0];
			$postal   = (string) ( $primary['postal_code'] ?? '' );
			$house    = (string) ( $primary['house_number'] ?? '' );
			$addition = (string) ( $primary['house_number_addition'] ?? '' );

			if ( $postal === '' || $house === '' ) {
				continue;
			}

			$normalized = strtoupper( preg_replace( '/\s+/', '', trim( $postal ) ) );
			if ( ! preg_match( '/^\d{4}[A-Z]{2}$/', $normalized ) ) {
				continue;
			}
			$key = $normalized . '-' . trim( $house . $addition );

			// Only adults (no leeftijdsgroep OR Onder N where N >= ADULT_MIN_AGE OR adult bucket).
			$lg = $lg_by_person[ $pid ] ?? '';
			if ( ! $this->is_adult_age_string( $lg ) ) {
				continue;
			}

			$map[ $key ][] = $pid;
		}

		$this->address_to_adults_cache = $map;
		return $map;
	}

	/**
	 * Quick adult-check from a raw leeftijdsgroep string (no get_field, no native field).
	 * Mirrors age_group_number() classification.
	 */
	private function is_adult_age_string( string $lg ): bool {
		if ( trim( $lg ) === '' ) {
			return true; // unknown → likely a non-playing parent, treat as adult for housemate purposes
		}
		$normalized = preg_replace( '/\s+(Meiden|Vrouwen)$/i', '', trim( $lg ) );
		if ( preg_match( '/^Onder\s+(\d+)/i', $normalized, $m ) ) {
			return (int) $m[1] >= self::ADULT_MIN_AGE;
		}
		foreach ( [ 'Senioren', 'Veteranen', 'Recreanten', 'Champions league', 'Walking' ] as $needle ) {
			if ( stripos( $normalized, $needle ) !== false ) {
				return true;
			}
		}
		return true; // unrecognized → assume adult, don't lose data
	}

	private function extract_type_id( $term ): ?int {
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

	/**
	 * Drill-down: every JO16- player in an orphan gezin (no parents in
	 * relationships, no adult housemates at the shared address). Returns
	 * just the youth trigger IDs — these are the records that need a parent
	 * link added.
	 *
	 * @return int[] Person post IDs.
	 */
	public function get_orphan_youth_ids( ?string $season = null ): array {
		return $this->collect_unit_person_ids( $season, 'orphan', 'triggers' );
	}

	/**
	 * Drill-down: every person in an address-fallback gezin — both the youth
	 * triggers and the adult housemates we inferred from shared addresses.
	 * These records would benefit from explicit relationship_type=parent
	 * entries so we don't depend on address heuristics.
	 *
	 * @return int[] Person post IDs.
	 */
	public function get_address_fallback_person_ids( ?string $season = null ): array {
		return $this->collect_unit_person_ids( $season, 'address_fallback', 'all' );
	}

	/**
	 * Drill-down: every active playing member whose record lacks a
	 * `leeftijdsgroep`. Non-playing parents, sponsors and contacts are excluded
	 * because Sportlink correctly gives them no playing age class.
	 *
	 * @return int[] Person post IDs.
	 */
	public function get_skipped_no_leeftijdsgroep_ids(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids_with_value = $wpdb->get_col(
			"SELECT DISTINCT p.ID
			 FROM {$wpdb->posts} p
			 INNER JOIN {$wpdb->postmeta} pm
				ON pm.post_id = p.ID AND pm.meta_key = 'leeftijdsgroep'
			 WHERE p.post_type = 'person'
			   AND p.post_status = 'publish'
			   AND pm.meta_value <> ''"
		);
		$with           = array_map( 'intval', (array) $ids_with_value );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$all_person_ids = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts}
			 WHERE post_type = 'person' AND post_status = 'publish'"
		);
		$all            = array_map( 'intval', (array) $all_person_ids );

		// Alleen actieve, spelende leden horen een leeftijdsgroep te hebben.
		// Huidige vrijwilligers en honorary leden blijven buiten deze controle:
		// hun ontbrekende groep heeft geen invloed op de vrijwilligersplicht.
		return array_values(
			array_filter(
				array_diff( $all, $with ),
				fn( $pid ) => self::is_active_member( (int) $pid )
					&& self::is_playing_member( (int) $pid )
					&& ! self::is_current_volunteer( (int) $pid )
					&& ! self::has_active_honorary_role( (int) $pid )
			)
		);
	}

	/**
	 * Internal helper for the orphan/address-fallback drill-downs.
	 *
	 * @param string|null $season         KNVB-seizoen.
	 * @param string      $data_quality   'orphan' | 'address_fallback' | 'ok'
	 * @param string      $scope          'triggers' | 'all' — which IDs to return per unit.
	 * @return int[]
	 */
	private function collect_unit_person_ids( ?string $season, string $data_quality, string $scope ): array {
		$view = $this->get_eligibility_view( $season );
		$ids  = [];
		foreach ( $view['units'] as $unit ) {
			if ( ( $unit['kind'] ?? '' ) !== self::UNIT_KIND_GEZIN ) {
				continue;
			}
			if ( ( $unit['data_quality'] ?? '' ) !== $data_quality ) {
				continue;
			}
			$pool = $scope === 'triggers'
				? (array) ( $unit['trigger_person_ids'] ?? [] )
				: (array) ( $unit['person_ids'] ?? [] );
			foreach ( $pool as $pid ) {
				$ids[] = (int) $pid;
			}
		}
		return array_values( array_unique( $ids ) );
	}
}
