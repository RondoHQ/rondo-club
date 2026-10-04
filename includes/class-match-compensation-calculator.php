<?php
/** Pure, integer-cent season calculations. */
namespace Rondo\Matches;

final class CompensationCalculator {
	public const STATUSES   = [ 'basis', 'bank', 'not_selected', 'injured', 'suspended', 'other_team' ];
	public const CATEGORIES = [ 'competitie', 'nacompetitie', 'beker', 'oefen' ];

	/** No names, accounts or mutable external state participate in the calculation. */
	public static function calculate( array $registrations, string $scheme ): array {
		$rows = [];
		foreach ( $registrations as $match ) {
			if ( ! in_array( $match['category'], [ 'competitie', 'nacompetitie' ], true ) ) {
				continue;
			}
			$our   = (int) ( $match['home'] ? $match['home_score'] : $match['away_score'] );
			$their = (int) ( $match['home'] ? $match['away_score'] : $match['home_score'] );
			$win   = $our > $their ? 1 : 0;
			$draw  = $our === $their ? 1 : 0;
			foreach ( $match['selection'] as $player ) {
				if ( ! in_array( $player['participation'], [ 'basis', 'bank' ], true ) ) {
					continue;
				}
				$id            = (int) $player['person_id'];
				$rows[ $id ] ??= [
					'person_id'    => $id,
					'player_name'  => $player['player_name'],
					'days'         => 0,
					'basis'        => 0,
					'bank'         => 0,
					'wins'         => 0,
					'draws'        => 0,
					'amount_cents' => 0,
				];
				++$rows[ $id ]['days'];
				++$rows[ $id ][ $player['participation'] ];
				$rows[ $id ]['wins']         += $win;
				$rows[ $id ]['draws']        += $draw;
				$rows[ $id ]['amount_cents'] += ( $win * 3 + $draw ) * ( $scheme === 'awc1' ? 3000 : 1500 );
			}
		}
		ksort( $rows );
		return array_values( $rows );
	}
}
