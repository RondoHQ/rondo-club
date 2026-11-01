<?php
/** Authenticated survey management and narrowly scoped public endpoints. */
namespace Rondo\REST;

use Rondo\Surveys\Surveys;
use Rondo\Surveys\Schema;
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
					'id'         => [
						'type'    => 'integer',
						'minimum' => 1,
					],
					'invitation' => [
						'type'      => 'string',
						'maxLength' => 64,
					],
					'action'     => [ 'type' => 'string' ],
					'person_ids' => [
						'type'     => 'array',
						'maxItems' => 2000,
						'items'    => [
							'type'    => 'integer',
							'minimum' => 1,
						],
					],
					'version'    => [
						'type'    => 'integer',
						'minimum' => 1,
					],
					'page'       => [
						'type'    => 'integer',
						'minimum' => 1,
					],
					'question'   => [ 'type' => 'string' ],
					'value'      => [ 'type' => 'string' ],
					'search'     => [
						'type'      => 'string',
						'maxLength' => 200,
					],
					'challenge'  => [
						'type'      => 'string',
						'maxLength' => 150,
					],
					'website'    => [
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
		$this->route( '/surveys/recipients', 'GET', 'recipients', 'can_manage' );
		$this->route( '/surveys/(?P<id>\d+)', 'GET', 'show', 'can_read' );
		$this->route( '/surveys/(?P<id>\d+)', 'PUT', 'update', 'can_manage' );
		$this->route( '/surveys/(?P<id>\d+)/action', 'POST', 'action', 'can_manage' );
		$this->route( '/surveys/(?P<id>\d+)/invitations', 'GET', 'invitations', 'can_manage' );
		$this->route( '/surveys/(?P<id>\d+)/invitations', 'POST', 'prepare', 'can_manage' );
		$this->route( '/surveys/(?P<id>\d+)/results', 'GET', 'results', 'can_results' );
		$this->route( '/surveys/(?P<id>\d+)/export', 'GET', 'export', 'can_results' );
		$this->route( '/public-surveys/(?P<id>\d+)', 'GET', 'public_show', 'public' );
		$this->route( '/public-surveys/(?P<id>\d+)', 'POST', 'public_submit', 'public' );
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
			return Surveys::queue( $id, $action === 'remind' );
		}
		$result = Surveys::action( $id, $action );
		return is_wp_error( $result ) ? $result : [ 'id' => $result ];
	}

	public function recipients( $r ) {
		$search = sanitize_text_field( $r['search'] ?? '' );
		if ( mb_strlen( $search ) < 2 ) {
			return [];
		}
		$posts  = get_posts(
			[
				'post_type'        => 'person',
				'post_status'      => 'publish',
				's'                => $search,
				'numberposts'      => 30,
				'suppress_filters' => false,
			]
			);
		$out    = [];
		$access = new \Rondo\Core\AccessControl();
		foreach ( $posts as $p ) {
			if ( ! $access->user_can_access_post( $p->ID ) ) {
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
		return $out;
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
		$s = Surveys::read( (int) $r['id'] );
		if ( is_wp_error( $s ) ) {
			return $s;
		}
		$version = absint( $r['version'] ?? $s['current_version'] );
		$v       = Surveys::read( $version, Surveys::VERSION );
		if ( is_wp_error( $v ) || (int) get_post_field( 'post_parent', $version ) !== $s['id'] ) {
			return Schema::error( 'Selecteer een gepubliceerde vragenlijstversie.' );
		}
		$rows = [];
		foreach ( Surveys::children( $s['id'], Surveys::RESPONSE ) as $p ) {
			$row = Surveys::read( $p->ID, Surveys::RESPONSE );
			if ( (int) $row['version'] !== $version ) {
				continue;
			}
			if ( $r['question'] && $r['value'] ) {
				$value = $row['answers'][ $r['question'] ] ?? null;
				if ( is_array( $value ) ? ! in_array( $r['value'], $value, true ) : $value !== $r['value'] ) {
					continue;
				}
			}
			unset( $row['id'] );
			$rows[] = $row;
		}
		return [
			'document' => $v['document'],
			'rows'     => $rows,
			'version'  => $version,
			'linked'   => $s['linked'],
		];
	}

	public function results( $r ) {
		$data = $this->result_data( $r );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$summary = [];
		foreach ( $data['document']['sections'] as $section ) {
			foreach ( $section['questions'] as $q ) {
				$counts   = array_fill_keys( array_column( $q['options'], 'id' ), 0 );
				$answered = 0;
				$texts    = [];
				foreach ( $data['rows'] as $row ) {
					if ( ! array_key_exists( $q['id'], $row['answers'] ) ) {
						continue;
					}
					++$answered;
					$value = $row['answers'][ $q['id'] ];
					if ( $q['type'] === 'text' ) {
						$texts[] = $value;
					} else {
						foreach ( (array) $value as $choice ) {
							++$counts[ $choice ];
						}
					}
				}
				$summary[] = $q + [
					'section'  => $section['title'],
					'answered' => $answered,
					'counts'   => $counts,
					'texts'    => $texts,
				];
			}
		}
		$page = max( 1, absint( $r['page'] ?? 1 ) );
		return [
			'version' => $data['version'],
			'total'   => count( $data['rows'] ),
			'summary' => $summary,
			'rows'    => array_slice( $data['rows'], ( $page - 1 ) * 50, 50 ),
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
