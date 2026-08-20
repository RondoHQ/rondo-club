<?php
/**
 * Read-only WordPress Abilities API integration.
 *
 * @package Rondo\Abilities
 */

namespace Rondo\Abilities;

use Rondo\Core\AccessControl;
use Rondo\Fields\Registry;
use Rondo\Fields\RestFields;
use WP_Error;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers typed, access-aware Rondo abilities for REST, MCP, and AI clients.
 */
final class Registrar {

	private const CATEGORY = 'rondo-records';

	private const SUPPORTED_CONTEXTS = [ 'person', 'team', 'commissie' ];

	private const SEARCH_FIELDS = [
		'person'    => [ 'first_name', 'infix', 'last_name', 'knvb_id', 'email_1', 'email_2' ],
		'team'      => [ 'name' ],
		'commissie' => [ 'name' ],
	];

	public function __construct() {
		add_action( 'wp_abilities_api_categories_init', [ $this, 'register_category' ] );
		add_action( 'wp_abilities_api_init', [ $this, 'register_abilities' ] );
	}

	/** Register the shared category before individual abilities. */
	public function register_category(): void {
		wp_register_ability_category(
			self::CATEGORY,
			[
				'label'       => __( 'Rondo Records', 'rondo' ),
				'description' => __( 'Read-only discovery of access-controlled Rondo people, teams, committees, and field contracts.', 'rondo' ),
			]
		);
	}

	/** Register the public, authenticated read-only ability surface. */
	public function register_abilities(): void {
		wp_register_ability(
			'rondo/search-records',
			[
				'label'               => __( 'Search Rondo Records', 'rondo' ),
				'description'         => __( 'Searches accessible people, teams, and committees by name or stable identifying fields. Returns compact record summaries.', 'rondo' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->search_input_schema(),
				'output_schema'       => $this->search_output_schema(),
				'execute_callback'    => [ $this, 'search_records' ],
				'permission_callback' => [ $this, 'can_read_records' ],
				'meta'                => $this->readonly_meta(),
			]
		);

		wp_register_ability(
			'rondo/get-record',
			[
				'label'               => __( 'Get a Rondo Record', 'rondo' ),
				'description'         => __( 'Returns one accessible person, team, or committee with canonical fields filtered for the current user.', 'rondo' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->record_input_schema(),
				'output_schema'       => $this->record_output_schema(),
				'execute_callback'    => [ $this, 'get_record' ],
				'permission_callback' => [ $this, 'can_read_record' ],
				'meta'                => $this->readonly_meta(),
			]
		);

		wp_register_ability(
			'rondo/get-field-schema',
			[
				'label'               => __( 'Get the Rondo Field Schema', 'rondo' ),
				'description'         => __( 'Returns the canonical, client-safe field contract for a Rondo record type, filtered for the current user.', 'rondo' ),
				'category'            => self::CATEGORY,
				'input_schema'        => $this->field_schema_input_schema(),
				'output_schema'       => $this->field_schema_output_schema(),
				'execute_callback'    => [ $this, 'get_field_schema' ],
				'permission_callback' => [ $this, 'can_read_records' ],
				'meta'                => $this->readonly_meta(),
			]
		);
	}

	/**
	 * Whether the current user may execute authenticated read abilities.
	 *
	 * @return bool
	 */
	public function can_read_records(): bool {
		return is_user_logged_in() && current_user_can( 'read' );
	}

	/**
	 * Apply the same row-level record visibility used by Rondo's REST API.
	 *
	 * @param mixed $input Normalized ability input.
	 * @return bool
	 */
	public function can_read_record( $input ) {
		if ( ! $this->can_read_records() || ! is_array( $input ) ) {
			return false;
		}

		$post = get_post( absint( $input['id'] ?? 0 ) );
		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, self::SUPPORTED_CONTEXTS, true ) || $post->post_status === 'trash' ) {
			return false;
		}

		$allowed = $post->post_type === 'person'
			? AccessControl::can_view_person( $post->ID )
			: current_user_can( 'read_post', $post->ID );

		return $allowed;
	}

	/**
	 * Search visible Rondo records without exposing field values in the result set.
	 *
	 * @param mixed $input Normalized ability input.
	 * @return array<string,mixed>
	 */
	public function search_records( $input ): array {
		$input    = is_array( $input ) ? $input : [];
		$query    = sanitize_text_field( (string) ( $input['query'] ?? '' ) );
		$limit    = min( 50, max( 1, absint( $input['limit'] ?? 10 ) ) );
		$contexts = isset( $input['contexts'] ) && is_array( $input['contexts'] )
			? array_values( array_intersect( self::SUPPORTED_CONTEXTS, $input['contexts'] ) )
			: self::SUPPORTED_CONTEXTS;
		$records  = [];

		foreach ( $contexts as $context ) {
			foreach ( $this->search_context( $context, $query, $limit ) as $post ) {
				$records[ $post->ID ] = $this->record_summary( $post );
			}
		}

		usort(
			$records,
			static function ( array $first, array $second ) use ( $query ): int {
				$first_score  = self::title_match_score( $first['title'], $query );
				$second_score = self::title_match_score( $second['title'], $query );
				return $first_score === $second_score
					? strcasecmp( $first['title'], $second['title'] )
					: $second_score <=> $first_score;
			}
		);

		$records = array_slice( $records, 0, $limit );

		return [
			'query'   => $query,
			'total'   => count( $records ),
			'records' => $records,
		];
	}

	/**
	 * Return one record through the canonical, visibility-aware field serializer.
	 *
	 * @param mixed $input Normalized ability input.
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_record( $input ) {
		$input  = is_array( $input ) ? $input : [];
		$post   = get_post( absint( $input['id'] ?? 0 ) );
		$fields = RestFields::for_post( $post->post_type, $post->ID );

		if ( ! empty( $input['fields'] ) && is_array( $input['fields'] ) ) {
			$requested = array_values( array_unique( array_map( 'strval', $input['fields'] ) ) );
			$unknown   = array_values( array_diff( $requested, array_keys( $fields ) ) );
			if ( $unknown ) {
				return new WP_Error(
					'rondo_ability_field_unavailable',
					sprintf(
						/* translators: %s: Comma-separated canonical field names. */
						__( 'These fields are unknown or unavailable to the current user: %s', 'rondo' ),
						implode( ', ', $unknown )
					),
					[ 'status' => 400 ]
				);
			}
			$fields = array_intersect_key( $fields, array_flip( $requested ) );
		}

		return [
			'id'                 => (int) $post->ID,
			'type'               => (string) $post->post_type,
			'title'              => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'slug'               => (string) $post->post_name,
			'status'             => (string) $post->post_status,
			'featured_image_url' => get_the_post_thumbnail_url( $post->ID, 'full' ) ?: null,
			'fields'             => $fields,
		];
	}

	/**
	 * Return a client-safe field contract with storage details removed.
	 *
	 * @param mixed $input Normalized ability input.
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_field_schema( $input ) {
		$input       = is_array( $input ) ? $input : [];
		$context     = sanitize_key( (string) ( $input['context'] ?? '' ) );
		$definitions = $this->visible_field_definitions( $context );

		if ( ! empty( $input['fields'] ) && is_array( $input['fields'] ) ) {
			$requested = array_values( array_unique( array_map( 'strval', $input['fields'] ) ) );
			$unknown   = array_values( array_diff( $requested, array_keys( $definitions ) ) );
			if ( $unknown ) {
				return new WP_Error(
					'rondo_ability_field_unavailable',
					sprintf(
						/* translators: %s: Comma-separated canonical field names. */
						__( 'These fields are unknown or unavailable to the current user: %s', 'rondo' ),
						implode( ', ', $unknown )
					),
					[ 'status' => 400 ]
				);
			}
			$definitions = array_intersect_key( $definitions, array_flip( $requested ) );
		}

		return [
			'context'          => $context,
			'registry_version' => Registry::version(),
			'fields'           => array_values( array_map( [ $this, 'field_contract' ], $definitions ) ),
		];
	}

	/** @return array<string,mixed> */
	private function readonly_meta(): array {
		return [
			'annotations' => [
				'readonly'    => true,
				'destructive' => false,
				'idempotent'  => true,
			],
			'public'      => true,
		];
	}

	/**
	 * Search one post type through WordPress queries so AccessControl filters apply.
	 *
	 * @return WP_Post[]
	 */
	private function search_context( string $context, string $query, int $limit ): array {
		$posts = [];
		$args  = [
			'post_type'        => $context,
			'post_status'      => 'publish',
			'posts_per_page'   => $limit,
			'no_found_rows'    => true,
			'suppress_filters' => false,
		];

		foreach ( get_posts( $args + [ 's' => $query ] ) as $post ) {
			$posts[ $post->ID ] = $post;
		}

		$meta_query = [ 'relation' => 'OR' ];
		foreach ( self::SEARCH_FIELDS[ $context ] as $canonical_name ) {
			$definition = Registry::resolve( $context, $canonical_name );
			if ( $definition['storage_name'] === null ) {
				continue;
			}
			$meta_query[] = [
				'key'     => $definition['storage_name'],
				'value'   => $query,
				'compare' => 'LIKE',
			];
		}

		if ( count( $meta_query ) > 1 ) {
			foreach ( get_posts( $args + [ 'meta_query' => $meta_query ] ) as $post ) {
				$posts[ $post->ID ] = $post;
			}
		}

		return array_values( $posts );
	}

	/** @return array<string,mixed> */
	private function record_summary( WP_Post $post ): array {
		return [
			'id'                 => (int) $post->ID,
			'type'               => (string) $post->post_type,
			'title'              => html_entity_decode( get_the_title( $post ), ENT_QUOTES, 'UTF-8' ),
			'slug'               => (string) $post->post_name,
			'status'             => (string) $post->post_status,
			'featured_image_url' => get_the_post_thumbnail_url( $post->ID, 'thumbnail' ) ?: null,
		];
	}

	private static function title_match_score( string $title, string $query ): int {
		$title = strtolower( $title );
		$query = strtolower( $query );
		if ( $title === $query ) {
			return 3;
		}
		if ( str_starts_with( $title, $query ) ) {
			return 2;
		}
		return str_contains( $title, $query ) ? 1 : 0;
	}

	/** @return array<string,array<string,mixed>> */
	private function visible_field_definitions( string $context ): array {
		$definitions = Registry::fields_for( $context );
		if ( $context !== 'person' ) {
			return $definitions;
		}

		if ( AccessControl::is_scoped_member() ) {
			$by_storage = [];
			foreach ( $definitions as $definition ) {
				if ( $definition['storage_name'] !== null ) {
					$by_storage[ $definition['storage_name'] ] = $definition;
				}
			}
			$definitions = [];
			foreach ( AccessControl::filter_member_visible_fields( $by_storage ) as $definition ) {
				$definitions[ $definition['canonical_name'] ] = $definition;
			}
		}

		foreach ( $definitions as $canonical_name => $definition ) {
			if ( AccessControl::field_is_hidden( $canonical_name ) ) {
				unset( $definitions[ $canonical_name ] );
			}
		}

		return $definitions;
	}

	/** @return array<string,mixed> */
	private function field_contract( array $definition ): array {
		$sub_fields = [];
		foreach ( $definition['sub_fields'] ?? [] as $sub_field ) {
			$sub_fields[] = $this->field_contract( $sub_field );
		}

		return [
			'name'        => (string) $definition['canonical_name'],
			'label'       => (string) ( $definition['label'] ?? $definition['canonical_name'] ),
			'description' => (string) ( $definition['instructions'] ?? '' ),
			'type'        => (string) $definition['type'],
			'required'    => ! empty( $definition['required'] ),
			'read_only'   => ! empty( $definition['read_only'] ) || ! empty( $definition['readonly'] ),
			'multiple'    => ! empty( $definition['multiple'] ) || in_array( $definition['type'], [ 'repeater', 'relationship', 'gallery', 'checkbox' ], true ),
			'sub_fields'  => $sub_fields,
		];
	}

	/** @return array<string,mixed> */
	private function search_input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'query'    => [
					'type'        => 'string',
					'description' => __( 'Name, email address, KNVB ID, or other identifying text to search for.', 'rondo' ),
					'minLength'   => 2,
					'maxLength'   => 100,
				],
				'contexts' => [
					'type'        => 'array',
					'description' => __( 'Record types to search. Defaults to people, teams, and committees.', 'rondo' ),
					'items'       => [
						'type' => 'string',
						'enum' => self::SUPPORTED_CONTEXTS,
					],
					'uniqueItems' => true,
					'default'     => self::SUPPORTED_CONTEXTS,
				],
				'limit'    => [
					'type'        => 'integer',
					'description' => __( 'Maximum number of records to return.', 'rondo' ),
					'minimum'     => 1,
					'maximum'     => 50,
					'default'     => 10,
				],
			],
			'required'             => [ 'query' ],
			'additionalProperties' => false,
		];
	}

	/** @return array<string,mixed> */
	private function search_output_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'query'   => [ 'type' => 'string' ],
				'total'   => [ 'type' => 'integer' ],
				'records' => [
					'type'  => 'array',
					'items' => $this->summary_schema(),
				],
			],
			'required'             => [ 'query', 'total', 'records' ],
			'additionalProperties' => false,
		];
	}

	/** @return array<string,mixed> */
	private function record_input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'id'     => [
					'type'        => 'integer',
					'description' => __( 'WordPress post ID of the Rondo record.', 'rondo' ),
					'minimum'     => 1,
				],
				'fields' => [
					'type'        => 'array',
					'description' => __( 'Optional canonical field names to include. All visible fields are returned when omitted.', 'rondo' ),
					'items'       => [ 'type' => 'string' ],
					'uniqueItems' => true,
				],
			],
			'required'             => [ 'id' ],
			'additionalProperties' => false,
		];
	}

	/** @return array<string,mixed> */
	private function record_output_schema(): array {
		$schema                         = $this->summary_schema();
		$schema['properties']['fields'] = [
			'type'                 => 'object',
			'additionalProperties' => true,
		];
		$schema['required'][]           = 'fields';
		return $schema;
	}

	/** @return array<string,mixed> */
	private function summary_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'id'                 => [ 'type' => 'integer' ],
				'type'               => [
					'type' => 'string',
					'enum' => self::SUPPORTED_CONTEXTS,
				],
				'title'              => [ 'type' => 'string' ],
				'slug'               => [ 'type' => 'string' ],
				'status'             => [ 'type' => 'string' ],
				'featured_image_url' => [ 'type' => [ 'string', 'null' ] ],
			],
			'required'             => [ 'id', 'type', 'title', 'slug', 'status', 'featured_image_url' ],
			'additionalProperties' => false,
		];
	}

	/** @return array<string,mixed> */
	private function field_schema_input_schema(): array {
		return [
			'type'                 => 'object',
			'properties'           => [
				'context' => [
					'type'        => 'string',
					'description' => __( 'Rondo record type whose canonical fields should be described.', 'rondo' ),
					'enum'        => self::SUPPORTED_CONTEXTS,
				],
				'fields'  => [
					'type'        => 'array',
					'description' => __( 'Optional canonical field names to describe.', 'rondo' ),
					'items'       => [ 'type' => 'string' ],
					'uniqueItems' => true,
				],
			],
			'required'             => [ 'context' ],
			'additionalProperties' => false,
		];
	}

	/** @return array<string,mixed> */
	private function field_schema_output_schema(): array {
		$field = [
			'type'                 => 'object',
			'properties'           => [
				'name'        => [ 'type' => 'string' ],
				'label'       => [ 'type' => 'string' ],
				'description' => [ 'type' => 'string' ],
				'type'        => [ 'type' => 'string' ],
				'required'    => [ 'type' => 'boolean' ],
				'read_only'   => [ 'type' => 'boolean' ],
				'multiple'    => [ 'type' => 'boolean' ],
				'sub_fields'  => [
					'type'  => 'array',
					'items' => [ 'type' => 'object' ],
				],
			],
			'required'             => [ 'name', 'label', 'description', 'type', 'required', 'read_only', 'multiple', 'sub_fields' ],
			'additionalProperties' => false,
		];

		return [
			'type'                 => 'object',
			'properties'           => [
				'context'          => [
					'type' => 'string',
					'enum' => self::SUPPORTED_CONTEXTS,
				],
				'registry_version' => [ 'type' => 'integer' ],
				'fields'           => [
					'type'  => 'array',
					'items' => $field,
				],
			],
			'required'             => [ 'context', 'registry_version', 'fields' ],
			'additionalProperties' => false,
		];
	}
}
