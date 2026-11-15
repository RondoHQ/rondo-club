<?php
/** Authenticated survey management and narrowly scoped public endpoints. */
namespace Rondo\REST;

use Rondo\Surveys\Surveys;
use Rondo\Surveys\Schema;
use Rondo\Surveys\Analysis;
use Rondo\Surveys\AnalysisReports;
use Rondo\Surveys\Followup;
use Rondo\Surveys\RoundComparison;
use Rondo\Core\UserRoles;
use Rondo\People\CommunicationPolicy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SurveyController extends Base {
	public function __construct() {
		parent::__construct();
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_filter( 'rest_post_dispatch', [ $this, 'no_cache' ], 10, 3 );
	}

	public function no_cache( $response, $server, $request ) {
		if ( strpos( $request->get_route(), '/rondo/v1/surveys' ) === 0 || strpos( $request->get_route(), '/rondo/v1/public-surveys' ) === 0 ) {
			$response->header( 'Cache-Control', 'no-store, private, max-age=0' );
			$response->header( 'X-Robots-Tag', 'noindex, nofollow' );
		}
		return $response;
	}

	public function can_manage(): bool {
		return UserRoles::can_access_section( 'enquetes' );
	}

	public function can_results(): bool {
		return UserRoles::can_access_section( 'enquete_resultaten' );
	}

	public function can_read(): bool {
		return $this->can_manage() || $this->can_results();
	}

	private function route( string $path, string $methods, string $callback, string $permission ): void {
		register_rest_route(
			'rondo/v1',
			$path,
			[
				'methods'             => $methods,
				'callback'            => [ $this, $callback ],
				'permission_callback' => $permission === 'public' ? '__return_true' : [ $this, $permission ],
				'args'                => [
					'id'          => [
						'type'    => 'integer',
						'minimum' => 1,
					],
					'invitation'  => [
						'type'      => 'string',
						'maxLength' => 64,
					],
					'action'      => [ 'type' => 'string' ],
					'all_members' => [
						'type'    => 'boolean',
						'default' => false,
					],
					'person_ids'  => [
						'type'     => 'array',
						'maxItems' => 2000,
						'items'    => [
							'type'    => 'integer',
							'minimum' => 1,
						],
					],
					'version'     => [
						'type'    => 'integer',
						'minimum' => 1,
					],
					'page'        => [
						'type'    => 'integer',
						'minimum' => 1,
					],
					'question'    => [ 'type' => 'string' ],
					'value'       => [ 'type' => 'string' ],
					'search'      => [
						'type'      => 'string',
						'maxLength' => 200,
					],
					'challenge'   => [
						'type'      => 'string',
						'maxLength' => 150,
					],
					'website'     => [
						'type'      => 'string',
						'maxLength' => 1000,
					],
				],
			]
			);
	}

	public function register_routes(): void {
		$this->route( '/surveys', 'GET', 'index', 'can_read' );
		$this->route( '/surveys', 'POST', 'create', 'can_manage' );
		$this->route( '/surveys/available', 'GET', 'available', 'can_choose' );
		$this->route( '/surveys/analysis-profiles', 'GET', 'analysis_templates', 'can_results' );
		$this->route( '/surveys/analysis-profiles', 'POST', 'analysis_save_template', 'can_analyze' );
		$this->route( '/surveys/(?P<id>\d+)/analysis', 'GET', 'analysis', 'can_results' );
		$this->route( '/surveys/(?P<id>\d+)/analysis', 'PUT', 'analysis_save', 'can_analyze' );
		$this->route( '/surveys/(?P<id>\d+)/analysis/texts', 'GET', 'analysis_texts', 'can_results' );
		$this->route( '/surveys/(?P<id>\d+)/analysis/source', 'GET', 'analysis_source', 'can_results' );
		$this->route( '/surveys/(?P<id>\d+)/analysis/submissions', 'GET', 'analysis_submissions', 'can_results' );
		$this->route( '/surveys/(?P<id>\d+)/analysis/labels', 'PUT', 'analysis_labels', 'can_analyze' );
		$this->route( '/surveys/(?P<id>\d+)/analysis/reports', 'GET', 'analysis_reports', 'can_results' );
		$this->route( '/surveys/(?P<id>\d+)/analysis/reports', 'POST', 'analysis_report', 'can_analyze' );
		$this->route( '/surveys/analysis-owners', 'GET', 'analysis_owners', 'can_analyze' );
		$this->route( '/surveys/analysis-rounds', 'GET', 'analysis_round_catalog', 'can_results' );
		$this->route( '/surveys/(?P<id>\d+)/analysis/actions', 'GET', 'analysis_actions', 'can_analyze' );
		$this->route( '/surveys/(?P<id>\d+)/analysis/actions', 'POST', 'analysis_save_action', 'can_analyze' );
		$this->route( '/surveys/(?P<id>\d+)/analysis/action-detail', 'GET', 'analysis_action_detail', 'can_analyze' );
		$this->route( '/surveys/(?P<id>\d+)/analysis/rules', 'GET', 'analysis_rules', 'can_analyze' );
		$this->route( '/surveys/(?P<id>\d+)/analysis/rules', 'POST', 'analysis_save_rule', 'can_analyze' );
		$this->route( '/surveys/(?P<id>\d+)/analysis/rules/run', 'POST', 'analysis_run_rule', 'can_analyze' );
		$this->route( '/surveys/(?P<id>\d+)/analysis/rounds', 'GET', 'analysis_rounds', 'can_results' );
		$this->route( '/surveys/(?P<id>\d+)/analysis/rounds', 'POST', 'analysis_save_rounds', 'can_analyze' );
		$this->route( '/surveys/recipients', 'GET', 'recipients', 'can_manage' );
		$this->route( '/surveys/(?P<id>\d+)', 'GET', 'show', 'can_read' );
		$this->route( '/surveys/(?P<id>\d+)', 'PUT', 'update', 'can_manage' );
		$this->route( '/surveys/(?P<id>\d+)/action', 'POST', 'action', 'can_manage' );
		$this->route( '/surveys/(?P<id>\d+)/invitations', 'GET', 'invitations', 'can_manage' );
		$this->route( '/surveys/(?P<id>\d+)/invitations', 'POST', 'prepare', 'can_manage' );
		$this->route( '/surveys/(?P<id>\d+)/email', 'GET', 'email_settings', 'can_manage' );
		$this->route( '/surveys/(?P<id>\d+)/email', 'PUT', 'save_email', 'can_manage' );
		$this->route( '/surveys/(?P<id>\d+)/email-preview', 'POST', 'email_preview', 'can_manage' );
		$this->route( '/surveys/(?P<id>\d+)/email-test', 'POST', 'email_test', 'can_manage' );
		$this->route( '/surveys/(?P<id>\d+)/results', 'GET', 'results', 'can_results' );
		$this->route( '/surveys/(?P<id>\d+)/export', 'GET', 'export', 'can_results' );
		$this->route( '/public-surveys/(?P<id>\d+)', 'GET', 'public_show', 'public' );
		$this->route( '/public-surveys/(?P<id>\d+)', 'POST', 'public_submit', 'public' );
	}


	public function can_analyze(): bool {
		return $this->can_manage() && $this->can_results();
	}

	public function analysis_owners() {
		return Followup::owners(); }
	public function analysis_round_catalog() {
		return RoundComparison::catalog(); }
	public function analysis_actions( $r ) {
		return Followup::listing( (int) $r['id'], $r->get_params() ); }
	public function analysis_save_action( $r ) {
		return Followup::save( (int) $r['id'], (array) $r->get_json_params() ); }
	public function analysis_action_detail( $r ) {
		return Followup::detail( (int) $r['id'], (int) $r['version'], (int) $r['action_id'] ); }
	public function analysis_rules( $r ) {
		return Followup::rules( (int) $r['id'], (int) $r['version'] ); }
	public function analysis_save_rule( $r ) {
		return Followup::save_rule( (int) $r['id'], (array) $r->get_json_params() ); }
	public function analysis_run_rule( $r ) {
		return Followup::run_rule( (int) $r['id'], (array) $r->get_json_params() ); }
	public function analysis_rounds( $r ) {
		return RoundComparison::listing( (int) $r['id'], (int) $r['version'] ); }
	public function analysis_save_rounds( $r ) {
		return RoundComparison::save( (int) $r['id'], (array) $r->get_json_params() ); }

	public function analysis_templates() {
		return Analysis::templates();
	}

	public function analysis_save_template( $r ) {
		$id = Analysis::save_template( (array) $r->get_json_params() );
		return is_wp_error( $id ) ? $id : [ 'id' => $id ];
	}

	public function analysis( $r ) {
		$data = $this->result_data( $r );
		if ( is_wp_error( $data ) ) {
			return $data; }
		return Analysis::aggregate( $data ) + [
			'version'         => $data['version'],
			'configuration'   => $data['configuration'],
			'selection'       => $data['selection'],
			'response'        => $data['response'],
			'source_revision' => $data['source_revision'],
			'source_count'    => $data['source_count'],
			'can_manage'      => $this->can_analyze(),
			'can_context'     => current_user_can( 'vrijwilligers' ) || current_user_can( 'manage_options' ),
		];
	}

	public function analysis_save( $r ) {
		return Analysis::save_configuration( (int) $r['id'], (array) $r->get_json_params() );
	}

	public function analysis_texts( $r ) {
		$data = $this->result_data( $r );
		return is_wp_error( $data ) ? $data : Analysis::text_page( $data, max( 1, (int) ( $r['page'] ?? 1 ) ) );
	}

	public function analysis_labels( $r ) {
		return Analysis::label( (int) $r['id'], (array) $r->get_json_params() );
	}

	public function analysis_submissions( $r ) {
		$data = $this->result_data( $r );
		if ( is_wp_error( $data ) ) {
			return $data; }
		$page = max( 1, (int) ( $r['page'] ?? 1 ) );
		$rows = array_map(
			static function ( $row ) {
				unset( $row['labels'], $row['label_revision'] );
				return $row;
			},
			array_slice( $data['rows'], ( $page - 1 ) * 25, 25 )
			);
		return [
			'rows'  => $rows,
			'page'  => $page,
			'pages' => (int) ceil( count( $data['rows'] ) / 25 ),
		];
	}

	public function analysis_source( $r ) {
		$id     = (int) $r['id'];
		$reply  = absint( $r['response_id'] );
		$row    = Surveys::read( $reply, Surveys::RESPONSE );
		$survey = Surveys::read( $id );
		if ( is_wp_error( $survey ) || is_wp_error( $row ) || (int) get_post_field( 'post_parent', $reply ) !== $id ) {
			return Schema::error( 'Bron niet meer beschikbaar.', 404 );
		}
		$publication = Surveys::read( (int) $row['version'], Surveys::VERSION );
		if ( is_wp_error( $publication ) || (int) get_post_field( 'post_parent', $row['version'] ) !== $id ) {
			return Schema::error( 'Bron niet meer beschikbaar.', 404 );
		}
		if ( ! $survey['linked'] ) {
			unset( $row['respondent'] ); }
		return [
			'row'       => $row,
			'questions' => array_values( Analysis::questions( $publication['document'] ) ),
		];
	}

	public function analysis_reports( $r ) {
		$data = $this->result_data( $r );
		return is_wp_error( $data ) ? $data : AnalysisReports::listing( $data );
	}

	public function analysis_report( $r ) {
		$id = AnalysisReports::create( (int) $r['id'], (array) $r->get_json_params() );
		return is_wp_error( $id ) ? $id : [ 'id' => $id ];
	}

	public function can_choose(): bool {
		return $this->can_manage() || current_user_can( 'vrijwilligers' ) || current_user_can( 'manage_options' );
	}

	public function available() {
		return array_values(
			array_map(
			static fn( $s ) => [
				'id'    => $s['id'],
				'title' => $s['title'],
			],
			array_filter( $this->index(), [ Surveys::class, 'is_open' ] )
			)
			);
	}

	public function index() {
		$posts = get_posts(
			[
				'post_type'        => Surveys::TYPE,
				'post_status'      => 'private',
				'numberposts'      => -1,
				'suppress_filters' => true,
			]
			);
		return array_map(
			static function ( $post ) {
				$s = Surveys::read( $post->ID );
				unset( $s['document'] );
				$s['url'] = Surveys::url( $post->ID );
				return $s;
			},
			$posts
			);
	}

	public function show( $r ) {
		$s = Surveys::read( (int) $r['id'] );
		if ( is_wp_error( $s ) ) {
			return $s;
		}
		$s['url']         = Surveys::url( $s['id'] );
		$s['versions']    = array_map(
			static fn( $p ) => [
				'id'   => $p->ID,
				'date' => $p->post_date_gmt,
			],
			Surveys::children( $s['id'], Surveys::VERSION )
			);
		$s['can_manage']  = $this->can_manage();
		$s['can_results'] = $this->can_results();
		return $s;
	}

	public function create( $r ) {
		$input = ( is_array( $r->get_json_params() ) ? $r->get_json_params() : [] );
		if ( ( $input['template'] ?? '' ) === 'volunteers' ) {
			$input = \Rondo\Surveys\VolunteerTemplate::data();
		}
		$id = Surveys::create( $input );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$updated = Surveys::update( $id, $input );
		return is_wp_error( $updated ) ? $updated : Surveys::read( $id );
	}

	public function update( $r ) {
		$result = Surveys::update( (int) $r['id'], ( is_array( $r->get_json_params() ) ? $r->get_json_params() : [] ) );
		return is_wp_error( $result ) ? $result : $this->show( $r );
	}

	public function action( $r ) {
		$id     = (int) $r['id'];
		$action = (string) $r['action'];
		if ( in_array( $action, [ 'send', 'remind' ], true ) ) {
			return Surveys::queue( $id, $action === 'remind', $r['expected_email'] );
		}
		$result = Surveys::action( $id, $action );
		return is_wp_error( $result ) ? $result : [ 'id' => $result ];
	}

	/** Match the current member list; external contacts and company-only records are excluded. */
	private function is_current_member( int $id ): bool {
		return ! \Rondo\Fields\Fields::get_for_post( $id, 'former_member' )
			&& \Rondo\Fields\Fields::get_for_post( $id, 'person_type' ) !== 'contact'
			&& ( trim( (string) \Rondo\Fields\Fields::get_for_post( $id, 'company_name' ) ) === ''
				|| trim( (string) \Rondo\Fields\Fields::get_for_post( $id, 'first_name' ) ) !== ''
				|| trim( (string) \Rondo\Fields\Fields::get_for_post( $id, 'last_name' ) ) !== '' );
	}

	public function recipients( $r ) {
		$all    = (bool) $r['all_members'];
		$search = sanitize_text_field( $r['search'] ?? '' );
		if ( ! $all && mb_strlen( $search ) < 2 ) {
			return [];
		}
		$out    = [];
		$access = new \Rondo\Core\AccessControl();
		$page   = 1;
		$limit  = $all ? 250 : 30;
		do {
			$posts = get_posts(
				[
					'post_type'        => 'person',
					'post_status'      => 'publish',
					's'                => $all ? '' : $search,
					'numberposts'      => $limit,
					'paged'            => $page++,
					'orderby'          => 'ID',
					'order'            => 'ASC',
					'suppress_filters' => false,
				]
			);
			foreach ( $posts as $p ) {
				if ( ! $access->user_can_access_post( $p->ID ) || ( $all && ! $this->is_current_member( $p->ID ) ) ) {
					continue;
				}
				$email = CommunicationPolicy::primary_email( $p->ID );
				if ( $email ) {
					$out[] = [
						'id'    => $p->ID,
						'name'  => \Rondo\Core\PostTitle::plain( $p->ID ),
						'email' => $email,
					];
				}
			}
		} while ( $all && count( $posts ) === $limit );
		return $out;
	}

	public function email_settings( $r ) {
		$survey = Surveys::read( (int) $r['id'] );
		return is_wp_error( $survey ) ? $survey : Surveys::email_settings( $survey );
	}

	public function save_email( $r ) {
		return Surveys::save_email( (int) $r['id'], $r['email'], $r['expected_email'] );
	}

	public function email_preview( $r ) {
		$survey = Surveys::read( (int) $r['id'] );
		if ( is_wp_error( $survey ) ) {
			return $survey;
		}
		$email = Surveys::validate_email( $r['email'] );
		if ( is_wp_error( $email ) ) {
			return $email;
		}
		$user       = wp_get_current_user();
		$person_id  = (int) get_user_meta( $user->ID, 'rondo_linked_person_id', true );
		$first_name = get_post_type( $person_id ) === 'person' ? (string) \Rondo\Fields\Fields::get_for_post( $person_id, 'first_name' ) : '';
		$first_name = trim( $first_name ) !== '' ? $first_name : (string) $user->first_name;
		return Surveys::render_email( $survey, $email, $user->display_name, add_query_arg( 'preview', '1', Surveys::url( $survey['id'] ) ), $first_name );
	}

	/** Test mail is restricted to the signed-in manager and creates no real invitation. */
	public function email_test( $r ) {
		$message = $this->email_preview( $r );
		if ( is_wp_error( $message ) ) {
			return $message;
		}
		$email = wp_get_current_user()->user_email;
		if ( ! is_email( $email ) ) {
			return Schema::error( 'Je account heeft geen geldig e-mailadres.' );
		}
		if ( ! wp_mail( $email, '[Test] ' . $message['subject'], $message['html'], [ 'Content-Type: text/html; charset=UTF-8' ] ) ) {
			return Schema::error( 'De testmail kon niet worden verzonden. Probeer het opnieuw.', 502 );
		}
		return [ 'sent_to' => $email ];
	}

	public function prepare( $r ) {
		$recipients = $r['recipients'] ?? [];
		$people     = $r['person_ids'] ?? [];
		if ( ! is_array( $recipients ) || ! is_array( $people ) || count( $people ) > 2000 ) {
			return Schema::error( 'Ongeldige ontvangers.' );
		}
		foreach ( $recipients as &$recipient ) {
			if ( ! is_array( $recipient ) ) {
				return Schema::error( 'Ongeldige ontvanger.' );
			}
			unset( $recipient['person_id'] );
		}
		unset( $recipient );
		$access = new \Rondo\Core\AccessControl();
		foreach ( $people as $person_id ) {
			$person_id = absint( $person_id );
			if ( get_post_type( $person_id ) !== 'person' || ! $access->user_can_access_post( $person_id ) ) {
				return Schema::error( 'Geen toegang tot een geselecteerd lid.', 403 );
			}
			if ( $r['all_members'] && ! $this->is_current_member( $person_id ) ) {
				continue;
			}
			$email = CommunicationPolicy::primary_email( $person_id );
			if ( $email ) {
				$recipients[] = [
					'email'     => $email,
					'name'      => \Rondo\Core\PostTitle::plain( $person_id ),
					'person_id' => $person_id,
				];
			}
		}
		$result = Surveys::prepare( (int) $r['id'], $recipients );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return [ 'prepared' => count( $result ) ];
	}

	public function invitations( $r ) {
		$s = Surveys::read( (int) $r['id'] );
		if ( is_wp_error( $s ) ) {
			return $s;
		}
		return array_map(
			static function ( $p ) {
				$i = Surveys::read( $p->ID, Surveys::INVITE );
				unset( $i['token'] );
				return $i;
			},
			Surveys::children( $s['id'], Surveys::INVITE )
			);
	}

	private function result_data( $r ) {
		return Analysis::select( (int) $r['id'], $r->get_params() );
	}

	public function results( $r ) {
		$data = $this->result_data( $r );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$summary = Analysis::summary( $data['questions'], $data['rows'], true );
		$page    = max( 1, absint( $r['page'] ?? 1 ) );
		return [
			'version' => $data['version'],
			'total'   => count( $data['rows'] ),
			'summary' => $summary,
			'rows'    => array_map(
				static function ( $row ) {
					unset( $row['labels'], $row['label_revision'] );
					return $row; },
				array_slice( $data['rows'], ( $page - 1 ) * 50, 50 )
				),
			'page'    => $page,
			'pages'   => (int) ceil( count( $data['rows'] ) / 50 ),
		];
	}

	/** CSV is delivered as JSON so the existing nonce-aware client can download it. */
	public function export( $r ) {
		$data = $this->result_data( $r );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$questions = [];
		foreach ( $data['document']['sections'] as $section ) {
			$questions = array_merge( $questions, $section['questions'] );
		}
		$header = $data['linked'] ? [ 'Naam', 'E-mail', 'Persoon ID', 'Context' ] : [];
		$header = array_merge( $header, array_column( $questions, 'label' ) );
		$csv    = self::csv_line( $header );
		foreach ( $data['rows'] as $row ) {
			$values = [];
			if ( $data['linked'] ) {
				foreach ( [ 'name', 'email', 'person_id', 'context' ] as $key ) {
					$values[] = $row['respondent'][ $key ] ?? '';
				}
			}
			foreach ( $questions as $q ) {
				$value = $row['answers'][ $q['id'] ] ?? '';
				if ( $q['type'] !== 'text' && $value !== '' ) {
					$labels = array_column( $q['options'], 'label', 'id' );
					$value  = implode( ' | ', array_map( static fn( $key ) => $labels[ $key ] ?? $key, (array) $value ) );
				}
				$values[] = $value;
			}
			$csv .= self::csv_line( $values );
		}
		return [
			'csv'      => "\xEF\xBB\xBF" . $csv,
			'filename' => 'enquete-' . absint( $r['id'] ) . '-versie-' . $data['version'] . '.csv',
		];
	}

	private static function csv_line( array $values ): string {
		return implode(
			';',
			array_map(
			static function ( $value ) {
				$value = (string) $value;
				if ( preg_match( '/^[\s]*[=+@\-]/u', $value ) ) {
					$value = "'" . $value;
				}
				return '"' . str_replace( '"', '""', $value ) . '"';
			},
			$values
			)
			) . "\r\n";
	}

	public function public_show( $r ) {
		return Surveys::public_data( (int) $r['id'], (string) ( $r['invitation'] ?? '' ) );
	}

	public function public_submit( $r ) {
		// Do not trust forwarded headers; keep only a short-lived, salted counter.
		$ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$key   = 'survey_rate_' . hash_hmac( 'sha256', $ip . ':' . (int) $r['id'] . ':' . (string) ( $r['invitation'] ?? '' ), wp_salt() );
		$count = (int) get_transient( $key );
		if ( $count >= 300 ) {
			return Schema::error( 'Te veel pogingen. Probeer het over een uur opnieuw.', 429 );
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS );
		return Surveys::submit( (int) $r['id'], ( is_array( $r->get_json_params() ) ? $r->get_json_params() : [] ), (string) ( $r['invitation'] ?? '' ) );
	}
}
