<?php
/** Private analysis documents; validated by the survey analysis service. */
return ( static function () {
	$contexts = [];
	foreach ( [
		'rondo_survey'       => 'analysis_profiles',
		'rondo_survey_reply' => 'analysis_labels',
	] as $context => $name ) {
		$contexts[ $context ] = [
			'kind'   => 'post',
			'fields' => [
				$name => [
					'canonical_name' => $name,
					'storage_name'   => '_' . $name,
					'name'           => $name,
					'key'            => 'field_' . $context . '_' . $name,
					'label'          => $name,
					'type'           => 'textarea',
					'read_only'      => true,
				],
			],
		];
	}
	return $contexts;
} )();
