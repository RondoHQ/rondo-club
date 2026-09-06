<?php
/**
 * Additive, repeatable examples for member-facing demo journeys.
 *
 * @package Rondo\Demo
 */

namespace Rondo\Demo;

use Rondo\Fields\Fields;

/** Explicitly invoked by the demo CLI helper; never loaded during web requests. */
final class FeatureFixtures {

	const META  = '_rondo_feature_demo_key';
	const STATE = 'rondo_feature_demo_state';

	/** Refuse to change any installation except the dedicated demo. */
	public static function guard(): void {
		if ( untrailingslashit( home_url() ) !== 'https://demo.rondo.club' || ! get_option( 'rondo_is_demo_site', false ) ) {
			throw new \RuntimeException( 'Feature fixtures require https://demo.rondo.club in demo mode.' );
		}
	}

	/** Summarize the bounded operation without writing records. */
	public static function plan(): array {
		self::guard();
		return [
			'people'      => 6,
			'teams'       => 2,
			'committees'  => 1,
			'shift_types' => 4,
			'shifts'      => 48,
			'sponsors'    => 1,
			'deletions'   => 0,
		];
	}

	/** Create or update one marked fixture, preserving every unrelated record. */
	private static function put( string $key, string $type, string $title, array $fields = [] ): int {
		$ids = get_posts(
			[
				'post_type'        => $type,
				'post_status'      => 'any',
				'posts_per_page'   => 2,
				'fields'           => 'ids',
				'meta_key'         => self::META,
				'meta_value'       => $key,
				'suppress_filters' => true,
			]
			);
		if ( count( $ids ) > 1 ) {
			throw new \RuntimeException( esc_html( 'Duplicate demo fixture: ' . $key ) );
		}
		$id = wp_insert_post(
			[
				'ID'          => (int) ( $ids[0] ?? 0 ),
				'post_type'   => $type,
				'post_status' => 'publish',
				'post_title'  => $title,
				'meta_input'  => [ self::META => $key ],
			],
			true
			);
		if ( is_wp_error( $id ) ) {
			throw new \RuntimeException( esc_html( $id->get_error_message() ) );
		}
		self::update( $id, $fields );
		return $id;
	}

	/** Surface field validation errors rather than reporting an incomplete seed as successful. */
	private static function update( int $id, array $fields ): void {
		$result = Fields::update_many_for_post( $id, $fields );
		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( esc_html( 'Demo record ' . $id . ': ' . $result->get_error_message() ) );
		}
	}

	/** Seed relative dates once; repeat runs preserve the chosen demonstration period. */
	public static function seed(): array {
		self::guard();
		$state       = get_option( self::STATE, [] );
		$base        = new \DateTimeImmutable( $state['base_date'] ?? wp_date( 'Y-m-d' ), wp_timezone() );
		$people      = [];
		$definitions = [
			'parent'    => [ 'Alex', 'Voorbeeld', 40, 'Verenigingslid' ],
			'partner'   => [ 'Robin', 'Voorbeeld', 39, 'Verenigingslid' ],
			'child-1'   => [ 'Noor', 'Voorbeeld', 12, 'Bondslid' ],
			'child-2'   => [ 'Sam', 'Voorbeeld', 9, 'Bondslid' ],
			'volunteer' => [ 'Daan', 'Demonstratie', 32, 'Verenigingslid' ],
			'former'    => [ 'Kim', 'Demonstratie', 45, 'Verenigingslid' ],
		];
		foreach ( $definitions as $key => [ $first, $last, $age, $type ] ) {
			$people[ $key ] = self::put(
				$key,
				'person',
				$first . ' ' . $last,
				[
					'first_name'     => $first,
					'last_name'      => $last,
					'email_1'        => $key . '@example.invalid',
					'birthdate'      => $base->modify( '-' . $age . ' years' )->format( 'Y-m-d' ),
					'leeftijdsgroep' => $age < 18 ? 'Onder ' . ( $age + 1 ) : 'Senioren',
					'type_lid'       => $type,
					'former_member'  => $key === 'former',
					'lid_sinds'      => $base->modify( '-3 years' )->format( 'Y-m-d' ),
				]
				);
		}
		$teams     = [ self::put( 'team-youth', 'team', 'Demo JO13-1' ), self::put( 'team-seniors', 'team', 'Demo Zaterdag 2' ) ];
		$committee = self::put( 'committee', 'commissie', 'Demo Vrijwilligerscommissie' );
		foreach ( [ 'parent', 'partner' ] as $parent ) {
			self::update(
				$people[ $parent ],
				[
					'relationships' => [
						[
							'related_person'    => $people['child-1'],
							'relationship_type' => \Rondo\Data\InverseRelationships::TYPE_CHILD,
						],
						[
							'related_person'    => $people['child-2'],
							'relationship_type' => \Rondo\Data\InverseRelationships::TYPE_CHILD,
						],
					],
				]
				);
		}
		foreach ( [ 'child-1', 'child-2' ] as $child ) {
			self::update(
				$people[ $child ],
				[
					'relationships' => [
						[
							'related_person'    => $people['parent'],
							'relationship_type' => \Rondo\Data\InverseRelationships::TYPE_PARENT,
						],
						[
							'related_person'    => $people['partner'],
							'relationship_type' => \Rondo\Data\InverseRelationships::TYPE_PARENT,
						],
					],
					'work_history'  => [
						[
							'team'        => $teams[0],
							'entity_type' => 'team',
							'job_title'   => 'Speler',
							'is_current'  => true,
						],
					],
				]
				);
		}
		self::update(
			$people['parent'],
			[
				'work_history' => [
					[
						'team'        => $teams[0],
						'entity_type' => 'team',
						'job_title'   => 'Trainer/coach',
						'is_current'  => true,
					],
					[
						'team'        => $committee,
						'entity_type' => 'commissie',
						'job_title'   => 'Vrijwilliger',
						'is_current'  => true,
					],
				],
			]
			);
		$sponsor         = self::put( 'sponsor', 'rondo_sponsor', 'Demo Voorbeeld Sportwinkel', [ 'sponsor_role' => 'awc_sponsor' ] );
		$contacts_result = \Rondo\Sponsors\Relations::set_contacts(
			$sponsor,
			[
				[
					'person_id'     => $people['former'],
					'receives_pass' => true,
				],
			]
			);
		if ( is_wp_error( $contacts_result ) ) {
			throw new \RuntimeException( esc_html( $contacts_result->get_error_message() ) );
		}
		$types = [];
		foreach ( [ [ 'Ontvangst wedstrijddag', '#007F83' ], [ 'Kantine zonder alcohol', '#2563eb' ], [ 'Klaarzetten jeugdvelden', '#a16207' ], [ 'Opruimen clubhuis', '#7c3aed' ] ] as $index => [ $name, $color ] ) {
			$types[] = self::put(
				'type-' . $index,
				'dienst_type',
				'Demo ' . $name,
				[
					'description'      => 'Fictieve inschrijftaak om de demokalender te proberen.',
					'color'            => $color,
					'default_capacity' => 2,
					'vog_required'     => false,
					'iva_required'     => false,
				]
				);
		}
		$shifts = [];
		for ( $i = 0; $i < 48; ++$i ) {
			$offset   = $i < 2 ? -7 + $i : 1 + (int) floor( ( $i - 2 ) / 2 );
			$start    = $base->modify( sprintf( '%+d days', $offset ) )->setTime( $i % 2 === 0 ? 9 : 13, 0 );
			$assigned = $i < 4 ? [ $people['parent'] ] : ( $i % 5 === 0 ? [ $people['volunteer'], $people['partner'] ] : ( $i % 3 === 0 ? [ $people['volunteer'] ] : [] ) );
			$status   = $i < 2 ? 'voltooid' : ( $i >= 46 ? 'geannuleerd' : ( count( $assigned ) === 2 ? 'vol' : 'open' ) );
			$shifts[] = self::put(
				'shift-' . $i,
				'dienst_shift',
				get_the_title( $types[ $i % 4 ] ) . ' ' . $start->format( 'd-m H:i' ),
				[
					'dienst_type_id'   => $types[ $i % 4 ],
					'start_datetime'   => $start->format( 'Y-m-d H:i:s' ),
					'end_datetime'     => $start->modify( '+3 hours' )->format( 'Y-m-d H:i:s' ),
					'capacity'         => 2,
					'assigned_persons' => $assigned,
					'status'           => $status,
					'notes'            => 'Demovoorbeeld; geen echte vrijwilligersafspraak.',
				]
				);
		}
		$state = [
			'base_date' => $base->format( 'Y-m-d' ),
			'people'    => $people,
			'teams'     => $teams,
			'committee' => $committee,
			'sponsor'   => $sponsor,
			'types'     => $types,
			'shifts'    => $shifts,
		];
		update_option( self::STATE, $state, false );
		return $state;
	}
}
