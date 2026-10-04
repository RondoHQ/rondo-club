<?php
/** Native field definitions for private match registrations and settlements. */
return ( static function () {
	$definition = static function ( string $context, string $name, string $type = 'text', array $extra = [] ): array {
		return array_merge(
		[
			'canonical_name' => $name,
			'storage_name'   => $name,
			'name'           => $name,
			'key'            => 'field_' . $context . '_' . $name,
			'label'          => $name,
			'type'           => $type,
		],
		$extra
		);
	};
	$contexts   = [];
	foreach ( [ 'rondo_match_reg', 'rondo_match_batch' ] as $context ) {
		$fields = [];
		foreach ( [
			'team_id' => 'number',
			'season'  => 'text',
			'phase'   => 'text',
			'version' => 'number',
		] as $name => $type ) {
			$fields[ $name ] = $definition( $context, $name, $type );
		}
		if ( $context === 'rondo_match_reg' ) {
			foreach ( [
				'source_match_id'    => 'text',
				'source_fingerprint' => 'text',
				'played_on'          => 'date_picker',
				'opponent_name'      => 'text',
				'home'               => 'true_false',
				'category'           => 'text',
				'home_score'         => 'number',
				'away_score'         => 'number',
				'reason'             => 'text',
			] as $name => $type ) {
				$fields[ $name ] = $definition( $context, $name, $type );
			}
			$children = [];
			foreach ( [
				'person_id'     => 'number',
				'player_name'   => 'text',
				'participation' => 'text',
				'guest'         => 'true_false',
			] as $name => $type ) {
				$children[ $name ] = $definition( $context . '_selection', $name, $type );
			}
			$fields['selection'] = $definition( $context, 'selection', 'repeater', [ 'sub_fields' => $children ] );
		} else {
			foreach ( [ 'month', 'scheme', 'closed_at', 'bank_code' ] as $name ) {
				$fields[ $name ] = $definition( $context, $name );
			}
			$children = [];
			foreach ( [
				'person_id'           => 'number',
				'player_name'         => 'text',
				'days'                => 'number',
				'basis'               => 'number',
				'bank'                => 'number',
				'wins'                => 'number',
				'draws'               => 'number',
				'amount_cents'        => 'number',
				'iban'                => 'text',
				'bank_account_holder' => 'text',
				'nmbrs_name'          => 'text',
			] as $name => $type ) {
				$children[ $name ] = $definition( $context . '_rows', $name, $type );
			}
			$fields['rows'] = $definition( $context, 'rows', 'repeater', [ 'sub_fields' => $children ] );
		}
		$contexts[ $context ] = [
			'kind'   => 'post',
			'fields' => $fields,
		];
	}
	return $contexts;
} )();
