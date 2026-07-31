<?php
/**
 * Compile the checked-in ACF JSON definitions into Rondo's native field registry.
 *
 * This script deliberately performs canonical-name normalization at build time.
 * Runtime code consumes the explicit canonical_name/storage_name pairs written to
 * includes/config/field-registry.php and never guesses by replacing characters.
 *
 * Usage: php bin/generate-field-registry.php
 */

declare(strict_types=1);

$root       = dirname( __DIR__ );
$source_dir = $root . '/acf-json';
$target     = $root . '/includes/config/field-registry.php';

$context_overrides = [
	// The field group predates the stadion_feedback -> rondo_feedback rename.
	'stadion_feedback' => 'rondo_feedback',
];

$canonical_overrides = [
	'person' => [
		'work_history'  => [
			'team' => 'team_id',
		],
		'relationships' => [
			'related_person'    => 'related_person_id',
			'relationship_type' => 'relationship_type_id',
		],
	],
];

$layout_types = [ 'accordion', 'clone', 'message', 'tab' ];

/**
 * Return a deterministic, recursively key-sorted value.
 *
 * @param mixed $value Value to sort.
 * @return mixed
 */
$sort_recursive = static function ( $value ) use ( &$sort_recursive ) {
	if ( ! is_array( $value ) ) {
		return $value;
	}

	if ( array_is_list( $value ) ) {
		return array_map( $sort_recursive, $value );
	}

	ksort( $value );
	foreach ( $value as $key => $child ) {
		$value[ $key ] = $sort_recursive( $child );
	}
	return $value;
};

/**
 * Compile one ACF field into a framework-neutral definition.
 *
 * @param array<string,mixed> $field ACF JSON field.
 * @param string              $context Context name.
 * @param string|null         $parent_name Parent legacy name.
 * @return array<string,mixed>|null
 */
$compile_field = static function ( array $field, string $context, ?string $parent_name = null ) use ( &$compile_field, $canonical_overrides, $layout_types, $sort_recursive ): ?array {
	$type         = (string) ( $field['type'] ?? '' );
	$storage_name = (string) ( $field['name'] ?? '' );
	if ( $storage_name === '' || in_array( $type, $layout_types, true ) ) {
		return null;
	}

	$canonical_name = str_replace( '-', '_', $storage_name );
	if ( $parent_name !== null && isset( $canonical_overrides[ $context ][ $parent_name ][ $storage_name ] ) ) {
		$canonical_name = $canonical_overrides[ $context ][ $parent_name ][ $storage_name ];
	}

	$definition = $field;
	unset( $definition['wrapper'] );
	$definition['storage_name']   = $storage_name;
	$definition['canonical_name'] = $canonical_name;

	if ( $type === 'date_picker' ) {
		$definition['storage_format'] = 'Ymd';
		$definition['wire_format']    = 'Y-m-d';
		$definition['timezone']       = 'date_only';
	} elseif ( $type === 'date_time_picker' ) {
		$definition['storage_format'] = 'Y-m-d H:i:s';
		$definition['wire_format']    = DATE_RFC3339;
		// Existing values are produced with current_time() or represent local shifts.
		$definition['timezone'] = 'site';
	} elseif ( $type === 'time_picker' ) {
		$definition['storage_format'] = 'H:i:s';
		$definition['wire_format']    = 'H:i';
		$definition['timezone']       = 'site';
	}

	if ( ! empty( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
		$sub_fields = [];
		foreach ( $field['sub_fields'] as $sub_field ) {
			$compiled = $compile_field( $sub_field, $context, $storage_name );
			if ( $compiled !== null ) {
				$sub_fields[ $compiled['canonical_name'] ] = $compiled;
			}
		}
		$definition['sub_fields'] = $sub_fields;
	}

	return $sort_recursive( $definition );
};

$registry = [
	'version'             => 1,
	'generated_from'      => 'acf-json/*.json',
	'reserved_exceptions' => [
		// These are explicitly namespaced business states (fields.status), not
		// WordPress's top-level post lifecycle status.
		'dienst_shift'   => [ 'status' ],
		'rondo_feedback' => [ 'status' ],
		'rondo_invoice'  => [ 'status' ],
	],
	'reserved_top_level'  => [
		'id',
		'title',
		'slug',
		'status',
		'type',
		'author',
		'date',
		'date_gmt',
		'modified',
		'modified_gmt',
		'link',
		'featured_media',
		'template',
		'meta',
		'acf',
		'fields',
		'_links',
		'_embedded',
	],
	'computed_top_level'  => [
		'person'    => [ 'is_deceased', 'birth_year' ],
		'team'      => [ 'player_count', 'staff_count' ],
		'commissie' => [ 'member_count' ],
	],
	'contexts'            => [],
];

$files = glob( $source_dir . '/*.json' );
sort( $files );
foreach ( $files as $file ) {
	$group = json_decode( (string) file_get_contents( $file ), true, 512, JSON_THROW_ON_ERROR );
	$rule  = $group['location'][0][0] ?? null;
	if ( ! is_array( $rule ) || ! in_array( $rule['param'] ?? '', [ 'post_type', 'taxonomy' ], true ) ) {
		throw new RuntimeException( 'Unsupported field-group location in ' . basename( $file ) );
	}

	$kind    = $rule['param'] === 'taxonomy' ? 'term' : 'post';
	$context = (string) $rule['value'];
	$context = $context_overrides[ $context ] ?? $context;

	if ( ! isset( $registry['contexts'][ $context ] ) ) {
		$registry['contexts'][ $context ] = [
			'kind'   => $kind,
			'fields' => [],
		];
	}

	foreach ( $group['fields'] ?? [] as $field ) {
		$compiled = $compile_field( $field, $context );
		if ( $compiled === null ) {
			continue;
		}

		$name = $compiled['canonical_name'];
		if ( isset( $registry['contexts'][ $context ]['fields'][ $name ] ) ) {
			throw new RuntimeException( "Duplicate canonical field {$context}.{$name}" );
		}
		$registry['contexts'][ $context ]['fields'][ $name ] = $compiled;
	}
}

// Domain fields intentionally stored as plain post meta already. They still
// belong to the canonical fields contract and must not be lost merely because
// no ACF JSON definition owns them.
foreach ( [
	'vog_email_sent_date',
	'vog_justis_submitted_date',
	'vog_reminder_sent_date',
] as $name ) {
	$registry['contexts']['person']['fields'][ $name ] = [
		'backend'        => 'meta',
		'canonical_name' => $name,
		'label'          => $name,
		'storage_name'   => $name,
		'storage_format' => 'Y-m-d H:i:s',
		'timezone'       => 'site',
		'type'           => 'date_time_picker',
		'wire_format'    => DATE_RFC3339,
	];
}

// Relationship response enrichment belongs to the canonical contract rather
// than the stored repeater. These properties are explicit, read-only fields.
$relationship_fields =& $registry['contexts']['person']['fields']['relationships']['sub_fields'];
foreach ( [
	'person_name'       => 'string',
	'person_thumbnail'  => 'string',
	'relationship_name' => 'string',
	'relationship_slug' => 'string',
] as $name => $value_type ) {
	$relationship_fields[ $name ] = [
		'canonical_name' => $name,
		'storage_name'   => null,
		'type'           => $value_type,
		'read_only'      => true,
	];
}

$registry = $sort_recursive( $registry );
$output   = "<?php\n/**\n * Generated native field registry.\n *\n * Do not edit manually; run php bin/generate-field-registry.php.\n */\n\nreturn "
	. var_export( $registry, true )
	. ";\n";

if ( ! is_dir( dirname( $target ) ) ) {
	mkdir( dirname( $target ), 0775, true );
}
file_put_contents( $target, $output );
fwrite( STDOUT, 'Wrote ' . $target . PHP_EOL );
