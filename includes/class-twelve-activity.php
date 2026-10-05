<?php
/** Hourly purchases, product revenue and scheduled kantine staffing. */
namespace Rondo\Twelve;

use Rondo\Fields\Fields;
use Rondo\Volunteer\ShiftAssignments;

class Activity {
	/** Optional versioned import contract; no customer or operator data. */
	public static function validate( array $data ): bool {
		$activity = $data['activity'] ?? null;
		if ( ! is_array( $activity ) || ( $activity['version'] ?? null ) !== 1 || ! is_array( $activity['transactions'] ?? null ) || ! isset( $data['product_revenue'] ) ) {
			return false;
		}
		$expected = [];
		foreach ( $data['product_revenue']['products'] as $product ) {
			$expected[ $product['product'] ] = [ $product['cashCents'], $product['businessclubCents'] ];
		}
		$actual = array_fill_keys( array_keys( $expected ), [ 0, 0 ] );
		$ids    = [];
		foreach ( $activity['transactions'] as $row ) {
			if ( ! is_array( $row ) || ! is_string( $row['id'] ?? null ) || $row['id'] === '' || isset( $ids[ $row['id'] ] ) || ! in_array( $row['kind'] ?? '', [ 'sale', 'correction' ], true ) || ! is_string( $row['localTime'] ?? null ) || ! is_array( $row['products'] ?? null ) || ! $row['products'] ) {
				return false;
			}
			$time = \DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $row['localTime'], new \DateTimeZone( 'Europe/Amsterdam' ) );
			if ( ! $time || $time->format( 'Y-m-d H:i' ) !== $row['localTime'] || $row['localTime'] < $data['period_start'] || $row['localTime'] >= $data['source']['coverage_end'] ) {
				return false;
			}
			$ids[ $row['id'] ] = true;
			$names             = [];
			foreach ( $row['products'] as $product ) {
				$name = $product['name'] ?? null;
				if ( ! is_string( $name ) || ! isset( $expected[ $name ] ) || isset( $names[ $name ] ) || ! is_int( $product['cashCents'] ?? null ) || ! is_int( $product['businessclubCents'] ?? null ) || abs( $product['cashCents'] ) >= 10000000000 || abs( $product['businessclubCents'] ) >= 10000000000 ) {
					return false;
				}
				$names[ $name ]      = true;
				$actual[ $name ][0] += $product['cashCents'];
				$actual[ $name ][1] += $product['businessclubCents'];
			}
		}
		return $actual === $expected;
	}

	/** Each basket counts once; unknown items take precedence over inferred types. */
	public static function hours( array $data ): ?array {
		if ( ! isset( $data['activity'] ) || ! self::validate( $data ) ) {
			return null;
		}
		$groups = get_option( ProductClassification::OPTION, [] );
		$hours  = [];
		$start  = new \DateTimeImmutable( $data['period_start'], new \DateTimeZone( 'Europe/Amsterdam' ) );
		// Twelve exports local minutes without UTC offsets: the repeated autumn
		// 02:00 hour is deliberately combined and labelled in the interface.
		for ( $i = 0; $i < 24; ++$i ) {
			$hour           = sprintf( '%02d', ( $i + 6 ) % 24 );
			$local          = ( $i < 18 ? $start : $start->modify( '+1 day' ) )->format( 'Y-m-d' ) . ' ' . $hour;
			$hours[ $hour ] = [
				'hour'         => $hour,
				'local'        => $local,
				'covered'      => $local . ':00' < $data['source']['coverage_end'],
				'complete'     => $local . ':59' < $data['source']['coverage_end'],
				'transactions' => array_fill_keys( [ 'food', 'non_food', 'mixed', 'unassigned', 'entree' ], 0 ),
				'revenue'      => array_fill_keys( [ 'food', 'non_food', 'unassigned', 'entree' ], 0 ),
				'corrections'  => 0,
			];
		}
		foreach ( $data['activity']['transactions'] as $row ) {
			$hour  = substr( $row['localTime'], 11, 2 );
			$types = [];
			foreach ( $row['products'] as $product ) {
				$group = $groups[ hash( 'sha256', $product['name'] ) ] ?? 'unassigned';
				if ( in_array( $group, [ 'other', 'merchandise' ], true ) ) {
					continue;
				}
				$types[ $group ]                      = true;
				$hours[ $hour ]['revenue'][ $group ] += $product['cashCents'] + $product['businessclubCents'];
			}
			if ( ! $types ) {
				continue;
			}
			if ( $row['kind'] === 'correction' ) {
				++$hours[ $hour ]['corrections'];
				continue;
			}
			$type = isset( $types['unassigned'] ) ? 'unassigned' : ( isset( $types['food'], $types['non_food'] ) ? 'mixed' : ( isset( $types['food'] ) ? 'food' : ( isset( $types['non_food'] ) ? 'non_food' : 'entree' ) ) );
			++$hours[ $hour ]['transactions'][ $type ];
		}
		return array_values( $hours );
	}

	/** Scheduled people, deduplicated at each instant; no personal data exposed. */
	public static function staffing( string $date ): array {
		$start  = new \DateTimeImmutable( $date . ' 06:00', new \DateTimeZone( 'Europe/Amsterdam' ) );
		$end    = $start->modify( '+1 day' );
		$ids    = get_posts(
			[
				'post_type'        => 'dienst_shift',
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_query'       => [
					[
						'key'     => 'start_datetime',
						'value'   => $end->format( 'Y-m-d H:i:s' ),
						'compare' => '<',
						'type'    => 'DATETIME',
					],
					[
						'key'     => 'end_datetime',
						'value'   => $start->format( 'Y-m-d H:i:s' ),
						'compare' => '>',
						'type'    => 'DATETIME',
					],
				],
			]
			);
		$shifts = [];
		foreach ( $ids as $id ) {
			if ( Fields::get_for_post( $id, 'status' ) === 'geannuleerd' ) {
				continue;
			}
			$type = (int) Fields::get_for_post( $id, 'dienst_type_id' );
			if ( ! in_array( get_post_meta( $type, '_rondo_seed_key', true ), [ 'kantine_bar', 'kantine_keuken_prep', 'kantine_keuken_verkoop' ], true ) ) {
				continue;
			}
			$shifts[] = [
				'start'  => new \DateTimeImmutable( Fields::get_for_post( $id, 'start_datetime' ), $start->getTimezone() ),
				'end'    => new \DateTimeImmutable( Fields::get_for_post( $id, 'end_datetime' ), $start->getTimezone() ),
				'people' => ShiftAssignments::person_ids( $id ),
			];
		}
		$result = [];
		for ( $time = $start; $time < $end; $time = $time->modify( '+1 hour' ) ) {
			$until   = min( $time->modify( '+1 hour' ), $end );
			$points  = [ $time->getTimestamp(), $until->getTimestamp() ];
			$overlap = array_filter( $shifts, static fn( $shift ) => $shift['start'] < $until && $shift['end'] > $time );
			foreach ( $overlap as $shift ) {
				$points[] = max( $time->getTimestamp(), $shift['start']->getTimestamp() );
				$points[] = min( $until->getTimestamp(), $shift['end']->getTimestamp() );
			}
			$points = array_values( array_unique( $points ) );
			sort( $points );
			$counts = [];
			foreach ( array_slice( $points, 0, -1 ) as $point ) {
				$people = [];
				foreach ( $overlap as $shift ) {
					if ( $shift['start']->getTimestamp() <= $point && $shift['end']->getTimestamp() > $point ) {
						$people = array_merge( $people, $shift['people'] );
					}
				}
				$counts[] = count( array_unique( $people ) );
			}
			$result[ $time->format( 'H' ) ] = [
				'scheduled' => (bool) $overlap,
				'min'       => min( $counts ),
				'max'       => max( $counts ),
			];
		}
		return $result;
	}

	/** Compare complete same-weekday reports with equally complete match archives. */
	public static function comparison( string $date, array $matches, array $reports, array $archive ): array {
		$values = [];
		if ( ! $matches['complete'] ) {
			return [
				'count'  => 0,
				'median' => null,
				'reason' => 'incomplete_matches',
			];
		}
		foreach ( $reports as $report ) {
			$day     = substr( $report['period_start'], 0, 10 );
			$fixture = isset( $archive[ $day ] ) ? MatchArchive::summary( $archive[ $day ] ) : null;
			if ( $day >= $date || date( 'N', strtotime( $day ) ) !== date( 'N', strtotime( $date ) ) || empty( $report['data']['source']['complete'] ) || ! isset( $report['data']['product_revenue'] ) || ! $fixture || ! $fixture['complete'] || abs( $fixture['count'] - $matches['count'] ) > 2 || $fixture['first_home'] !== $matches['first_home'] || $fixture['u23_home'] !== $matches['u23_home'] ) {
				continue;
			}
			$values[] = ReportAggregator::revenue_breakdown( $report['data'], ProductClassification::excluded_products() )['omzet_totaal'];
		}
		sort( $values );
		$count  = count( $values );
		$median = $count >= 5 ? ( $values[ (int) floor( ( $count - 1 ) / 2 ) ] + $values[ (int) floor( $count / 2 ) ] ) / 2 : null;
		return [
			'count'  => $count,
			'median' => $median,
			'reason' => $median === null ? 'too_few_days' : null,
		];
	}

	public static function day( string $date ): array {
		$repo     = new ReportRepository();
		$reports  = $repo->query( ( new \DateTimeImmutable( $date ) )->modify( '-1 year' )->format( 'Y-m-d' ), $date );
		$selected = array_values( array_filter( $reports, static fn( $report ) => substr( $report['period_start'], 0, 10 ) === $date ) );
		$data     = count( $selected ) === 1 ? $selected[0]['data'] : null;
		$archive  = MatchArchive::load();
		$matches  = MatchArchive::summary( $archive[ $date ] ?? [] );
		return [
			'date'            => $date,
			'hours'           => $data ? self::hours( $data ) : null,
			'staffing'        => self::staffing( $date ),
			'revenue'         => $data && isset( $data['product_revenue'] ) ? ReportAggregator::revenue_breakdown( $data, ProductClassification::excluded_products() )['omzet_totaal'] : null,
			'report_complete' => $data['source']['complete'] ?? false,
			'coverage_end'    => $data['source']['coverage_end'] ?? null,
			'matches'         => $matches,
			'comparison'      => self::comparison( $date, $matches, $reports, $archive ),
		];
	}
}
