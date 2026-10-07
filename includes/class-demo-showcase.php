<?php
/** Portable fictional fixtures for every Rondo demo workflow. */
namespace Rondo\Demo;

use Rondo\Fields\Fields;
use Rondo\Fields\Formatter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DemoShowcase {
	public const POST_TYPES = [
		'person',
		'team',
		'commissie',
		'discipline_case',
		'rondo_invoice',
		'rondo_todo',
		'rondo_feedback',
		'dienst_type',
		'dienst_shift',
		'shift_template',
		'taakuitleg',
		'rondo_sponsor',
		'rondo_training',
		'rondo_room',
		'rondo_room_booking',
		'rondo_park_closure',
		'rondo_tournament',
		'rondo_tourn_entry',
		'rondo_comm_item',
		'rondo_comm_series',
		'rondo_display',
		'rondo_signage_item',
		'rondo_signage_list',
		'rondo_access_event',
		'rondo_admission',
		'rondo_guest_pass',
		'rondo_clothing_item',
		'rondo_clothing_txn',
		'rondo_profile_change',
		'rondo_onboard_round',
		'rondo_vog_submission',
		'rondo_twelve_report',
		'rondo_kantine_day',
		'rondo_purchase',
		'rondo_kassa_product',
		'rondo_match_reg',
		'rondo_match_batch',
	];
	public const SETTINGS   = [
		'rondo_club_name',
		'rondo_feature_toggles',
		'rondo_communication_channels',
		'rondo_newsletter_config',
		'rondo_training_settings',
		'rondo_training_active',
		'rondo_volunteer_signup_info',
		'rondo_volunteer_pool_commissies',
		'rondo_player_roles',
		'rondo_excluded_roles',
		'rondo_anniversary_milestones',
		'rondo_vog_exempt_commissies',
		'rondo_narrowcasting_default_playlist_id',
		'rondo_guest_pass_team_id',
		'rondo_twelve_product_groups',
		'rondo_match_compensation',
	];
	private const USER_META = [ 'rondo_linked_person_id', 'rondo_newsletter_profile', '_rondo_match_teams', 'rondo_approved' ];
	private const USER_CAPS = [ 'manage_training', 'narrowcasting', 'wedstrijdregistratie', 'wedstrijdzaken', 'feedback', 'commissies' ];
	private array $ids      = [];
	private \DateTimeImmutable $today;

	public function __construct() {
		$this->today = new \DateTimeImmutable( 'today', wp_timezone() );
	}

	/** Validate the entire fixture before the CLI is allowed to clean existing data. */
	public function validate( array $fixture ) {
		if ( ! get_option( 'rondo_is_demo_site', false ) ) {
			return new \WP_Error( 'demo_only', 'Showcase fixtures may only be imported on a demo site.' );
		}
		try {
			foreach ( [ 'meta', 'records', 'terms', 'settings', 'comments', 'demo_account' ] as $section ) {
				if ( isset( $fixture[ $section ] ) && ! is_array( $fixture[ $section ] ) ) {
					throw new \InvalidArgumentException( 'Invalid fixture section: ' . $section );
				}
			}
			if ( ( $fixture['meta']['version'] ?? '' ) !== '2.0' || ( $fixture['meta']['source'] ?? '' ) !== 'fictional_showcase'
				|| empty( $fixture['records'] ) || ! is_array( $fixture['records'] ) || count( $fixture['records'] ) > 2000 ) {
				throw new \InvalidArgumentException( 'Invalid fictional showcase fixture.' );
			}
			$this->ids = [];
			foreach ( array_merge( $fixture['terms'] ?? [], $fixture['records'] ) as $index => $record ) {
				$ref = $record['_ref'] ?? '';
				if ( ! is_string( $ref ) || ! preg_match( '/^[a-z_]+:[a-z0-9_-]+$/D', $ref ) || isset( $this->ids[ $ref ] ) ) {
					throw new \InvalidArgumentException( 'Missing or duplicate fixture reference.' );
				}
				$this->ids[ $ref ] = $index + 1;
			}
			foreach ( $fixture['terms'] ?? [] as $term ) {
				if ( ! in_array( $term['taxonomy'] ?? '', [ 'relationship_type', 'seizoen', 'clothing_category' ], true ) ) {
					throw new \InvalidArgumentException( 'Unsupported showcase taxonomy.' );
				}
				if ( empty( $term['name'] ) || empty( $term['slug'] ) || ! taxonomy_exists( $term['taxonomy'] ) ) {
					throw new \InvalidArgumentException( 'Invalid showcase term.' );
				}
				if ( ! empty( $term['fields'] ) ) {
					Formatter::for_storage( $term['taxonomy'], $this->resolve( $term['fields'] ) );
				}
			}
			foreach ( $fixture['records'] as $record ) {
				$type = $record['post_type'] ?? '';
				if ( ! in_array( $type, self::POST_TYPES, true ) || ! post_type_exists( $type ) || empty( $record['title'] )
					|| ! get_post_status_object( $record['status'] ?? 'publish' ) ) {
					throw new \InvalidArgumentException( 'Invalid record: ' . $record['_ref'] );
				}
				foreach ( [ 'fields', 'post_meta', 'taxonomies' ] as $section ) {
					if ( isset( $record[ $section ] ) && ! is_array( $record[ $section ] ) ) {
						throw new \InvalidArgumentException( 'Invalid record section: ' . $section );
					}
				}
				$fields = $this->resolve( $record['fields'] ?? [] );
				if ( $fields ) {
					try {
						Formatter::for_storage( $type, $fields );
					} catch ( \InvalidArgumentException $error ) {
						throw new \InvalidArgumentException( $record['_ref'] . ': ' . $error->getMessage() );
					}
				}
				$this->resolve( $record['post_meta'] ?? [] );
				$this->resolve( $record['parent'] ?? null );
				foreach ( $this->resolve( $record['taxonomies'] ?? [] ) as $taxonomy => $terms ) {
					if ( ! is_object_in_taxonomy( $type, $taxonomy ) || ! is_array( $terms ) ) {
						throw new \InvalidArgumentException( 'Invalid record taxonomy.' );
					}
				}
			}
			foreach ( $fixture['settings'] ?? [] as $key => $value ) {
				if ( ! in_array( $key, self::SETTINGS, true ) && ! preg_match( '/^rondo_(membership_fees|family_discount)_\{season\}$/D', $key ) ) {
					throw new \InvalidArgumentException( 'Unsupported showcase setting: ' . $key );
				}
				$this->resolve( $value );
			}
			foreach ( $fixture['comments'] ?? [] as $comment ) {
				$this->resolve( $comment['post'] );
				$this->resolve( $comment['date'] );
				if ( empty( $comment['content'] ) || ! in_array( $comment['type'] ?? 'rondo_note', [ 'rondo_note', 'rondo_activity', 'rondo_email' ], true ) ) {
					throw new \InvalidArgumentException( 'Invalid showcase comment.' );
				}
			}
			$account = $this->resolve( $fixture['demo_account'] ?? [] );
			if ( array_diff( $account['roles'] ?? [], [ 'rondo_bestuur', 'rondo_kaderlijst' ] ) || array_diff( $account['capabilities'] ?? [], self::USER_CAPS ) || array_diff( array_keys( $account['user_meta'] ?? [] ), self::USER_META ) ) {
				throw new \InvalidArgumentException( 'Unsupported demo account permission or setting.' );
			}
			foreach ( $account['roles'] ?? [] as $role ) {
				if ( ! get_role( $role ) ) {
					throw new \InvalidArgumentException( 'Missing demo role.' );
				}
			}
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'demo_fixture_invalid', $error->getMessage() );
		}
		return true;
	}

	/** Refuse a partial reset while native relationship or shift guards protect records. */
	public function validate_cleanup() {
		if ( ! get_option( 'rondo_is_demo_site', false ) ) {
			return new \WP_Error( 'demo_only', 'Showcase cleanup requires a demo site.' );
		}
		foreach ( self::POST_TYPES as $type ) {
			$page = 1;
			do {
				$posts = get_posts(
					[
						'post_type'        => $type,
						'post_status'      => array_keys( get_post_stati() ),
						'posts_per_page'   => 100,
						'paged'            => $page,
						'suppress_filters' => true,
					]
					);
				foreach ( $posts as $post ) {
					if ( apply_filters( 'pre_delete_post', null, $post, true ) !== null ) {
						return new \WP_Error( 'demo_cleanup_protected', 'Demo reset stopped before deletion: protected ' . $type . ' ' . $post->ID . '. Review and approve a separate demo reset first.' );
					}
				}
				++$page;
			} while ( count( $posts ) === 100 );
		}
		return true;
	}

	/** Reset only showcase-owned modules; upgrade flags and user accounts survive. */
	public function clean() {
		$valid = $this->validate_cleanup();
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		foreach ( self::POST_TYPES as $type ) {
			do {
				$ids = get_posts(
					[
						'post_type'        => $type,
						'post_status'      => array_keys( get_post_stati() ),
						'posts_per_page'   => 100,
						'fields'           => 'ids',
						'suppress_filters' => true,
					]
					);
				foreach ( $ids as $id ) {
					if ( get_post( $id ) && ! wp_delete_post( $id, true ) ) {
						throw new \RuntimeException( 'Could not remove showcase record: ' . esc_html( $type ) . ' ' . (int) $id );
					}
				}
			} while ( count( $ids ) === 100 );
		}
		foreach ( self::SETTINGS as $key ) {
			delete_option( $key );
		}
		delete_option( 'rondo_demo_showcase_manifest' );
		return true;
	}

	/** Allocate every ID first, then resolve relationships using the native field API. */
	public function import( array $fixture ) {
		$valid = $this->validate( $fixture );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$this->ids = [];
		foreach ( $fixture['terms'] ?? [] as $term ) {
			$name   = $this->resolve( $term['name'] );
			$slug   = $this->resolve( $term['slug'] );
			$exists = term_exists( $slug, $term['taxonomy'] );
			$result = $exists ?: wp_insert_term( $name, $term['taxonomy'], [ 'slug' => $slug ] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$this->ids[ $term['_ref'] ] = (int) $result['term_id'];
		}
		foreach ( $fixture['records'] as $record ) {
			$id = wp_insert_post(
				[
					'post_type'    => $record['post_type'],
					'post_title'   => $this->resolve( $record['title'] ),
					'post_content' => $this->resolve( $record['content'] ?? '' ),
					'post_status'  => $record['status'] ?? 'publish',
					'post_author'  => $this->resolve( [ '$user' => 'demo' ] ),
				],
				true
			);
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			$this->ids[ $record['_ref'] ] = (int) $id;
		}
		foreach ( $fixture['terms'] ?? [] as $term ) {
			foreach ( $term['fields'] ?? [] as $key => $value ) {
				Fields::update_for_term( $term['taxonomy'], $this->ids[ $term['_ref'] ], $key, $this->resolve( $value ) );
			}
		}
		foreach ( $fixture['records'] as $record ) {
			$id     = $this->ids[ $record['_ref'] ];
			$fields = $this->resolve( $record['fields'] ?? [] );
			if ( $fields ) {
				$result = Fields::update_many_for_post( $id, Formatter::for_storage( $record['post_type'], $fields ) );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
			foreach ( $this->resolve( $record['post_meta'] ?? [] ) as $key => $value ) {
				update_post_meta( $id, $key, wp_slash( $value ) );
			}
			foreach ( $this->resolve( $record['taxonomies'] ?? [] ) as $taxonomy => $terms ) {
				$result = wp_set_object_terms( $id, $terms, $taxonomy );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
			if ( ! empty( $record['parent'] ) ) {
				wp_update_post(
					[
						'ID'          => $id,
						'post_parent' => $this->resolve( $record['parent'] ),
					]
					);
			}
		}
		foreach ( $fixture['comments'] ?? [] as $comment ) {
			wp_insert_comment(
				[
					'comment_post_ID'  => $this->resolve( $comment['post'] ),
					'comment_content'  => $comment['content'],
					'comment_type'     => $comment['type'] ?? 'rondo_note',
					'comment_author'   => 'Demo clubbeheer',
					'user_id'          => $this->resolve( [ '$user' => 'demo' ] ),
					'comment_date'     => $this->resolve( $comment['date'] ),
					'comment_approved' => 1,
				]
			);
		}
		foreach ( $this->resolve( $fixture['settings'] ?? [] ) as $key => $value ) {
			update_option( $key, $value, false );
		}
		$user = get_user_by( 'login', 'demo' );
		if ( $user && ! empty( $fixture['demo_account'] ) ) {
			foreach ( $fixture['demo_account']['roles'] ?? [] as $role ) {
				$user->add_role( $role );
			}
			foreach ( $fixture['demo_account']['capabilities'] ?? [] as $capability ) {
				$user->add_cap( $capability );
			}
			foreach ( $this->resolve( $fixture['demo_account']['user_meta'] ?? [] ) as $key => $value ) {
				if ( ! in_array( $key, self::USER_META, true ) ) {
					continue;
				}
				update_user_meta( $user->ID, $key, $value );
			}
		}
		update_option(
			'rondo_demo_showcase_manifest',
			[
				'imported_at' => current_time( DATE_RFC3339 ),
				'refs'        => $this->ids,
				'coverage'    => $fixture['coverage'] ?? [],
			],
			false
			);
		return $this->ids;
	}

	/** Resolve explicit references, import-relative dates, JSON and season placeholders. */
	private function resolve( $value ) {
		if ( is_array( $value ) ) {
			if ( isset( $value['$ref'] ) ) {
				if ( ! isset( $this->ids[ $value['$ref'] ] ) ) {
					throw new \InvalidArgumentException( 'Unknown reference: ' . esc_html( $value['$ref'] ) );
				}
				return $this->ids[ $value['$ref'] ];
			}
			if ( isset( $value['$date'] ) ) {
				if ( ! is_string( $value['$date'] ) || ! preg_match( '/^(?:[+-]\d+ (?:days?|weeks?|months?|years?)(?: [0-2]\d:[0-5]\d)?|today(?: [0-2]\d:[0-5]\d)?)$/D', $value['$date'] ) ) {
					throw new \InvalidArgumentException( 'Invalid relative date expression.' );
				}
				$date = $this->today->modify( $value['$date'] );
				if ( ! $date ) {
					throw new \InvalidArgumentException( 'Invalid relative date.' );
				}
				return ( $value['format'] ?? '' ) === 'U' ? $date->getTimestamp() : $date->format( $value['format'] ?? 'Y-m-d' );
			}
			if ( isset( $value['$json'] ) ) {
				return wp_json_encode( $this->resolve( $value['$json'] ) );
			}
			if ( isset( $value['$user'] ) ) {
				if ( $value['$user'] !== 'demo' ) {
					throw new \InvalidArgumentException( 'Only the existing demo account can be referenced.' );
				}
				$user = get_user_by( 'login', 'demo' );
				return $user ? $user->ID : 0;
			}
			$result = [];
			foreach ( $value as $key => $child ) {
				$result[ is_string( $key ) ? $this->resolve( $key ) : $key ] = $this->resolve( $child );
			}
			return $result;
		}
		if ( is_string( $value ) ) {
			$year  = (int) $this->today->format( 'Y' ) - ( (int) $this->today->format( 'n' ) < 7 ? 1 : 0 );
			$value = str_replace( '{season}', $year . '-' . ( $year + 1 ), $value );
			$value = preg_replace_callback( '/\{\{([a-z_]+:[a-z0-9_-]+)\}\}/', fn( $match ) => (string) $this->resolve( [ '$ref' => $match[1] ] ), $value );
		}
		return $value;
	}
}
