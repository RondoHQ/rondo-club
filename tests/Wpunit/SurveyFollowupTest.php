<?php
namespace Tests\Wpunit;

use Rondo\Surveys\Surveys;
use Rondo\Surveys\Followup;
use Rondo\Surveys\RoundComparison;
use Rondo\Surveys\Analysis;
use Rondo\Surveys\AnalysisSchema;
use Rondo\REST\SurveyController;
use Tests\Support\RondoTestCase;

class SurveyFollowupTest extends RondoTestCase {
	private function manager(): int {
		$id   = $this->createRondoUser();
		$user = new \WP_User( $id );
		$user->add_cap( 'enquetes' );
		$user->add_cap( 'enquete_resultaten' );
		wp_set_current_user( $id );
		return $id;
	}

	private function fixture( array $answers = [ 'yes', 'no', 'unknown' ], string $prefix = '', string $type = 'single', array $labels = [
		'yes'     => 'Goed',
		'no'      => 'Slecht',
		'unknown' => 'Weet ik niet',
	], string $question_label = 'Hoe is de accommodatie?' ): array {
		$id = Surveys::create(
			[
				'title'    => 'Fictieve ronde ' . $prefix,
				'document' => [
					'sections' => [
						[
							'id'        => 's',
							'title'     => 'Ervaring',
							'questions' => [
								[
									'id'      => $prefix . 'rating',
									'label'   => $question_label,
									'type'    => $type,
									'options' => array_map(
										static fn( $key, $label ) => [
											'id'    => $prefix . $key,
											'label' => $label,
										],
										array_keys( $labels ),
										$labels
								),
								],
								[
									'id'    => 'note',
									'label' => 'Toelichting',
									'type'  => 'text',
								],
							],
						],
					],
				],
			]
			);
		$this->assertIsInt( $id );
		Surveys::action( $id, 'publish' );
		$version = Surveys::read( $id )['current_version'];
		$ids     = [];
		foreach ( $answers as $answer ) {
			$values = [ 'note' => 'Fictieve tekst over licht.' ];
			if ( $answer !== null ) {
				$values[ $prefix . 'rating' ] = is_array( $answer ) ? array_map( static fn( $a ) => $prefix . $a, $answer ) : $prefix . $answer; }
			$ids[] = Surveys::write(
				Surveys::RESPONSE,
				[
					'version' => $version,
					'answers' => $values,
				],
				0,
				$id
				);
		}
		return [ $id, $version, $ids ];
	}

	private function draft( int $owner, int $version, int $reply ): array {
		return [
			'version' => $version,
			'title'   => 'Verlichting bespreken',
			'owner'   => $owner,
			'due'     => '2026-01-01',
			'notes'   => 'Interne notitie',
			'reason'  => '',
			'status'  => 'open',
			'source'  => [
				'type' => 'response',
				'id'   => $reply,
			],
		];
	}

	private function rule( int $survey, int $version ): array {
		$rule = Followup::save_rule(
			$survey,
			[
				'version'  => $version,
				'title'    => 'Lage waardering beoordelen',
				'active'   => true,
				'kind'     => 'answer',
				'question' => 'rating',
				'options'  => [ 'no' ],
			]
			);
		$this->assertIsArray( $rule );
		return $rule;
	}

	private function comparison( array $first, array $second, string $prefix = '' ): array {
		$round = static fn( $f, $p ) => [
			'survey_id'     => $f[0],
			'version'       => $f[1],
			'question'      => $p . 'rating',
			'options'       => [ $p . 'yes' ],
			'exclude'       => [ $p . 'unknown' ],
			'start'         => '2026-01-01',
			'end'           => '2026-02-01',
			'audience'      => 'Alle leden',
			'compatibility' => 'comparable',
			'note'          => '',
		];
		return [
			'version'    => $first[1],
			'title'      => 'Tevredenheid',
			'definition' => 'Goed onder antwoorden, zonder weet ik niet.',
			'rounds'     => [ $round( $first, '' ), $round( $second, $prefix ) ],
		];
	}

	public function test_actions_are_shared_persistent_filterable_and_audited_without_mail(): void {
		$owner                = $this->manager();
		[ $id, $v, $replies ] = $this->fixture();
		$emails               = [];
		$block                = static function ( $pre, $args ) use ( &$emails ) {
			$emails[] = $args;
			return true;
		};
		add_filter( 'pre_wp_mail', $block, 10, 2 );
		try {
			$action = Followup::save( $id, $this->draft( $owner, $v, $replies[0] ) );
			$this->assertIsArray( $action );
			$other = $this->manager();
			$rows  = Followup::listing(
				$id,
				[
					'version' => $v,
					'owner'   => $owner,
					'status'  => 'open',
					'overdue' => true,
				]
				);
			$this->assertSame( 1, $rows['total'] );
			$this->assertArrayNotHasKey( 'history', $rows['rows'][0] );
			$this->assertSame(
				0,
				Followup::listing(
				$id,
				[
					'version' => $v,
					'owner'   => $other,
				]
				)['total']
				);
			$done = Followup::save(
				$id,
				array_replace(
				$action,
				[
					'status' => 'done',
					'notes'  => 'Besproken met accommodatiecommissie',
				]
				)
				);
			$this->assertSame( 'done', $done['status'] );
			$detail = Followup::detail( $id, $v, $action['id'] );
			$this->assertCount( 2, $detail['history'] );
			$this->assertSame( $other, $detail['history'][1]['author'] );
			$this->assertSame(
				[
					'from' => 'open',
					'to'   => 'done',
				],
				$detail['history'][1]['changes']['status']
				);
			$this->assertFalse( $detail['overdue'] );
			$this->assertSame( [], $emails );
			$this->assertSame( [], Surveys::children( $id, 'rondo_todo' ) );
		} finally {
			remove_filter( 'pre_wp_mail', $block, 10 ); }
	}

	public function test_action_conflicts_invalid_assignments_and_reasons_are_rejected(): void {
		$owner                = $this->manager();
		[ $id, $v, $replies ] = $this->fixture();
		$draft                = $this->draft( $owner, $v, $replies[0] );
		foreach ( [ [ 'owner' => 0 ], [ 'owner' => $this->createRondoUser() ], [ 'due' => '2026-02-30' ], [ 'title' => '' ], [ 'status' => 'no_action' ], [ 'status' => 'proposed' ] ] as $patch ) {
			$this->assertWPError( Followup::save( $id, array_replace( $draft, $patch ) ) );
		}
		$saved = Followup::save( $id, $draft );
		$this->assertSame( 409, Followup::save( $id, array_replace( $saved, [ 'revision' => 'stale' ] ) )->get_error_data()['status'] );
		$closed = Followup::save(
			$id,
			array_replace(
			$saved,
			[
				'status' => 'no_action',
				'reason' => 'Wordt al opgepakt',
			]
			)
			);
		$this->assertSame( 'Wordt al opgepakt', $closed['reason'] );
		$this->assertSame( 409, Surveys::locked( 'survey:' . $id, static fn() => Followup::save( $id, $draft ) )->get_error_data()['status'] );
	}

	public function test_foreign_sources_versions_and_deleted_sources_are_protected(): void {
		$owner                     = $this->manager();
		[ $id, $v, $replies ]      = $this->fixture();
		[ $other, $ov, $oreplies ] = $this->fixture();
		$draft                     = $this->draft( $owner, $v, $replies[0] );
		$this->assertWPError(
			Followup::save(
			$id,
			array_replace(
			$draft,
			[
				'source' => [
					'type' => 'response',
					'id'   => $oreplies[0],
				],
			]
			)
			)
			);
		$action = Followup::save( $id, $draft );
		$this->assertWPError( Followup::detail( $other, $ov, $action['id'] ) );
		wp_delete_post( $replies[0], true );
		$detail = Followup::detail( $id, $v, $action['id'] );
		$this->assertFalse( $detail['source']['available'] );
		$this->assertStringNotContainsString( 'Fictieve tekst', wp_json_encode( $detail ) );
		$this->assertSame( 'done', Followup::save( $id, array_replace( $action, [ 'status' => 'done' ] ) )['status'] );
		wp_delete_post( $id, true );
		$this->assertWPError( Followup::detail( $id, $v, $action['id'] ) );
	}

	public function test_rule_runs_are_bounded_idempotent_and_do_not_reopen_decisions(): void {
		$owner      = $this->manager();
		[ $id, $v ] = $this->fixture( array_fill( 0, 105, 'no' ) );
		$rule       = $this->rule( $id, $v );
		$first      = Followup::run_rule( $id, $rule );
		$this->assertSame( 100, $first['created'] );
		$this->assertSame( 100, $first['processed'] );
		$this->assertIsInt( $first['next'] );
		$this->assertSame( 0, Followup::run_rule( $id, $rule )['created'] );
		$last = Followup::run_rule( $id, $rule + [ 'after' => $first['next'] ] );
		$this->assertSame( 5, $last['created'] );
		$this->assertNull( $last['next'] );
		$proposal = Followup::listing(
			$id,
			[
				'version' => $v,
				'status'  => 'proposed',
			]
			)['rows'][0];
		$this->assertWPError( Followup::save( $id, array_replace( $proposal, [ 'status' => 'open' ] ) ) );
		$accepted = Followup::save(
			$id,
			array_replace(
			$proposal,
			[
				'owner'  => $owner,
				'due'    => '2027-01-01',
				'status' => 'open',
			]
			)
			);
		$done     = Followup::save( $id, array_replace( $accepted, [ 'status' => 'done' ] ) );
		Followup::run_rule( $id, $rule + [ 'after' => $first['next'] ] );
		$this->assertSame( 'done', Followup::detail( $id, $v, $done['id'] )['status'] );
		$this->assertSame( 105, Followup::listing( $id, [ 'version' => $v ] )['total'] );
		$this->assertCount( 25, Followup::listing( $id, [ 'version' => $v ] )['rows'] );
	}

	public function test_rule_versions_deactivation_rejection_and_missing_sources(): void {
		$this->manager();
		[ $id, $v ] = $this->fixture();
		$rule       = $this->rule( $id, $v );
		Followup::run_rule( $id, $rule );
		$proposal = Followup::listing( $id, [ 'version' => $v ] )['rows'][0];
		$this->assertWPError( Followup::save( $id, array_replace( $proposal, [ 'status' => 'dismissed' ] ) ) );
		$dismissed = Followup::save(
			$id,
			array_replace(
			$proposal,
			[
				'status' => 'dismissed',
				'reason' => 'Geen aanvullende opvolging nodig',
			]
			)
			);
		$this->assertSame( 'dismissed', $dismissed['status'] );
		$this->assertSame( 0, Followup::run_rule( $id, $rule )['created'] );
		$disabled = Followup::save_rule( $id, array_replace( $rule, $rule['predicate'], [ 'active' => false ] ) );
		$this->assertSame( 1, $disabled['rule_version'] );
		$this->assertWPError( Followup::run_rule( $id, $rule ) );
		$this->assertWPError( Followup::run_rule( $id, $disabled ) );
		$updated = Followup::save_rule(
			$id,
			array_replace(
			$disabled,
			$rule['predicate'],
			[
				'active'  => true,
				'options' => [ 'yes' ],
			]
			)
			);
		$this->assertSame( 2, $updated['rule_version'] );
		$this->assertSame( 1, Followup::run_rule( $id, $updated )['created'] );
		$new = Followup::listing(
			$id,
			[
				'version' => $v,
				'status'  => 'proposed',
			]
			)['rows'][0];
		wp_delete_post( $new['source']['id'], true );
		$this->assertWPError(
			Followup::save(
			$id,
			array_replace(
			$new,
			[
				'status' => 'open',
				'owner'  => get_current_user_id(),
				'due'    => '2027-01-01',
			]
			)
			)
			);
		$this->assertSame(
			'dismissed',
			Followup::save(
			$id,
			array_replace(
			$new,
			[
				'status' => 'dismissed',
				'reason' => 'Bron verwijderd',
			]
			)
			)['status']
			);
	}

	public function test_only_confirmed_themes_create_suggestions_and_anonymity_is_preserved(): void {
		$this->manager();
		[ $id, $v, $replies ] = $this->fixture();
		$profile              = AnalysisSchema::blank();
		$profile['themes']    = [
			[
				'id'       => 'light',
				'label'    => 'Verlichting',
				'keywords' => [ 'licht' ],
			],
		];
		$config               = Analysis::save_configuration(
			$id,
			[
				'version'  => $v,
				'revision' => 'none',
				'profile'  => $profile,
			]
			);
		$rule                 = Followup::save_rule(
			$id,
			[
				'version' => $v,
				'title'   => 'Verlichting bekijken',
				'active'  => true,
				'kind'    => 'theme',
				'theme'   => 'light',
			]
			);
		$this->assertSame( 0, Followup::run_rule( $id, $rule )['created'] );
		Analysis::label(
			$id,
			[
				'response_id'      => $replies[0],
				'question'         => 'note',
				'themes'           => [ 'light' ],
				'revision'         => hash( 'sha256', '[]' ),
				'profile_revision' => $config['revision'],
			]
			);
		$this->assertSame( 1, Followup::run_rule( $id, $rule )['created'] );
		$rows = Followup::listing( $id, [ 'version' => $v ] );
		$this->assertStringNotContainsString( 'respondent', wp_json_encode( $rows ) );
		$this->assertStringNotContainsString( 'Fictieve tekst', wp_json_encode( $rows ) );
		$this->assertSame( 'proposed', $rows['rows'][0]['status'] );
	}

	public function test_action_routes_require_both_capabilities_even_for_the_owner(): void {
		$owner                = $this->manager();
		[ $id, $v, $replies ] = $this->fixture();
		$action               = Followup::save( $id, $this->draft( $owner, $v, $replies[0] ) );
		$this->bootRestControllers( [ SurveyController::class ] );
		$paths = [ '/analysis/actions', '/analysis/action-detail', '/analysis/rules' ];
		foreach ( [ [ true, false ], [ false, true ], [ false, false ], [ true, true ] ] as [ $manage, $results ] ) {
			$user = new \WP_User( $owner );
			$user->add_cap( 'enquetes', $manage );
			$user->add_cap( 'enquete_resultaten', $results );
			wp_set_current_user( 0 );
			wp_set_current_user( $owner );
			foreach ( $paths as $path ) {
				$r = new \WP_REST_Request( 'GET', '/rondo/v1/surveys/' . $id . $path );
				$r->set_param( 'version', $v );
				$r->set_param( 'action_id', $action['id'] );
				$this->assertSame( $manage && $results ? 200 : 403, rest_do_request( $r )->get_status() );
			}
			$this->assertSame( $manage && $results, in_array( $owner, array_column( Followup::owners(), 'id' ), true ) );
			foreach ( [ '/analysis/actions', '/analysis/rules', '/analysis/rules/run', '/analysis/rounds' ] as $path ) {
				$r = new \WP_REST_Request( 'POST', '/rondo/v1/surveys/' . $id . $path );
				$r->set_header( 'Content-Type', 'application/json' );
				$r->set_body( '{}' );
				$this->assertSame( $manage && $results ? 400 : 403, rest_do_request( $r )->get_status() );
			}
		}
	}

	public function test_rounds_use_explicit_ids_denominators_and_percentage_points(): void {
		$this->manager();
		$a     = $this->fixture( [ 'yes', 'no', 'unknown', null ] );
		$b     = $this->fixture( [ 'yes', 'yes', 'yes', 'no', 'unknown' ], 'next_' );
		$input = $this->comparison( $a, $b, 'next_' );
		$saved = RoundComparison::save( $a[0], $input );
		$this->assertIsArray( $saved );
		$comparison         = RoundComparison::listing( $a[0], $a[1] )[0];
		[ $first, $second ] = $comparison['results'];
		$this->assertSame( 4, $first['total'] );
		$this->assertSame( 2, $first['denominator'] );
		$this->assertSame( 50.0, (float) $first['rate'] );
		$this->assertSame( 75.0, (float) $second['rate'] );
		$this->assertSame( 25.0, $second['delta'] );
		$this->assertSame( 'comparable', $second['assessment'] );
		$this->assertSame( 'Alle leden', $second['audience'] );
		$this->assertTrue( $first['small'] );
		$this->assertSame( 409, RoundComparison::save( $a[0], array_replace( $comparison, [ 'revision' => 'stale' ] ) )->get_error_data()['status'] );
		$this->assertStringNotContainsString( 'Fictieve tekst', wp_json_encode( $comparison ) );
	}

	public function test_changed_scales_options_audiences_and_wording_never_silently_compare(): void {
		$this->manager();
		$a          = $this->fixture();
		$variations = [
			[ $this->fixture( [ 'yes' ], 'b_', 'scale' ), 'incompatible', [] ],
			[
				$this->fixture(
					[ 'yes' ],
					'b_',
					'single',
					[
						'yes'     => 'Goed',
						'no'      => 'Slecht',
						'unknown' => 'Weet ik niet',
						'extra'   => 'Matig',
					]
					),
				'incompatible',
				[],
			],
			[
				$this->fixture(
					[ 'yes' ],
					'b_',
					'single',
					[
						'yes'     => 'Erg goed',
						'no'      => 'Slecht',
						'unknown' => 'Weet ik niet',
					]
					),
				'limited',
				[],
			],
			[ $this->fixture( [ 'yes' ], 'b_' ), 'limited', [ 'audience' => 'Alleen ouders' ] ],
			[
				$this->fixture(
					[ 'yes' ],
					'b_',
					'single',
					[
						'yes'     => 'Goed',
						'no'      => 'Slecht',
						'unknown' => 'Weet ik niet',
					],
					'Hoe zijn de kleedkamers?'
					),
				'limited',
				[],
			],
			[
				$this->fixture( [ 'yes' ], 'b_' ),
				'incompatible',
				[
					'compatibility' => 'incompatible',
					'note'          => 'Andere meetmethode',
				],
			],
		];
		foreach ( $variations as [ $b, $level, $patch ] ) {
			$input              = $this->comparison( $a, $b, 'b_' );
			$input['rounds'][1] = array_replace( $input['rounds'][1], $patch );
			$this->assertIsArray( RoundComparison::save( $a[0], $input ) );
			$rows   = RoundComparison::listing( $a[0], $a[1] );
			$result = end( $rows )['results'][1];
			$this->assertSame( $level, $result['assessment'] );
			$this->assertNull( $result['delta'] );
			$this->assertNotEmpty( $result['reasons'] );
		}
	}

	public function test_rounds_reject_pooled_revisions_foreign_versions_and_bad_mappings(): void {
		$this->manager();
		$a     = $this->fixture();
		$b     = $this->fixture();
		$input = $this->comparison( $a, $b );
		foreach ( [ [ 'survey_id' => $a[0] ], [ 'version' => $a[1] ], [ 'question' => 'note' ], [ 'options' => [ 'missing' ] ], [ 'exclude' => [ 'yes' ] ], [ 'end' => '2025-01-01' ], [ 'compatibility' => 'incompatible' ] ] as $patch ) {
			$bad              = $input;
			$bad['rounds'][1] = array_replace( $bad['rounds'][1], $patch );
			$this->assertWPError( RoundComparison::save( $a[0], $bad ) );
		}
		$this->assertWPError( RoundComparison::save( $b[0], $input ) );
		$this->assertSame( [], Surveys::children( $a[0], RoundComparison::TYPE ) );
	}

	public function test_deleted_rounds_and_zero_answers_do_not_reuse_stale_figures(): void {
		$this->manager();
		$a = $this->fixture( [] );
		$b = $this->fixture( [ 'yes' ] );
		RoundComparison::save( $a[0], $this->comparison( $a, $b ) );
		$before = RoundComparison::listing( $a[0], $a[1] )[0]['results'];
		$this->assertNull( $before[0]['rate'] );
		$this->assertNull( $before[1]['delta'] );
		wp_delete_post( $b[0], true );
		$after = RoundComparison::listing( $a[0], $a[1] )[0]['results'];
		$this->assertFalse( $after[1]['available'] );
		$this->assertArrayNotHasKey( 'rate', $after[1] );
		$this->assertCount( 1, RoundComparison::catalog() );
	}
}
