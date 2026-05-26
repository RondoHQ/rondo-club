<?php
/**
 * VolunteerSeeder
 *
 * One-shot bootstrapping of the volunteer-policy data layer. Run on theme
 * activation (and idempotently on every `init` so existing installs catch up
 * automatically) to seed:
 *
 *   - 6 initial `dienst_type` records (Terreinmeester, Kantine bar/keuken/verkoop,
 *     Schoonmaak, Terreinonderhoud).
 *   - 3 pool `commissie` records (Schoonmaak, Activiteiten, Werkploeg terreinonderhoud).
 *   - The `rondo_volunteer_pool_commissies` option mapping pool slugs to commissie IDs.
 *
 * Seeding is **idempotent**: each record carries a unique meta key (`_rondo_seed_key`)
 * so we can re-run safely without creating duplicates. Admins are free to rename or
 * extend records — the seeder will not overwrite existing fields.
 *
 * @package Rondo\Volunteer
 */

namespace Rondo\Volunteer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VolunteerSeeder {

	const SEED_META_KEY     = '_rondo_seed_key';
	const POOL_OPTION       = 'rondo_volunteer_pool_commissies';
	const SEED_DONE_OPTION  = 'rondo_volunteer_seed_version';
	const CURRENT_SEED_VERSION = 1;

	/**
	 * Initial dienst_type seed data.
	 * Each key is the slug stored in _rondo_seed_key; values become post fields.
	 */
	private const DIENST_TYPES = [
		'terreinmeester' => [
			'title'             => 'Terreinmeester',
			'description'       => 'Sleutel + ranja uitreiken aan trainers/leiders, terrein gereed maken voor trainingen en wedstrijden.',
			'vog_required'      => true,
			'iva_required'      => false,
			'default_capacity'  => 1,
			'sleutel_involved'  => true,
			'color'             => '#4f46e5',
		],
		'kantine_bar'    => [
			'title'             => 'Kantine — bar',
			'description'       => 'Bardienst: schenken, kassa, opruimen. Vereist een geldig IVA-certificaat (alcohol).',
			'vog_required'      => true,
			'iva_required'      => true,
			'default_capacity'  => 1,
			'sleutel_involved'  => false,
			'color'             => '#dc2626',
		],
		'kantine_keuken_prep' => [
			'title'             => 'Kantine — keuken voorbereiding',
			'description'       => 'Vooraf snijden, kop koffie zetten, broodjes smeren, voorraad bijvullen.',
			'vog_required'      => true,
			'iva_required'      => false,
			'default_capacity'  => 1,
			'sleutel_involved'  => false,
			'color'             => '#f59e0b',
		],
		'kantine_keuken_verkoop' => [
			'title'             => 'Kantine — keuken verkoop',
			'description'       => 'Snacks en broodjes verkopen tijdens wedstrijddagen.',
			'vog_required'      => true,
			'iva_required'      => false,
			'default_capacity'  => 1,
			'sleutel_involved'  => false,
			'color'             => '#eab308',
		],
		'schoonmaak'     => [
			'title'             => 'Schoonmaak',
			'description'       => 'Wekelijkse schoonmaak van clubgebouw, kleedkamers en kantine.',
			'vog_required'      => true,
			'iva_required'      => false,
			'default_capacity'  => 4,
			'sleutel_involved'  => false,
			'color'             => '#10b981',
		],
		'terreinonderhoud' => [
			'title'             => 'Terreinonderhoud',
			'description'       => 'Onderhoud van velden, hekwerk, groen en infrastructuur. Door de werkploeg + ad-hoc helpers.',
			'vog_required'      => true,
			'iva_required'      => false,
			'default_capacity'  => 0,  // 0 = unlimited
			'sleutel_involved'  => false,
			'color'             => '#84cc16',
		],
	];

	/**
	 * Pool commissies — Rondo-managed (not synced from Sportlink).
	 * `slug` is also used as the entry in the pool-commissie option.
	 */
	private const POOL_COMMISSIES = [
		'schoonmaak'      => [
			'title'   => 'Schoonmaakpoule',
			'content' => 'Vaste poule van ~20 vrijwilligers die rouleren in de wekelijkse schoonmaakdienst. Beheerd in Rondo, niet gesynchroniseerd vanuit Sportlink.',
		],
		'activiteiten'    => [
			'title'   => 'Activiteitenpoule',
			'content' => 'Vaste poule van ~75 vrijwilligers die activiteiten en evenementen regelen. Leden zijn vrijgesteld van de 2-diensten-plicht.',
		],
		'werkploeg'       => [
			'title'   => 'Werkploeg terreinonderhoud',
			'content' => 'Vaste werkploeg voor terreinonderhoud. Aangevuld met (ouders van) leden op inschrijving via de 2-diensten-plicht.',
		],
	];

	public function __construct() {
		add_action( 'after_switch_theme', [ $this, 'seed' ] );
		add_action( 'init', [ $this, 'seed_if_needed' ], 30 );
	}

	/**
	 * Conditionally seed: only runs when the stored seed version is below CURRENT_SEED_VERSION.
	 * Avoids running heavy queries on every page load.
	 */
	public function seed_if_needed() {
		$installed = (int) get_option( self::SEED_DONE_OPTION, 0 );
		if ( $installed >= self::CURRENT_SEED_VERSION ) {
			return;
		}
		$this->seed();
	}

	/**
	 * Run the full seed. Safe to call multiple times.
	 */
	public function seed() {
		// dienst_type CPT must already be registered before we can insert posts.
		if ( ! post_type_exists( 'dienst_type' ) || ! post_type_exists( 'commissie' ) ) {
			return;
		}

		$this->seed_dienst_types();
		$this->seed_pool_commissies();

		update_option( self::SEED_DONE_OPTION, self::CURRENT_SEED_VERSION, false );
	}

	private function seed_dienst_types() {
		foreach ( self::DIENST_TYPES as $slug => $data ) {
			if ( $this->find_seeded_post( 'dienst_type', $slug ) !== null ) {
				continue;
			}

			$post_id = wp_insert_post(
				[
					'post_type'   => 'dienst_type',
					'post_status' => 'publish',
					'post_title'  => $data['title'],
					'post_name'   => 'dienst-type-' . $slug,
				],
				true
			);

			if ( is_wp_error( $post_id ) || $post_id === 0 ) {
				continue;
			}

			update_post_meta( $post_id, self::SEED_META_KEY, $slug );
			update_post_meta( $post_id, 'description', $data['description'] );
			update_post_meta( $post_id, 'vog_required', $data['vog_required'] );
			update_post_meta( $post_id, 'iva_required', $data['iva_required'] );
			update_post_meta( $post_id, 'default_capacity', $data['default_capacity'] );
			update_post_meta( $post_id, 'sleutel_involved', $data['sleutel_involved'] );
			update_post_meta( $post_id, 'color', $data['color'] );
		}
	}

	private function seed_pool_commissies() {
		$existing = get_option( self::POOL_OPTION, [] );
		if ( ! is_array( $existing ) ) {
			$existing = [];
		}

		foreach ( self::POOL_COMMISSIES as $slug => $data ) {
			$seed_key   = 'pool-' . $slug;
			$existing_post = $this->find_seeded_post( 'commissie', $seed_key );

			if ( $existing_post !== null ) {
				$existing[ $slug ] = (int) $existing_post;
				continue;
			}

			$post_id = wp_insert_post(
				[
					'post_type'    => 'commissie',
					'post_status'  => 'publish',
					'post_title'   => $data['title'],
					'post_content' => $data['content'],
				],
				true
			);

			if ( is_wp_error( $post_id ) || $post_id === 0 ) {
				continue;
			}

			update_post_meta( $post_id, self::SEED_META_KEY, $seed_key );
			$existing[ $slug ] = (int) $post_id;
		}

		update_option( self::POOL_OPTION, $existing, false );
	}

	/**
	 * Locate a previously seeded post by its seed key.
	 */
	private function find_seeded_post( string $post_type, string $seed_key ): ?int {
		$query = new \WP_Query(
			[
				'post_type'        => $post_type,
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'post_status'      => [ 'publish', 'draft', 'pending', 'private' ],
				'meta_query'       => [
					[
						'key'   => self::SEED_META_KEY,
						'value' => $seed_key,
					],
				],
			]
		);

		if ( empty( $query->posts ) ) {
			return null;
		}

		return (int) $query->posts[0];
	}

	/**
	 * Public accessor for the pool-commissie ID map. Used by the eligibility
	 * service, the sync whitelist and any UI that needs to know which commissies
	 * are Rondo-managed.
	 *
	 * @return array<string, int> Pool slug => commissie post ID.
	 */
	public static function get_pool_commissies(): array {
		$value = get_option( self::POOL_OPTION, [] );
		return is_array( $value ) ? array_map( 'intval', $value ) : [];
	}

	/**
	 * Get the list of Rondo-managed commissie IDs (any commissie with a seed key).
	 * Includes the pool commissies plus anything else admins flagged via the seed
	 * key directly. Consumed by the rondo-sync whitelist.
	 */
	public static function get_managed_commissie_ids(): array {
		$ids = array_values( self::get_pool_commissies() );

		// Also scoop up any commissies that were seeded but somehow dropped from the option.
		$query = new \WP_Query(
			[
				'post_type'        => 'commissie',
				'posts_per_page'   => -1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'post_status'      => [ 'publish', 'draft', 'pending', 'private' ],
				'meta_query'       => [
					[
						'key'     => self::SEED_META_KEY,
						'compare' => 'EXISTS',
					],
				],
			]
		);
		foreach ( $query->posts as $pid ) {
			$ids[] = (int) $pid;
		}

		return array_values( array_unique( $ids ) );
	}
}
