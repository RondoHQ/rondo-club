<?php
namespace Tests\Wpunit;

use Rondo\Surveys\Analysis;
use Rondo\Surveys\AnalysisSchema;
use Rondo\Surveys\AnalysisReports;
use Rondo\Surveys\Surveys;
use Rondo\Fields\Fields;
use Rondo\REST\SurveyController;
use Tests\Support\RondoTestCase;

class SurveyAnalysisTest extends RondoTestCase {
	private function fixture( string $prefix = '', bool $linked = false ): array {
		$question = static function ( $id, $type, $options = [] ) use ( $prefix ) {
			return [
				'id'       => $prefix . $id,
				'label'    => $prefix . $id,
				'type'     => $type,
				'required' => false,
				'options'  => array_map(
				static fn( $id ) => [
					'id'    => $id,
					'label' => $id,
				],
				$options
			),
			];
		};
		$id       = Surveys::create(
			[
				'title'    => 'Fictieve accommodatie-enquête',
				'document' => [
					'sections' => [
						[
							'id'        => 's',
							'title'     => 'Algemeen',
							'questions' => [
								$question( 'role', 'multiple', [ 'player', 'parent', 'staff' ] ),
								$question( 'rating', 'single', [ 'yes', 'no', 'unknown' ] ),
								$question( 'note', 'text' ),
								$question( 'extra', 'text' ),
							],
						],
					],
				],
			]
			);
		$this->assertIsInt( $id );
		Surveys::update( $id, [ 'linked' => $linked ] );
		Surveys::action( $id, 'publish' );
		$version = Surveys::read( $id )['current_version'];
		$rows    = [
			[
				'role'   => [ 'player', 'parent' ],
				'rating' => 'yes',
				'note'   => 'Meer licht op het veld',
				'extra'  => 'Ook licht in de hal',
			],
			[
				'role'   => [ 'parent' ],
				'rating' => 'no',
				'note'   => 'Een schone kleedkamer',
			],
			[
				'role'   => [ 'staff' ],
				'rating' => 'unknown',
				'note'   => '=HYPERLINK("test")',
			],
			[ 'role' => [ 'player' ] ],
		];
		$ids     = [];
		foreach ( $rows as $answers ) {
			$mapped = [];
			foreach ( $answers as $key => $value ) {
				$mapped[ $prefix . $key ] = $value; }
			$ids[] = Surveys::write(
				Surveys::RESPONSE,
				[
					'version' => $version,
					'answers' => $mapped,
				],
				0,
				$id
				);
		}
		return [ $id, $version, $ids ];
	}

	private function profile( string $prefix = '' ): array {
		$profile                = AnalysisSchema::blank();
		$profile['metrics']     = [
			[
				'id'       => 'happy',
				'label'    => 'Tevreden',
				'question' => $prefix . 'rating',
				'options'  => [ 'yes' ],
				'exclude'  => [ 'unknown' ],
			],
		];
		$profile['comparisons'] = [
			[
				'id'      => 'roles',
				'label'   => 'Naar rol',
				'outcome' => $prefix . 'rating',
				'group'   => $prefix . 'role',
			],
		];
		$profile['themes']      = [
			[
				'id'       => 'light',
				'label'    => 'Verlichting',
				'keywords' => [ 'licht' ],
			],
		];
		$profile['views']       = [
			[
				'id'      => 'parents',
				'label'   => 'Ouders',
				'filters' => [ $prefix . 'role' => [ 'parent' ] ],
			],
		];
		return $profile;
	}

	private function configure( int $id, int $version, ?array $profile = null ): array {
		$config = Analysis::save_configuration(
			$id,
			[
				'version'  => $version,
				'revision' => Analysis::configuration( $id, $version )['revision'],
				'profile'  => $profile ?? $this->profile(),
			]
			);
		$this->assertIsArray( $config );
		return $config;
	}

	private function label( int $id, int $reply, string $question = 'note' ) {
		$row = Surveys::read( $reply, Surveys::RESPONSE );
		return Analysis::label(
			$id,
			[
				'response_id'      => $reply,
				'question'         => $question,
				'themes'           => [ 'light' ],
				'revision'         => hash( 'sha256', wp_json_encode( Analysis::json_field( $reply, 'analysis_labels' ) ) ),
				'profile_revision' => Analysis::configuration( $id, $row['version'] )['revision'],
			]
			);
	}

	public function test_selection_percentages_and_cross_tabs_share_denominators(): void {
		[ $id, $version ] = $this->fixture();
		$this->configure( $id, $version );
		$data  = Analysis::select( $id );
		$stats = Analysis::aggregate( $data );
		$this->assertSame( 4, $stats['total'] );
		$questions = array_column( $stats['summary'], null, 'id' );
		$this->assertSame( 3, $questions['rating']['answered'] );
		$this->assertSame(
			[
				'yes'     => 1,
				'no'      => 1,
				'unknown' => 1,
			],
			$questions['rating']['counts']
			);
		$this->assertSame( [], $questions['note']['texts'] );
		$this->assertSame( 1, $stats['metrics'][0]['count'] );
		$this->assertSame( 2, $stats['metrics'][0]['denominator'] );
		$this->assertEquals( 50, $stats['metrics'][0]['rate'] );
		$groups = array_column( $stats['comparisons'][0]['groups'], null, 'id' );
		$this->assertSame( 2, $groups['player']['total'] );
		$this->assertSame( 1, $groups['player']['answered'] );
		$this->assertSame( 2, $groups['parent']['answered'] );
		$this->assertTrue( $groups['parent']['small'] );
		$this->assertSame( 5, array_sum( array_column( $groups, 'total' ) ) );
		$filtered = Analysis::select(
			$id,
			[
				'filters' => [
					'role'   => [ 'parent', 'staff' ],
					'rating' => [ 'yes', 'no' ],
				],
			]
			);
		$this->assertCount( 2, $filtered['rows'] );
		$this->assertCount( 2, Analysis::select( $id, [ 'view' => 'parents' ] )['rows'] );
		$this->assertCount(
			1,
			Analysis::select(
			$id,
			[
				'question' => 'rating',
				'value'    => 'yes',
			]
			)['rows']
			);
		$this->assertWPError( Analysis::select( $id, [ 'filters' => [ 'rating' => [ 'missing' ] ] ] ) );
		$this->assertWPError( Analysis::select( $id, [ 'filters' => 'not-json' ] ) );
		$this->assertWPError( Analysis::select( $id, [ 'view' => 'missing' ] ) );
	}

	public function test_empty_selection_and_unresolved_template_mappings(): void {
		[ $id, $version ] = $this->fixture();
		$this->configure( $id, $version );
		$data  = Analysis::select(
			$id,
			[
				'filters' => [
					'role'   => [ 'staff' ],
					'rating' => [ 'yes' ],
				],
			]
			);
		$stats = Analysis::aggregate( $data );
		$this->assertSame( 0, $stats['total'] );
		$this->assertNull( $stats['metrics'][0]['rate'] );
		$this->assertSame( 0, $stats['metrics'][0]['denominator'] );
		$this->configure( $id, $version, AnalysisSchema::templates()[1]['profile'] );
		$stats = Analysis::aggregate( Analysis::select( $id ) );
		$this->assertNotEmpty( $stats['metrics'][0]['error'] );
		$this->assertNull( $stats['metrics'][0]['rate'] );
		$this->assertNotEmpty( $stats['comparisons'][0]['error'] );
	}

	public function test_profiles_work_with_unrelated_question_ids_and_are_copied(): void {
		[ $id, $version ] = $this->fixture( 'building_' );
		$config           = $this->configure( $id, $version, $this->profile( 'building_' ) );
		$this->assertEquals( 50, Analysis::aggregate( Analysis::select( $id ) )['metrics'][0]['rate'] );
		$template = Analysis::save_template( [ 'profile' => $config['profile'] ] );
		$this->assertIsInt( $template );
		$profile                   = $config['profile'];
		$profile['source_profile'] = [
			'id'      => $template,
			'version' => 1,
		];
		$this->configure( $id, $version, $profile );
		$profile['title'] = 'Nieuwe templateversie';
		Analysis::save_template( [ 'profile' => $profile ] );
		$this->assertSame( $config['profile']['title'], Analysis::configuration( $id, $version )['profile']['title'] );
		$this->assertSame(
			[
				'id'      => $template,
				'version' => 1,
			],
			Analysis::configuration( $id, $version )['profile']['source_profile']
			);
		$this->assertNotEmpty( get_post_meta( $id, '_analysis_profiles', true ) );
		$this->assertSame( 'field_rondo_survey_analysis_profiles', get_post_meta( $id, '__analysis_profiles', true ) );
		$this->assertFalse( get_post_type_object( Analysis::PROFILE )->show_in_rest );
		$this->assertFalse( get_post_type_object( Analysis::REPORT )->can_export );
	}

	public function test_profile_revisions_and_publication_versions_are_isolated(): void {
		[ $id, $version ] = $this->fixture();
		$this->configure( $id, $version );
		$this->assertWPError(
			Analysis::save_configuration(
			$id,
			[
				'version'  => $version,
				'revision' => 'none',
				'profile'  => $this->profile(),
			]
			)
			);
		Surveys::action( $id, 'publish' );
		$current = Surveys::read( $id )['current_version'];
		$this->assertNotSame( $version, $current );
		$this->assertSame( 'none', Analysis::configuration( $id, $current )['revision'] );
		$this->assertSame( 0, Analysis::aggregate( Analysis::select( $id ) )['total'] );
		$this->assertSame( 4, Analysis::aggregate( Analysis::select( $id, [ 'version' => $version ] ) )['total'] );
		[ $foreign, $other_version ] = $this->fixture();
		$this->assertWPError( Analysis::select( $id, [ 'version' => $other_version ] ) );
		$this->assertWPError(
			Analysis::save_configuration(
			$foreign,
			[
				'version'  => $version,
				'revision' => 'none',
				'profile'  => $this->profile(),
			]
			)
			);
	}

	public function test_suggestions_require_confirmation_and_theme_counts_deduplicate_submissions(): void {
		[ $id, $version, $replies ] = $this->fixture();
		$this->configure( $id, $version );
		$data  = Analysis::select( $id );
		$texts = Analysis::text_page( $data, 1 );
		$this->assertSame( 4, $texts['total'] );
		$this->assertSame(
			[
				[
					'theme' => 'light',
					'words' => [ 'licht' ],
				],
			],
			$texts['items'][0]['suggestions']
			);
		$this->assertSame( 0, Analysis::aggregate( $data )['themes'][0]['count'] );
		$this->assertIsArray( $this->label( $id, $replies[0] ) );
		$this->assertIsArray( $this->label( $id, $replies[0], 'extra' ) );
		$data = Analysis::select( $id );
		$this->assertSame( 1, Analysis::aggregate( $data )['themes'][0]['count'] );
		$this->assertSame( [], Analysis::text_page( $data, 1 )['items'][0]['suggestions'] );
		$this->assertCount( 1, Analysis::select( $id, [ 'theme' => 'light' ] )['rows'] );
		$this->assertSame( 2, Analysis::text_page( Analysis::select( $id, [ 'theme' => 'light' ] ), 1 )['total'] );
		$this->assertSame(
			1,
			Analysis::text_page(
			Analysis::select(
			$id,
			[
				'search'        => 'LICHT',
				'text_question' => 'extra',
			]
			),
			1
			)['total']
			);
		$this->assertWPError(
			Analysis::label(
			$id,
			[
				'response_id' => $replies[0],
				'question'    => 'note',
				'themes'      => [],
				'revision'    => 'stale',
			]
			)
			);
		$this->assertWPError(
			Analysis::label(
			$id,
			[
				'response_id' => $replies[0],
				'question'    => 'rating',
				'themes'      => [ 'light' ],
			]
			)
			);
		[ $other ] = $this->fixture();
		$this->assertWPError( $this->label( $other, $replies[0] ) );
		wp_delete_post( $replies[0], true );
		$this->assertSame( 0, Analysis::aggregate( Analysis::select( $id ) )['themes'][0]['count'] );
	}

	public function test_csv_and_text_pages_use_the_identical_selection(): void {
		[ $id, $version ] = $this->fixture();
		$this->configure( $id, $version );
		$r            = new \WP_REST_Request( 'GET' );
		$r['id']      = $id;
		$r['filters'] = wp_json_encode(
			[
				'role'   => [ 'parent' ],
				'rating' => [ 'yes' ],
			]
			);
		$controller   = new SurveyController();
		$this->assertSame( 1, $controller->analysis( $r )['total'] );
		$this->assertSame( 2, $controller->analysis_texts( $r )['total'] );
		$csv = $controller->export( $r )['csv'];
		$this->assertStringContainsString( 'Meer licht', $csv );
		$this->assertStringNotContainsString( 'schone', $csv );
		$this->assertCount( 2, array_filter( explode( "\r\n", $csv ) ) );
		$r['filters'] = '{}';
		$r['search']  = 'HYPERLINK';
		$this->assertStringContainsString( "'=HYPERLINK", $controller->export( $r )['csv'] );
	}

	public function test_invitation_response_uses_confirmed_unique_sends_and_separates_public_submissions(): void {
		[ $id, $version, $replies ] = $this->fixture();
		foreach ( [ 'sent', 'reminder_failed', 'failed', 'prepared' ] as $index => $status ) {
			$invite = Surveys::write(
				Surveys::INVITE,
				[
					'version'   => $version,
					'status'    => $status,
					'sent_at'   => $index < 2 ? '2026-11-14T12:00:00Z' : '',
					'completed' => $index !== 1,
				],
				0,
				$id
				);
			if ( $index !== 1 ) {
				add_option( 'rondo_survey_submit_' . hash( 'sha256', $id . ':invite:' . $invite ), $replies[ $index ], '', false ); }
		}
		$response = Analysis::select( $id )['response'];
		$this->assertSame( 2, $response['sent'] );
		$this->assertSame( 1, $response['completed'] );
		$this->assertEquals( 50, $response['rate'] );
		$this->assertSame( 3, $response['invited_submissions'] );
		$this->assertSame( 1, $response['public_submissions'] );
		$this->assertArrayNotHasKey( 'email', $response );
		[ $public ] = $this->fixture();
		$this->assertNull( Analysis::select( $public )['response']['rate'] );
	}

	public function test_report_snapshot_is_immutable_and_deleted_answers_are_not_copied(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		[ $id, $version, $replies ] = $this->fixture();
		$this->configure( $id, $version );
		$data   = Analysis::select( $id );
		$input  = [
			'version'          => $version,
			'title'            => 'Meer aandacht nodig',
			'text'             => 'Bekijk de verlichting.',
			'profile_revision' => $data['configuration']['revision'],
			'source_revision'  => $data['source_revision'],
			'selection'        => $data['selection'],
			'references'       => [
				[
					'type' => 'response',
					'id'   => $replies[0],
				],
				[
					'type' => 'question',
					'id'   => 'rating',
				],
				[
					'type' => 'theme',
					'id'   => 'light',
				],
			],
		];
		$report = AnalysisReports::create( $id, $input );
		$this->assertIsInt( $report );
		$stored = get_post_field( 'post_content', $report );
		$this->assertStringNotContainsString( 'Meer licht op het veld', $stored );
		$this->assertSame( 4, Surveys::read( $report, Analysis::REPORT )['aggregates']['total'] );
		Surveys::write(
			Surveys::RESPONSE,
			[
				'version' => $version,
				'answers' => [ 'rating' => 'no' ],
			],
			0,
			$id
			);
		$this->assertTrue( AnalysisReports::listing( Analysis::select( $id ) )[0]['new_responses'] );
		$this->assertSame( $stored, get_post_field( 'post_content', $report ) );
		$this->assertWPError( AnalysisReports::create( $id, $input ) );
		$data                     = Analysis::select( $id );
		$input['source_revision'] = $data['source_revision'];
		$input['previous']        = $report;
		$this->assertIsInt( AnalysisReports::create( $id, $input ) );
		$this->assertSame( $stored, get_post_field( 'post_content', $report ) );
		wp_delete_post( $replies[0], true );
		$report_data = AnalysisReports::listing( Analysis::select( $id ) )[0];
		$this->assertFalse( $report_data['references'][0]['available'] );
		$r                = new \WP_REST_Request();
		$r['id']          = $id;
		$r['response_id'] = $replies[0];
		$this->assertWPError( ( new SurveyController() )->analysis_source( $r ) );
		wp_delete_post( $id, true );
		$this->assertWPError( Analysis::select( $id ) );
	}

	public function test_foreign_report_sources_and_corrections_are_rejected(): void {
		[ $id, $version ]      = $this->fixture();
		[ $other, , $replies ] = $this->fixture();
		$data                  = Analysis::select( $id );
		$input                 = [
			'version'          => $version,
			'title'            => 'Test',
			'text'             => 'Test',
			'profile_revision' => $data['configuration']['revision'],
			'source_revision'  => $data['source_revision'],
			'references'       => [
				[
					'type' => 'response',
					'id'   => $replies[0],
				],
			],
		];
		$this->assertWPError( AnalysisReports::create( $id, $input ) );
		$input['references'] = [];
		$input['previous']   = $other;
		$this->assertWPError( AnalysisReports::create( $id, $input ) );
	}

	public function test_permissions_protect_all_analysis_routes_and_anonymous_identity(): void {
		[ $id, $version, $replies ] = $this->fixture();
		// A defensive boundary even when older data accidentally contains a respondent.
		$row               = Surveys::read( $replies[0], Surveys::RESPONSE );
		$row['respondent'] = [
			'email'     => 'private@example.org',
			'person_id' => 123,
		];
		Surveys::write( Surveys::RESPONSE, $row, $replies[0], $id );
		$server = $this->bootRestControllers( [ SurveyController::class ] );
		$reader = self::factory()->user->create( [ 'role' => 'rondo_user' ] );
		$user   = get_user_by( 'id', $reader );
		foreach ( [ [], [ 'enquetes' ], [ 'vrijwilligers' ], [ 'enquete_resultaten' ], [ 'enquetes', 'enquete_resultaten' ] ] as $caps ) {
			foreach ( [ 'enquetes', 'enquete_resultaten', 'vrijwilligers' ] as $cap ) {
				$user->remove_cap( $cap ); }
			foreach ( $caps as $cap ) {
				$user->add_cap( $cap ); }
			wp_set_current_user( 0 );
			wp_set_current_user( $reader );
			$can_read  = in_array( 'enquete_resultaten', $caps, true );
			$can_write = $can_read && in_array( 'enquetes', $caps, true );
			foreach ( [ '/analysis', '/analysis/texts', '/analysis/reports', '/analysis/source', '/analysis/submissions', '/export' ] as $path ) {
				$r                = new \WP_REST_Request( 'GET', '/rondo/v1/surveys/' . $id . $path );
				$r['response_id'] = $replies[0];
				$response         = $server->dispatch( $r );
				$this->assertSame( $can_read ? 200 : 403, $response->get_status(), $path );
				$this->assertStringNotContainsString( 'private@example.org', wp_json_encode( $response->get_data() ) );
			}
			foreach ( [ [ 'PUT', '/analysis' ], [ 'PUT', '/analysis/labels' ], [ 'POST', '/analysis/reports' ] ] as [ $method, $path ] ) {
				$r = new \WP_REST_Request( $method, '/rondo/v1/surveys/' . $id . $path );
				$this->assertSame( $can_write ? ( $path === '/analysis/labels' ? 404 : 400 ) : 403, $server->dispatch( $r )->get_status(), $path );
			}
		}
		wp_set_current_user( 0 );
		$this->assertSame( 401, $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/surveys/' . $id . '/analysis' ) )->get_status() );
	}

	public function test_structural_validation_and_duplicate_options(): void {
		foreach ( [
			null,
			[ 'title' => [] ],
			[
				'title'  => 'Test',
				'themes' => [
					[
						'id'       => 't',
						'label'    => 'x',
						'keywords' => [ [] ],
					],
				],
			],
			[
				'title'   => 'Test',
				'metrics' => [
					[
						'id'       => 'm',
						'label'    => 'm',
						'question' => 'q',
						'options'  => [ 'yes' ],
						'exclude'  => [ 'yes' ],
					],
				],
			],
		] as $input ) {
			$this->assertWPError( AnalysisSchema::profile( $input ) );
		}
		[ $id ]  = $this->fixture();
		$data    = Analysis::select( $id );
		$summary = Analysis::summary( $data['questions'], [ [ 'answers' => [ 'role' => [ 'player', 'player', 'parent' ] ] ] ] );
		$this->assertSame( 1, $summary[0]['counts']['player'] );
		$this->assertSame( 1, $summary[0]['answered'] );
	}

	public function test_text_pagination_is_bounded_and_statistics_do_not_copy_texts(): void {
		[ $id, $version ] = $this->fixture();
		for ( $i = 0; $i < 30; ++$i ) {
			Surveys::write(
			Surveys::RESPONSE,
			[
				'version' => $version,
				'answers' => [ 'note' => 'Fictief antwoord ' . $i ],
			],
			0,
			$id
			); }
		$data = Analysis::select( $id );
		$this->assertCount( 25, Analysis::text_page( $data, 1 )['items'] );
		$this->assertCount( 9, Analysis::text_page( $data, 2 )['items'] );
		$this->assertSame( 34, Analysis::text_page( $data, 1 )['total'] );
		$this->assertStringNotContainsString( 'Fictief antwoord', wp_json_encode( Analysis::aggregate( $data ) ) );
	}

	public function test_unmapped_views_conflicts_and_native_label_storage(): void {
		[ $id, $version, $replies ]      = $this->fixture();
		$profile                         = $this->profile();
		$profile['views'][0]['unmapped'] = true;
		$config                          = $this->configure( $id, $version, $profile );
		$this->assertWPError( Analysis::select( $id, [ 'view' => 'parents' ] ) );
		$result = Surveys::locked(
			'survey:' . $id,
			static fn() => Analysis::save_configuration(
			$id,
			[
				'version'  => $version,
				'revision' => $config['revision'],
				'profile'  => $profile,
			]
			)
			);
		$this->assertSame( 409, $result->get_error_data()['status'] );
		$this->assertSame( $config['revision'], Analysis::configuration( $id, $version )['revision'] );
		$this->assertIsArray( $this->label( $id, $replies[0] ) );
		$this->assertSame( [ 'note' => [ 'light' ] ], json_decode( get_post_meta( $replies[0], '_analysis_labels', true ), true ) );
		$this->assertSame( 'field_rondo_survey_reply_analysis_labels', get_post_meta( $replies[0], '__analysis_labels', true ) );
		$profile['views'][0]['unmapped'] = false;
		$this->configure( $id, $version, $profile );
		$this->assertCount( 2, Analysis::select( $id, [ 'view' => 'parents' ] )['rows'] );
	}

	public function test_rest_profile_write_and_permission_revocation(): void {
		[ $id, $version, $replies ] = $this->fixture();
		$server                     = $this->bootRestControllers( [ SurveyController::class ] );
		$uid                        = self::factory()->user->create( [ 'role' => 'rondo_user' ] );
		$user                       = get_user_by( 'id', $uid );
		$user->add_cap( 'enquetes' );
		$user->add_cap( 'enquete_resultaten' );
		wp_set_current_user( $uid );
		$request = new \WP_REST_Request( 'PUT', '/rondo/v1/surveys/' . $id . '/analysis' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
			[
				'version'  => $version,
				'revision' => 'none',
				'profile'  => $this->profile(),
			]
			)
			);
		$this->assertSame( 200, $server->dispatch( $request )->get_status() );
		$this->assertSame( 409, $server->dispatch( $request )->get_status() );
		$user->remove_cap( 'enquete_resultaten' );
		wp_set_current_user( 0 );
		wp_set_current_user( $uid );
		$this->assertSame( 403, $server->dispatch( $request )->get_status() );
		foreach ( [ '/surveys/analysis-profiles', '/surveys/' . $id . '/analysis/source', '/surveys/' . $id . '/analysis/reports' ] as $path ) {
			$read                = new \WP_REST_Request( 'GET', '/rondo/v1' . $path );
			$read['response_id'] = $replies[0];
			$this->assertSame( 403, $server->dispatch( $read )->get_status() );
		}
	}

	public function test_benchmark_five_thousand_synthetic_submissions(): void {
		if ( ! getenv( 'RONDO_ANALYSIS_BENCHMARK' ) ) {
			$this->markTestSkipped( 'Opt-in dataset benchmark.' ); }
		[ $id, $version ] = $this->fixture();
		$this->configure( $id, $version );
		for ( $i = 4; $i < 5000; ++$i ) {
			Surveys::write(
			Surveys::RESPONSE,
			[
				'version' => $version,
				'answers' => [
					'role'   => [ 'parent', 'staff' ],
					'rating' => $i % 2 ? 'yes' : 'no',
					'note'   => str_repeat( 'Fictief antwoord. ', 20 ),
				],
			],
			0,
			$id
			); }
		$start = microtime( true );
		$data  = Analysis::select( $id );
		$stats = Analysis::aggregate( $data );
		$this->assertSame( 5000, $stats['total'] );
		$elapsed = microtime( true ) - $start;
		$bytes   = strlen( wp_json_encode( $stats ) );
		$this->assertLessThan( 100000, $bytes );
		$this->assertCount( 25, Analysis::text_page( $data, 1 )['items'] );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI-only benchmark output.
		fwrite( STDERR, sprintf( "\nAnalysis benchmark: 5000 submissions, %.3fs, %d aggregate bytes\n", $elapsed, $bytes ) );
	}
}
