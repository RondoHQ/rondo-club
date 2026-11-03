<?php
namespace Tests\Wpunit;

use Rondo\Surveys\Surveys;
use Rondo\Surveys\Schema;
use Rondo\Surveys\VolunteerTemplate;
use Rondo\REST\SurveyController;
use Tests\Support\RondoTestCase;

class SurveysTest extends RondoTestCase {
	private function survey( array $settings = [] ): int {
		$input = VolunteerTemplate::data();
		$id    = Surveys::create( $input );
		$this->assertIsInt( $id );
		$this->assertSame( $id, Surveys::update( $id, $settings + $input ) );
		$this->assertSame( $id, Surveys::action( $id, 'publish' ) );
		return $id;
	}

	private function answers( string $route = 'none' ): array {
		return [
			'roles'        => [ 'o0', 'o1' ],
			'satisfaction' => 'o3',
			'welcome'      => 'o4',
			'contribute'   => 'o0',
			'expectations' => 'o3',
			'account'      => 'easy',
			'route'        => $route,
		];
	}

	private function submit( int $id, array $answers, string $token = '' ) {
		$data = Surveys::public_data( $id, $token );
		$this->assertIsArray( $data );
		return Surveys::submit(
			$id,
			[
				'version'   => $data['version'],
				'challenge' => $data['challenge'],
				'answers'   => $answers,
			],
			$token
			);
	}

	public function test_template_supports_all_four_routes_and_discards_hidden_answers(): void {
		$doc = Schema::validate( VolunteerTemplate::data()['document'] );
		$this->assertIsArray( $doc );
		foreach ( [ 'done', 'planned', 'other', 'none' ] as $route ) {
			$clean = Schema::answers(
				$doc,
				$this->answers( $route ) + [
					'done_note'    => 'done',
					'signup'       => 'planned',
					'other_note'   => 'other',
					'motivation'   => 'none',
					'account_note' => 'hidden',
				]
				);
			$this->assertIsArray( $clean );
			$this->assertArrayNotHasKey( 'account_note', $clean );
			foreach ( [
				'done_note'  => 'done',
				'signup'     => 'planned',
				'other_note' => 'other',
				'motivation' => 'none',
			] as $question => $expected_route ) {
				$this->assertSame( $expected_route === $route, array_key_exists( $question, $clean ) );
			}
		}
	}

	public function test_invalid_options_required_answers_and_cycles_are_rejected(): void {
		$doc = Schema::validate( VolunteerTemplate::data()['document'] );
		$this->assertWPError( Schema::answers( $doc, [] ) );
		$this->assertWPError( Schema::answers( $doc, array_merge( $this->answers(), [ 'roles' => [ 'invalid' ] ] ) ) );
		$this->assertWPError( Schema::answers( $doc, array_merge( $this->answers(), [ 'account' => [ 'easy' ] ] ) ) );
		$doc['sections'][0]['condition'] = [
			'question' => 'route',
			'value'    => 'done',
		];
		$this->assertWPError( Schema::validate( $doc ) );
		$doc['sections'][0]['condition'] = [
			'question' => [],
			'value'    => [],
		];
		$this->assertWPError( Schema::validate( $doc ) );
	}

	public function test_hidden_required_question_does_not_block_submission(): void {
		$doc = Schema::validate( VolunteerTemplate::data()['document'] );
		$doc['sections'][0]['questions'][7]['required'] = true;
		$this->assertIsArray( Schema::answers( $doc, $this->answers() ) );
		$this->assertWPError( Schema::answers( $doc, array_merge( $this->answers(), [ 'account' => 'untried' ] ) ) );
	}

	public function test_published_version_is_immutable_and_stale_public_forms_are_rejected(): void {
		$id  = $this->survey();
		$old = Surveys::public_data( $id );
		Surveys::update(
			$id,
			[
				'title'  => 'Nieuwe titel',
				'thanks' => 'Nieuwe tekst',
			]
			);
		$this->assertSame( $old['title'], Surveys::public_data( $id )['title'] );
		Surveys::action( $id, 'publish' );
		$new = Surveys::public_data( $id );
		$this->assertNotSame( $old['version'], $new['version'] );
		$this->assertSame( 'Nieuwe titel', $new['title'] );
		$this->assertWPError(
			Surveys::submit(
			$id,
			[
				'version'   => $old['version'],
				'challenge' => $old['challenge'],
				'answers'   => $this->answers(),
			]
			)
			);
		$this->assertSame( $old['title'], Surveys::read( $old['version'], Surveys::VERSION )['title'] );
	}

	public function test_personal_link_is_single_use_and_unlinked_reply_has_no_identity(): void {
		$id  = $this->survey( [ 'access' => 'invitation' ] );
		$ids = Surveys::prepare(
			$id,
			[
				[
					'name'  => 'Jan',
					'email' => 'jan@example.org',
				],
			]
			);
		$i   = Surveys::read( $ids[0], Surveys::INVITE );
		$this->assertWPError( Surveys::public_data( $id ) );
		$this->assertWPError( Surveys::public_data( $id, str_repeat( '0', 64 ) ) );
		$this->assertTrue( $this->submit( $id, $this->answers(), $i['token'] )['submitted'] );
		$this->assertWPError( Surveys::public_data( $id, $i['token'] ) );
		$rows = Surveys::children( $id, Surveys::RESPONSE );
		$this->assertCount( 1, $rows );
		$reply = Surveys::read( $rows[0]->ID, Surveys::RESPONSE );
		$this->assertArrayNotHasKey( 'respondent', $reply );
		$this->assertStringNotContainsString( 'jan@example.org', $rows[0]->post_content );
		$this->assertTrue( Surveys::read( $ids[0], Surveys::INVITE )['completed'] );
	}

	public function test_invitation_pins_its_original_version_and_linked_mode_is_explicit(): void {
		$id  = $this->survey( [ 'linked' => true ] );
		$ids = Surveys::prepare(
			$id,
			[
				[
					'name'  => 'Jan',
					'email' => 'jan@example.org',
				],
			]
			);
		$i   = Surveys::read( $ids[0], Surveys::INVITE );
		Surveys::update( $id, [ 'title' => 'Ander onderzoek' ] );
		Surveys::action( $id, 'publish' );
		$this->assertSame( $i['version'], Surveys::public_data( $id, $i['token'] )['version'] );
		$this->assertTrue( $this->submit( $id, $this->answers(), $i['token'] )['submitted'] );
		$reply = Surveys::read( Surveys::children( $id, Surveys::RESPONSE )[0]->ID, Surveys::RESPONSE );
		$this->assertSame( 'jan@example.org', $reply['respondent']['email'] );
		$this->assertWPError( Surveys::update( $id, [ 'linked' => false ] ) );
		$this->assertWPError( Surveys::update( $id, [ 'access' => 'invitation' ] ) );
	}

	public function test_replay_close_and_expiry_are_enforced_server_side(): void {
		$id    = $this->survey();
		$data  = Surveys::public_data( $id );
		$input = [
			'version'   => $data['version'],
			'challenge' => $data['challenge'],
			'answers'   => $this->answers(),
		];
		$this->assertTrue( Surveys::submit( $id, $input )['submitted'] );
		$this->assertWPError( Surveys::submit( $id, $input ) );
		$this->assertCount( 1, Surveys::children( $id, Surveys::RESPONSE ) );
		Surveys::update( $id, [ 'closes_at' => '2020-01-01T00:00:00Z' ] );
		$this->assertWPError( Surveys::public_data( $id ) );
		$this->assertWPError( Surveys::submit( $id, $input ) );
		Surveys::action( $id, 'close' );
		$this->assertWPError( Surveys::public_data( $id ) );
	}

	public function test_challenge_and_honeypot_are_checked(): void {
		$id    = $this->survey();
		$data  = Surveys::public_data( $id );
		$input = [
			'version'   => $data['version'],
			'challenge' => $data['challenge'],
			'answers'   => $this->answers(),
		];
		$this->assertWPError( Surveys::submit( $id, $input + [ 'website' => 'spam' ] ) );
		$input['challenge'] .= 'tamper';
		$this->assertWPError( Surveys::submit( $id, $input ) );
		$this->assertCount( 0, Surveys::children( $id, Surveys::RESPONSE ) );
	}

	public function test_duplicate_is_a_clean_draft_without_responses_or_invites(): void {
		$id = $this->survey();
		$this->submit( $id, $this->answers() );
		Surveys::prepare( $id, [ [ 'email' => 'test@example.org' ] ] );
		$copy = Surveys::action( $id, 'duplicate' );
		$s    = Surveys::read( $copy );
		$this->assertSame( 'draft', $s['status'] );
		$this->assertSame( 0, $s['current_version'] );
		$this->assertCount( 0, Surveys::children( $copy, Surveys::RESPONSE ) );
		$this->assertCount( 0, Surveys::children( $copy, Surveys::INVITE ) );
		$this->assertSame( Surveys::read( $id )['document'], $s['document'] );
	}

	public function test_invitation_preparation_deduplicates_and_sends_nothing(): void {
		$id        = $this->survey();
		$sent      = 0;
		$intercept = static function () use ( &$sent ) {
			++$sent;
			return true;
		};
		add_filter( 'pre_wp_mail', $intercept );
		try {
			$ids = Surveys::prepare( $id, [ [ 'email' => 'TEST@example.org' ], [ 'email' => 'test@example.org' ] ] );
			$this->assertCount( 1, $ids );
			$this->assertSame( $ids, Surveys::prepare( $id, [ [ 'email' => 'test@example.org' ] ] ) );
			Surveys::deliver();
			$this->assertSame( 0, $sent );
			$this->assertSame( 1, Surveys::queue( $id )['queued'] );
			Surveys::deliver();
			Surveys::deliver();
			$this->assertSame( 1, $sent );
			$this->assertSame( 'sent', Surveys::read( $ids[0], Surveys::INVITE )['status'] );
		} finally {
			remove_filter( 'pre_wp_mail', $intercept );
		}
	}

	public function test_only_incomplete_invites_receive_one_reminder(): void {
		$id  = $this->survey();
		$ids = Surveys::prepare( $id, [ [ 'email' => 'one@example.org' ], [ 'email' => 'two@example.org' ] ] );
		$i   = Surveys::read( $ids[0], Surveys::INVITE );
		$this->submit( $id, $this->answers(), $i['token'] );
		$sent      = 0;
		$intercept = static function () use ( &$sent ) {
			++$sent;
			return true;
		};
		add_filter( 'pre_wp_mail', $intercept );
		try {
			$this->assertSame( 1, Surveys::queue( $id )['queued'] );
			Surveys::deliver();
			$this->assertSame( 1, Surveys::queue( $id, true )['queued'] );
			Surveys::deliver();
			$this->assertSame( 0, Surveys::queue( $id, true )['queued'] );
			Surveys::deliver();
			$this->assertSame( 2, $sent );
		} finally {
			remove_filter( 'pre_wp_mail', $intercept );
		}
	}

	public function test_rest_permissions_separate_editor_results_and_public_access(): void {
		$id     = $this->survey();
		$server = $this->bootRestControllers( [ SurveyController::class ] );
		wp_set_current_user( 0 );
		$this->assertSame( 401, $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/surveys' ) )->get_status() );
		$this->assertSame( 200, $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/public-surveys/' . $id ) )->get_status() );
		$editor = self::factory()->user->create( [ 'role' => 'rondo_user' ] );
		get_user_by( 'id', $editor )->add_cap( 'enquetes' );
		wp_set_current_user( $editor );
		$this->assertSame( 200, $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/surveys/' . $id ) )->get_status() );
		$this->assertSame( 403, $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/surveys/' . $id . '/results' ) )->get_status() );
		$this->assertSame( 403, $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/surveys/' . $id . '/export' ) )->get_status() );
		get_user_by( 'id', $editor )->remove_cap( 'enquetes' );
		get_user_by( 'id', $editor )->add_cap( 'enquete_resultaten' );
		$this->assertSame( 200, $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/surveys/' . $id . '/results' ) )->get_status() );
		$this->assertSame( 403, $server->dispatch( new \WP_REST_Request( 'POST', '/rondo/v1/surveys/' . $id . '/action' ) )->get_status() );
		$this->assertSame( 403, $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/surveys/' . $id . '/invitations' ) )->get_status() );
	}

	public function test_results_use_question_denominators_filter_routes_and_escape_csv(): void {
		$id = $this->survey();
		$this->submit( $id, $this->answers( 'none' ) + [ 'motivation' => '=HYPERLINK("bad")' ] );
		$this->submit( $id, $this->answers( 'done' ) + [ 'done_note' => 'Bedankt' ] );
		$admin = self::factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $admin );
		$controller    = new SurveyController();
		$request       = new \WP_REST_Request( 'GET' );
		$request['id'] = $id;
		$data          = $controller->results( $request );
		$this->assertSame( 2, $data['total'] );
		$questions = array_column( $data['summary'], null, 'id' );
		$this->assertSame( 2, $questions['roles']['answered'] );
		$this->assertSame( 2, $questions['roles']['counts']['o0'] );
		$this->assertSame( 1, $questions['motivation']['answered'] );
		$this->assertStringContainsString( "'=HYPERLINK", $controller->export( $request )['csv'] );
		$request['question'] = 'route';
		$request['value']    = 'none';
		$this->assertSame( 1, $controller->results( $request )['total'] );
		$this->assertStringNotContainsString( 'Bedankt', $controller->export( $request )['csv'] );
	}

	public function test_shift_scheduler_sends_personal_internal_link_once_and_skips_no_shows(): void {
		$id     = $this->survey();
		$person = $this->createPerson(
			[ 'post_title' => 'Anne Test' ],
			[
				'first_name' => 'Anne',
				'email_1'    => 'anne@example.org',
			]
			);
		$absent = $this->createPerson( [], [ 'email_1' => 'absent@example.org' ] );
		$type   = self::factory()->post->create(
			[
				'post_type'   => 'dienst_type',
				'post_status' => 'publish',
			]
			);
		\Rondo\Fields\Fields::update_for_post( $type, 'survey_id', $id );
		update_post_meta( $type, 'survey_url', 'https://example.org/external' );
		$now   = current_datetime();
		$shift = self::factory()->post->create(
			[
				'post_type'   => 'dienst_shift',
				'post_status' => 'publish',
			]
			);
		update_post_meta( $shift, 'dienst_type_id', $type );
		update_post_meta( $shift, 'start_datetime', $now->modify( '-1 day -2 hours' )->format( 'Y-m-d H:i:s' ) );
		update_post_meta( $shift, 'end_datetime', $now->modify( '-1 day' )->format( 'Y-m-d H:i:s' ) );
		update_post_meta( $shift, 'status', 'voltooid' );
		update_post_meta( $shift, 'assigned_persons', [ $person, $absent ] );
		update_post_meta( $shift, '_no_show_' . $absent, true );
		$messages  = [];
		$intercept = static function ( $result, $args ) use ( &$messages ) {
			$messages[] = $args;
			return true;
		};
		add_filter( 'pre_wp_mail', $intercept, 10, 2 );
		try {
			$scheduler = new \Rondo\Volunteer\ShiftEmailScheduler();
			$this->assertSame( 1, $scheduler->run_sweep( $now ) );
			$this->assertSame( 0, $scheduler->run_sweep( $now ) );
			$this->assertCount( 1, $messages );
			$this->assertStringContainsString( '/enquete/' . $id, $messages[0]['message'] );
			$this->assertStringContainsString( 'uitnodiging=', $messages[0]['message'] );
			$this->assertStringNotContainsString( 'example.org/external', $messages[0]['message'] );
			$invites = Surveys::children( $id, Surveys::INVITE );
			$this->assertCount( 1, $invites );
			$this->assertSame( 'sent', Surveys::read( $invites[0]->ID, Surveys::INVITE )['status'] );
		} finally {
			remove_filter( 'pre_wp_mail', $intercept, 10 );
		}
	}

	public function test_stale_editor_revision_cannot_overwrite_another_save(): void {
		$id    = $this->survey();
		$first = Surveys::read( $id );
		$this->assertSame(
			$id,
			Surveys::update(
			$id,
			[
				'title'    => 'Nieuw concept',
				'revision' => $first['revision'],
			]
			)
			);
		$this->assertWPError(
			Surveys::update(
			$id,
			[
				'title'    => 'Verouderd concept',
				'revision' => $first['revision'],
			]
			)
			);
		$this->assertSame( 'Nieuw concept', Surveys::read( $id )['title'] );
	}

	public function test_duty_type_can_be_created_and_survey_cleared_through_rest(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$server  = $this->bootRestControllers( [] );
		$request = new \WP_REST_Request( 'POST', '/wp/v2/dienst-types' );
		$request->set_body_params(
			[
				'title'  => 'Entree zonder enquête',
				'status' => 'publish',
				'fields' => [
					'description' => 'Controleer de toegang.',
					'survey_id'   => 0,
				],
			]
		);
		$response = $server->dispatch( $request );
		$this->assertSame( 201, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$type = $response->get_data()['id'];
		$this->assertSame( 0, $response->get_data()['fields']['survey_id'] );
		$this->assertSame( 'Controleer de toegang.', $response->get_data()['fields']['description'] );

		$survey = $this->survey();
		foreach ( [ $survey, 0 ] as $survey_id ) {
			$request = new \WP_REST_Request( 'POST', '/wp/v2/dienst-types/' . $type );
			$request->set_body_params( [ 'fields' => [ 'survey_id' => $survey_id ] ] );
			$response = $server->dispatch( $request );
			$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
			$this->assertSame( $survey_id, $response->get_data()['fields']['survey_id'] );
			$this->assertSame( (string) $survey_id, get_post_meta( $type, 'survey_id', true ) );
		}
	}

	public function test_shift_invitations_are_per_shift_and_person_and_field_round_trips(): void {
		$id     = $this->survey();
		$person = $this->createPerson();
		$one    = Surveys::shift_invitation( $id, 101, $person, 'test@example.org' );
		$again  = Surveys::shift_invitation( $id, 101, $person, 'test@example.org' );
		$two    = Surveys::shift_invitation( $id, 102, $person, 'test@example.org' );
		$this->assertSame( $one['id'], $again['id'] );
		$this->assertNotSame( $one['id'], $two['id'] );
		$this->assertSame( $person, $one['person_id'] );
		$type = self::factory()->post->create(
			[
				'post_type'   => 'dienst_type',
				'post_status' => 'publish',
			]
			);
		\Rondo\Fields\Fields::update_for_post( $type, 'survey_id', $id );
		$this->assertSame( $id, (int) \Rondo\Fields\Fields::get_for_post( $type, 'survey_id' ) );
		$this->assertSame( (string) $id, get_post_meta( $type, 'survey_id', true ) );
		$this->assertWPError( \Rondo\Fields\Fields::update_many_for_post( $type, [ 'survey_id' => $person ] ) );
		$this->assertWPError( \Rondo\Fields\Fields::update_many_for_post( $type, [ 'survey_id' => 1.5 ] ) );
	}
}
