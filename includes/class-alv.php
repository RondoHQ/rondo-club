<?php

namespace Rondo\Alv;

use Rondo\Fields\Fields;
use Rondo\Fields\Formatter;
use Rondo\People\PersonName;
use Rondo\Core\UserRoles;
use WP_Error;
use WP_REST_Response;

class Alv {
	private Publications $publications;

	private const MEMBERSHIP_META = [ 'type-lid', 'former_member', 'lid-sinds', 'lid-tot' ];

	public function __construct() {
		$this->publications = new Publications( $this );
		add_action( 'rest_api_init', [ $this, 'routes' ] );
		add_filter( 'rest_post_dispatch', [ $this, 'private_response' ], 10, 3 );
		add_action( 'rondo_alv_start', [ $this, 'freeze_due' ] );
		foreach ( [ 'add', 'update', 'delete' ] as $operation ) {
			add_filter( "{$operation}_post_metadata", [ $this, 'before_membership_meta' ], 10, 3 );
		}
		add_filter( 'wp_insert_post_empty_content', [ $this, 'before_person_save' ], 10, 2 );
		add_filter( 'pre_delete_post', [ $this, 'before_person_delete' ], 10, 2 );
	}

	public function table( string $name ): string {
		global $wpdb;
		return $wpdb->prefix . 'rondo_alv_' . $name;
	}

	private function install(): bool {
		global $wpdb;
		if ( get_option( 'rondo_alv_schema' ) === '1' ) {
			return true;
		}
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$schemas = [
			'meetings'      => 'id bigint unsigned NOT NULL AUTO_INCREMENT,
				starts bigint unsigned NOT NULL,
				frozen tinyint unsigned NOT NULL DEFAULT 0,
				document longtext NOT NULL,
				PRIMARY KEY  (id),
				KEY due (frozen,starts)',
			'participation' => 'meeting_id bigint unsigned NOT NULL,
				question_id varchar(36) NOT NULL,
				person_id bigint unsigned NOT NULL,
				choice int unsigned DEFAULT NULL,
				PRIMARY KEY  (meeting_id,question_id,person_id)',
			'ballots'       => 'id char(36) NOT NULL,
				meeting_id bigint unsigned NOT NULL,
				question_id varchar(36) NOT NULL,
				choice int unsigned NOT NULL,
				PRIMARY KEY  (id),
				KEY tally (meeting_id,question_id,choice)',
		];
		foreach ( $schemas as $name => $schema ) {
			$table = $this->table( $name );
			dbDelta( "CREATE TABLE $table ($schema) ENGINE=InnoDB $charset;" );
			$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table ) );
			if ( ! $status || strcasecmp( $status->Engine, 'InnoDB' ) !== 0 ) {
				return false;
			}
		}
		update_option( 'rondo_alv_schema', '1', false );
		return true;
	}

	private function error( string $message, int $status = 409 ): WP_Error {
		return new WP_Error( 'rondo_alv', $message, [ 'status' => $status ] );
	}

	private function permissions(): array {
		$board = UserRoles::can_access_board();
		return [
			'manage'    => $board || UserRoles::can_access_section( 'rondo_alv_manage' ),
			'edit'      => $board || UserRoles::can_access_section( 'rondo_alv_edit' ),
			'approve'   => UserRoles::can_access_section( 'rondo_alv_approve' ),
			'publish'   => UserRoles::can_access_section( 'rondo_alv_publish' ),
			'secretary' => UserRoles::can_access_section( 'rondo_alv_secretary' ),
		];
	}

	public function current_member(): bool {
		$id = $this->person();
		return $id && $this->is_member( $id, wp_date( 'Y-m-d' ) );
	}

	public function meeting_documents( int $id = 0 ): array {
		global $wpdb;
		if ( get_option( 'rondo_alv_schema' ) !== '1' ) {
			return [];
		}
		$table    = $this->table( 'meetings' );
		$rows     = $id
			? $wpdb->get_results( $wpdb->prepare( "SELECT id, starts, document FROM $table WHERE id = %d", $id ) )
			: $wpdb->get_results( "SELECT id, starts, document FROM $table ORDER BY starts DESC" );
		$meetings = [];
		foreach ( $rows as $row ) {
			$meetings[ (int) $row->id ] = json_decode( $row->document, true, 512, JSON_THROW_ON_ERROR ) + [ 'starts' => (int) $row->starts ];
		}
		return $meetings;
	}

	private function person(): int {
		$id = (int) get_user_meta( get_current_user_id(), 'rondo_linked_person_id', true );
		return get_post_type( $id ) === 'person' && get_post_status( $id ) === 'publish' ? $id : 0;
	}

	private function is_member( int $id, string $date ): bool {
		$type = strtolower( trim( (string) Fields::try_get_for_post( $id, 'type_lid' ) ) );
		if ( ! in_array( $type, [ 'bondslid', 'verenigingslid' ], true ) || Fields::try_get_for_post( $id, 'former_member' ) ) {
			return false;
		}
		$dates = Formatter::for_wire(
			'person',
			[
				'lid_sinds' => Fields::try_get_for_post( $id, 'lid_sinds' ),
				'lid_tot'   => Fields::try_get_for_post( $id, 'lid_tot' ),
			]
		);
		return ( empty( $dates['lid_sinds'] ) || $dates['lid_sinds'] <= $date )
			&& ( empty( $dates['lid_tot'] ) || $dates['lid_tot'] >= $date );
	}

	private function roster( int $starts ): array {
		$ids = get_posts(
			[
				'post_type'        => 'person',
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => true,
			]
		);
		update_meta_cache( 'post', $ids );
		$date = wp_date( 'Y-m-d', $starts );
		return array_values( array_filter( array_map( 'intval', $ids ), fn( $id ) => $this->is_member( $id, $date ) ) );
	}

	public function before_membership_meta( $check, $id, $key ) {
		if ( in_array( $key, self::MEMBERSHIP_META, true ) && ( ! $id || get_post_type( $id ) === 'person' ) && ! $this->freeze_due() ) {
			return false;
		}
		return $check;
	}

	public function before_person_save( bool $empty, array $data ): bool {
		return $empty || ( $data['post_type'] === 'person' && ! $this->freeze_due() );
	}

	public function before_person_delete( $delete, $post ) {
		if ( $post->post_type === 'person' && ! $this->freeze_due() ) {
			return false;
		}
		return $delete;
	}

	public function freeze_due(): bool {
		global $wpdb;
		if ( get_option( 'rondo_alv_schema' ) !== '1' ) {
			return true;
		}
		$table = $this->table( 'meetings' );
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM $table WHERE frozen = 0 AND starts <= %d", time() ) );
		if ( $wpdb->last_error ) {
			return false;
		}
		foreach ( $ids as $id ) {
			$result = $this->locked( (int) $id, static fn() => true );
			if ( is_wp_error( $result ) ) {
				return false;
			}
		}
		return true;
	}

	public function locked( int $id, callable $action ) {
		global $wpdb;
		$table = $this->table( 'meetings' );
		if ( $wpdb->query( 'START TRANSACTION' ) === false ) {
			return $this->error( 'De ALV kon niet worden bijgewerkt.', 503 );
		}
		$committed = false;
		try {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d FOR UPDATE", $id ) );
			if ( ! $row ) {
				return $this->error( 'ALV niet gevonden.', 404 );
			}
			$meeting    = json_decode( $row->document, true, 512, JSON_THROW_ON_ERROR );
			$was_frozen = (bool) $row->frozen;
			$old_start  = (int) $row->starts;
			if ( ! $row->frozen && time() >= (int) $row->starts ) {
				$meeting['roster']    = $this->roster( (int) $row->starts );
				$meeting['frozen_at'] = time();
				$row->frozen          = 1;
			}
			if ( time() >= (int) $row->starts ) {
				foreach ( $meeting['questions'] as &$question ) {
					if ( $question['state'] === 'open' ) {
						$question['state'] = 'closed';
						$question['ends']  = (int) $row->starts;
					}
				}
				unset( $question );
			}
			$result = $action( $meeting, $row );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$document = wp_json_encode( $meeting );
			if ( $document === false ) {
				return $this->error( 'De ALV kon niet worden bijgewerkt.', 503 );
			}
			if ( $document !== $row->document || (bool) $row->frozen !== $was_frozen || (int) $row->starts !== $old_start ) {
				$saved = $wpdb->update(
					$table,
					[
						'document' => $document,
						'starts'   => $row->starts,
						'frozen'   => $row->frozen,
					],
					[ 'id' => $id ]
				);
				if ( $saved === false ) {
					return $this->error( 'De ALV kon niet worden bijgewerkt.', 503 );
				}
			}
			if ( $wpdb->query( 'COMMIT' ) === false ) {
				return $this->error( 'De ALV kon niet worden bijgewerkt.', 503 );
			}
			$committed = true;
			if ( (int) $row->starts !== $old_start ) {
				wp_schedule_single_event( (int) $row->starts, 'rondo_alv_start', [ $id, (int) $row->starts ] );
			}
			return $result;
		} finally {
			if ( ! $committed ) {
				$wpdb->query( 'ROLLBACK' );
			}
		}
	}

	public function private_response( $response, $server, $request ) {
		if ( preg_match( '#^/rondo/v1/alv(?:/|$)#', $request->get_route() ) ) {
			$response->header( 'Cache-Control', 'private, no-store' );
		}
		return $response;
	}

	public function routes(): void {
		register_rest_route(
			'rondo/v1',
			'/alv(?:/(?P<id>\d+))?',
			[
				'methods'             => 'GET, POST',
				'permission_callback' => static fn() => is_user_logged_in() && UserRoles::is_user_approved( get_current_user_id() ),
				'callback'            => [ $this, 'request' ],
			]
		);
	}

	public function request( $request ) {
		global $wpdb;
		$rights = $this->permissions();
		$staff  = in_array( true, $rights, true );
		$person = $this->person();
		$member = $person && $this->is_member( $person, wp_date( 'Y-m-d' ) );
		$id     = (int) $request['id'];
		$table  = $this->table( 'meetings' );

		if ( ! $this->install() || ! $this->freeze_due() ) {
			return $this->error( 'De ALV is tijdelijk niet beschikbaar.', 503 );
		}
		if ( $request->get_method() === 'POST' ) {
			$input = $request->get_json_params();
			if ( ! is_array( $input ) ) {
				return $this->error( 'Ongeldige aanvraag.', 400 );
			}
			foreach ( $input as $value ) {
				if ( ! is_scalar( $value ) && $value !== null ) {
					return $this->error( 'Ongeldige aanvraag.', 400 );
				}
			}
			$input += array_fill_keys(
				[ 'action', 'title', 'starts', 'options', 'secret', 'question', 'choice', 'page', 'version', 'html', 'result_question', 'person', 'present', 'direction' ],
				null
			);
			$result = $id
				? $this->locked( $id, fn( &$meeting, $row ) => $this->mutate( $meeting, $row, $input, $rights, $person ) )
				: $this->create( $input, $rights );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return new WP_REST_Response( $result, 200, [ 'Cache-Control' => 'private, no-store' ] );
		}

		$meetings = [];
		$rows     = $id
			? $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table WHERE id = %d", $id ) )
			: $wpdb->get_results( "SELECT id, starts, frozen, document FROM $table ORDER BY starts DESC" );
		if ( $wpdb->last_error ) {
			return $this->error( 'De ALV kon niet worden geladen.', 503 );
		}
		foreach ( $rows as $row ) {
			$meeting  = json_decode( $row->document, true, 512, JSON_THROW_ON_ERROR );
			$eligible = time() < (int) $row->starts ? $member : $person && in_array( $person, $meeting['roster'] ?? [], true );
			if ( ! $member && ! $staff && ! $eligible ) {
				continue;
			}
			$item = [
				'id'     => (int) $row->id,
				'title'  => $meeting['title'],
				'starts' => (int) $row->starts,
			];
			if ( $id ) {
				$item += $this->detail( $meeting, $row, $rights, $person, $member, $staff, $eligible );
			}
			$meetings[] = $item;
		}
		if ( $id && ! $meetings ) {
			return $this->error( 'ALV niet gevonden.', 404 );
		}
		return new WP_REST_Response(
			[
				'meetings' => $meetings,
				'rights'   => $rights,
				'timezone' => wp_timezone_string(),
			],
			200,
			[ 'Cache-Control' => 'private, no-store' ]
		);
	}

	private function start_time( $value ): int {
		if ( ! is_string( $value ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/D', $value ) ) {
			return 0;
		}
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', (string) $value, wp_timezone() );
		return $date && $date->format( 'Y-m-d\TH:i' ) === $value ? $date->getTimestamp() : 0;
	}

	private function create( $request, array $rights ) {
		global $wpdb;
		if ( ! $rights['manage'] ) {
			return $this->error( 'Je mag geen ALV aanmaken.', 403 );
		}
		$title  = sanitize_text_field( (string) $request['title'] );
		$starts = $this->start_time( $request['starts'] );
		if ( $title === '' || $starts <= time() ) {
			return $this->error( 'Vul een titel en een toekomstige starttijd in.', 400 );
		}
		$document = wp_json_encode(
			[
				'title'     => $title,
				'questions' => [],
				'pages'     => [],
			]
			);
		if ( ! $wpdb->insert(
			$this->table( 'meetings' ),
			[
				'starts'   => $starts,
				'document' => $document,
			]
			) ) {
			return $this->error( 'De ALV kon niet worden aangemaakt.', 503 );
		}
		$id = (int) $wpdb->insert_id;
		wp_schedule_single_event( $starts, 'rondo_alv_start', [ $id ] );
		return [ 'id' => $id ];
	}

	private function detail( array $meeting, $row, array $rights, int $person, bool $member, bool $staff, bool $eligible ): array {
		global $wpdb;
		$table     = $this->table( 'participation' );
		$votes     = $person ? $wpdb->get_col(
			$wpdb->prepare( "SELECT question_id FROM $table WHERE meeting_id = %d AND person_id = %d", $row->id, $person )
		) : [];
		$questions = [];
		foreach ( $meeting['questions'] as $key => $question ) {
			if ( ! $rights['manage'] && ( ! $eligible || $question['state'] === 'draft' ) ) {
				continue;
			}
			$question['id']       = $key;
			$question['voted']    = in_array( $key, $votes, true );
			$before_start         = time() < (int) $row->starts;
			$question['can_vote'] = $eligible && $before_start;
			if ( $question['state'] === 'open' ) {
				$question['ends'] = (int) $row->starts;
				if ( ! $before_start ) {
					$question['state'] = 'closed';
				}
			}
			if ( $rights['manage'] ) {
				$turnout              = $this->turnout( (int) $row->id, $key );
				$question['turnout']  = $turnout;
				$question['can_edit'] = $turnout === 0 && ( $question['state'] === 'draft' || ( $question['state'] === 'open' && $before_start ) );
				if ( $question['state'] === 'closed' ) {
					$totals = $this->totals( (int) $row->id, $key, $question );
					if ( ! is_wp_error( $totals ) ) {
						$question['totals'] = $totals;
					}
					if ( ! $question['secret'] ) {
						$question['open_votes'] = array_map(
							fn( $vote ) => [
								'id'     => (int) $vote->person_id,
								'name'   => $this->person_name( (int) $vote->person_id ),
								'choice' => (int) $vote->choice,
							],
							$wpdb->get_results( $wpdb->prepare( "SELECT person_id, choice FROM $table WHERE meeting_id = %d AND question_id = %s", $row->id, $key ) ) ?? []
						);
					}
				}
			}
			$questions[] = $question;
		}
		$admin = current_user_can( 'manage_options' );
		$draft = $admin ? $this->publications->draft( (int) $row->id, $meeting['publication'] ?? [] ) : null;
		$pages = [];
		$tree  = $meeting['publication']['live']['pages'] ?? [];
		if ( $member || $admin ) {
			$tree = array_values( $tree );
		} else {
			$tree = [];
		}
		foreach ( ( $draft !== null || isset( $meeting['publication']['live'] ) ) ? [] : $meeting['pages'] as $key => $page ) {
			if ( $staff && ( $rights['edit'] || $rights['approve'] || $rights['publish'] ) ) {
				$version = $page['current'];
			} elseif ( $member && isset( $page['published'] ) ) {
				$version = $page['published'];
			} else {
				continue;
			}
			$version['id'] = $key;
			$pages[]       = $version;
		}
		$detail = [
			'eligible'        => $eligible,
			'questions'       => $questions,
			'pages'           => $pages,
			'tree'            => $tree,
			'draft_tree'      => $draft === null ? null : array_values( $draft['pages'] ),
			'draft_front'     => $draft['front'] ?? null,
			'published_front' => $meeting['publication']['live']['front'] ?? null,
			'admin_pages_url' => $admin ? admin_url( 'admin.php?page=rondo-alv-pages&meeting=' . (int) $row->id ) : null,
			'wordpress_pages' => isset( $meeting['publication']['root'] ),
			'frozen'          => (bool) $row->frozen,
		];
		if ( $rights['manage'] ) {
			$ids              = $meeting['roster'] ?? $this->roster( time() );
			$accounts         = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT meta_value FROM $wpdb->usermeta WHERE meta_key = %s", 'rondo_linked_person_id' ) );
			$linked           = array_fill_keys( array_map( 'intval', $accounts ), true );
			$detail['roster'] = array_map(
				fn( $id ) => [
					'id'     => $id,
					'name'   => $this->person_name( $id ),
					'linked' => isset( $linked[ $id ] ),
				],
				$ids
			);
			usort( $detail['roster'], static fn( $a, $b ) => strnatcasecmp( $a['name'], $b['name'] ) );
		}
		if ( $rights['secretary'] ) {
			$roster       = $meeting['roster'] ?? $this->roster( time() );
			$participants = $wpdb->get_results(
				$wpdb->prepare( "SELECT person_id, question_id FROM $table WHERE meeting_id = %d", $row->id )
			);
			$by_person    = [];
			foreach ( $participants as $participant ) {
				$by_person[ (int) $participant->person_id ][] = $participant->question_id;
			}
			$people              = array_unique( array_merge( $roster, array_keys( $meeting['attendance'] ?? [] ), array_keys( $by_person ) ) );
			$detail['secretary'] = [
				'questions' => array_filter( array_map( static fn( $question ) => $question['state'] === 'draft' ? null : $question['title'], $meeting['questions'] ) ),
				'people'    => array_map(
					fn( $id ) => [
						'id'       => $id,
						'name'     => $this->person_name( $id ),
						'present'  => isset( $meeting['attendance'][ $id ] ),
						'eligible' => in_array( $id, $roster, true ),
						'voted'    => $by_person[ $id ] ?? [],
					],
					$people
					),
				'guests'    => array_values( $meeting['guests'] ?? [] ),
			];
			usort( $detail['secretary']['people'], static fn( $a, $b ) => strnatcasecmp( $a['name'], $b['name'] ) );
		}
		return $detail;
	}

	private function person_name( int $id ): string {
		return get_post_type( $id ) === 'person' ? PersonName::get( $id ) : 'Verwijderde persoon #' . $id;
	}

	private function totals( int $id, string $key, array $question ) {
		global $wpdb;
		$table  = $this->table( $question['secret'] ? 'ballots' : 'participation' );
		$counts = $wpdb->get_results(
			$wpdb->prepare( "SELECT choice, COUNT(*) AS total FROM $table WHERE meeting_id = %d AND question_id = %s GROUP BY choice", $id, $key ),
			OBJECT_K
		);
		if ( $wpdb->last_error ) {
			return $this->error( 'De uitslag kon niet worden gelezen.', 503 );
		}
		return array_map( static fn( $i ) => (int) ( $counts[ $i ]->total ?? 0 ), array_keys( $question['options'] ) );
	}

	private function mutate( array &$meeting, $row, $request, array $rights, int $person ) {
		$action = (string) $request['action'];
		if ( $action === 'vote' ) {
			return $this->vote( $meeting, $row, $request, $person );
		}
		if ( in_array( $action, [ 'reschedule', 'question', 'delete_question', 'edit_question', 'move_question', 'draft_question' ], true ) ) {
			if ( ! $rights['manage'] ) {
				return $this->error( 'Je mag deze stemming niet beheren.', 403 );
			}
			return $this->manage( $meeting, $row, $request, $action );
		}
		if ( in_array( $action, [ 'attendance', 'guest_add', 'guest_remove' ], true ) ) {
			if ( ! $rights['secretary'] ) {
				return $this->error( 'Je mag de presentielijst niet beheren.', 403 );
			}
			if ( $action === 'guest_remove' ) {
				unset( $meeting['guests'][ (string) $request['person'] ] );
			} elseif ( $action === 'guest_add' ) {
				$name = sanitize_text_field( (string) $request['title'] );
				if ( $name === '' || strlen( $name ) > 200 ) {
					return $this->error( 'Vul de naam van de aanwezige in.', 400 );
				}
				$key                       = wp_generate_uuid4();
				$meeting['guests'][ $key ] = [
					'id'   => $key,
					'name' => $name,
				];
			} else {
				$id = (int) $request['person'];
				if ( get_post_type( $id ) !== 'person' && ! isset( $meeting['attendance'][ $id ] ) ) {
					return $this->error( 'Persoon niet gevonden.', 404 );
				}
				if ( rest_sanitize_boolean( $request['present'] ) ) {
					$meeting['attendance'][ $id ] = [
						'at' => time(),
						'by' => get_current_user_id(),
					];
				} else {
					unset( $meeting['attendance'][ $id ] );
				}
			}
			return [ 'ok' => true ];
		}
		if ( isset( $meeting['publication']['root'] ) ) {
			return $this->error( 'Bewerk en publiceer deze ALV-pagina’s in WordPress.' );
		}
		return $this->page( $meeting, (int) $row->id, $request, $rights, $action );
	}

	private function turnout( int $id, string $question ): ?int {
		global $wpdb;
		$table = $this->table( 'participation' );
		$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE meeting_id = %d AND question_id = %s", $id, $question ) );
		return $count === null ? null : (int) $count;
	}

	private function manage( array &$meeting, $row, $request, string $action ) {
		if ( $action === 'reschedule' ) {
			$starts = $this->start_time( $request['starts'] );
			if ( $row->frozen || time() >= (int) $row->starts || $starts <= time() ) {
				return $this->error( 'Kies vóór aanvang een toekomstige starttijd.' );
			}
			$row->starts = $starts;
			return [ 'ok' => true ];
		}
		$key      = (string) $request['question'];
		$question = $meeting['questions'][ $key ] ?? null;
		if ( $action === 'edit_question' ) {
			if ( ! $question ) {
				return $this->error( 'Stemvraag niet gevonden.', 404 );
			}
			$editable = $question['state'] === 'draft' || ( $question['state'] === 'open' && time() < (int) $row->starts );
			if ( ! $editable || $this->turnout( (int) $row->id, $key ) !== 0 ) {
				return $this->error( 'Deze stemvraag kan niet meer worden aangepast.' );
			}
			if ( (string) $request['version'] !== ( $question['revision'] ?? '' ) ) {
				return $this->error( 'De stemvraag is gewijzigd. Vernieuw de pagina en probeer opnieuw.' );
			}
		}
		if ( $action === 'question' || $action === 'edit_question' ) {
			$title   = sanitize_text_field( (string) $request['title'] );
			$options = array_values( array_filter( array_map( 'sanitize_text_field', explode( "\n", (string) $request['options'] ) ), 'strlen' ) );
			if ( $title === '' || count( $options ) < 2 || count( $options ) > 30 || count( array_unique( $options ) ) !== count( $options ) ) {
				return $this->error( 'Vul een vraag en 2 tot 30 verschillende antwoordopties in.', 400 );
			}
			$question = array_replace(
				$action === 'edit_question' ? $question : [ 'state' => 'draft' ],
				[
					'title'    => $title,
					'options'  => $options,
					'secret'   => rest_sanitize_boolean( $request['secret'] ),
					'revision' => wp_generate_uuid4(),
				]
			);
			$meeting['questions'][ $action === 'edit_question' ? $key : wp_generate_uuid4() ] = $question;
			return [ 'ok' => true ];
		}
		if ( ! $question ) {
			return $this->error( 'Stemvraag niet gevonden.', 404 );
		}
		if ( $action === 'move_question' ) {
			$offset = [
				'up'   => -1,
				'down' => 1,
			][ (string) $request['direction'] ] ?? null;
			if ( $offset === null ) {
				return $this->error( 'Kies omhoog of omlaag.', 400 );
			}
			$keys        = array_keys( $meeting['questions'] );
			$index       = array_search( $key, $keys, true );
			$destination = $index + $offset;
			if ( isset( $keys[ $destination ] ) ) {
				[ $keys[ $index ], $keys[ $destination ] ] = [ $keys[ $destination ], $keys[ $index ] ];
				$questions                                 = [];
				foreach ( $keys as $id ) {
					$questions[ $id ] = $meeting['questions'][ $id ];
				}
				$meeting['questions'] = $questions;
			}
			return [ 'ok' => true ];
		}
		if ( $action === 'draft_question' ) {
			if ( $question['state'] !== 'open' || time() >= (int) $row->starts || $this->turnout( (int) $row->id, $key ) !== 0 ) {
				return $this->error( 'Alleen een open stemvraag zonder uitgebrachte stemmen kan terug naar concept.' );
			}
			$meeting['questions'][ $key ]['state']    = 'draft';
			$meeting['questions'][ $key ]['revision'] = wp_generate_uuid4();
			return [ 'ok' => true ];
		}

		if ( $action === 'delete_question' ) {
			if ( $question['state'] !== 'draft' ) {
				return $this->error( 'Alleen conceptvragen kunnen worden verwijderd.' );
			}
			unset( $meeting['questions'][ $key ] );
			return [ 'ok' => true ];
		}

		return $this->error( 'Onbekende stemvraagactie.', 400 );
	}

	private function vote( array $meeting, $row, $request, int $person ) {
		global $wpdb;
		$key      = (string) $request['question'];
		$question = $meeting['questions'][ $key ] ?? null;
		$table    = $this->table( 'participation' );
		if ( $person && $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM $table WHERE meeting_id = %d AND question_id = %s AND person_id = %d", $row->id, $key, $person ) ) ) {
			return [ 'received' => true ];
		}
		if ( ! $question || $question['state'] !== 'open' || time() >= (int) $row->starts ) {
			return $this->error( 'De stemming is gesloten.' );
		}
		if ( ! $person || ! $this->is_member( $person, wp_date( 'Y-m-d' ) ) ) {
			return $this->error( 'Je hebt een actief lidmaatschap nodig om te stemmen.', 403 );
		}
		if ( (string) $request['version'] !== ( $question['revision'] ?? '' ) ) {
			return $this->error( 'De stemvraag is gewijzigd. Controleer de nieuwe opties en maak opnieuw een keuze.' );
		}
		$choice = filter_var( $request['choice'], FILTER_VALIDATE_INT );
		if ( $choice === false || ! isset( $question['options'][ $choice ] ) ) {
			return $this->error( 'Kies een geldige antwoordoptie.', 400 );
		}
		if ( time() >= (int) $row->starts ) {
			return $this->error( 'De stemming is gesloten.' );
		}
		$previous = $wpdb->suppress_errors( true );
		try {
			$ok = $wpdb->insert(
				$table,
				[
					'meeting_id'  => $row->id,
					'question_id' => $key,
					'person_id'   => $person,
					'choice'      => $question['secret'] ? null : $choice,
				]
			);
			if ( $ok && $question['secret'] ) {
				$ok = $wpdb->insert(
					$this->table( 'ballots' ),
					[
						'id'          => wp_generate_uuid4(),
						'meeting_id'  => $row->id,
						'question_id' => $key,
						'choice'      => $choice,
					]
				);
			}
		} finally {
			$wpdb->suppress_errors( $previous );
		}
		return $ok ? [ 'received' => true ] : $this->error( 'Je stem kon niet worden opgeslagen. Probeer opnieuw.', 503 );
	}

	private function page( array &$meeting, int $id, $request, array $rights, string $action ) {
		$key      = (string) $request['page'];
		$page     = $meeting['pages'][ $key ] ?? [];
		$current  = $page['current'] ?? null;
		$required = [
			'save_page' => 'edit',
			'submit'    => 'edit',
			'approve'   => 'approve',
			'return'    => 'approve',
			'publish'   => 'publish',
		];
		if ( ! isset( $required[ $action ] ) || ! $rights[ $required[ $action ] ] ) {
			return $this->error( 'Je mag deze pagina niet wijzigen.', 403 );
		}
		if ( (string) ( $current['version'] ?? '' ) !== (string) $request['version'] ) {
			return $this->error( 'De pagina is gewijzigd. Vernieuw de pagina.' );
		}
		if ( $action === 'save_page' ) {
			if ( ( $key && ! $current ) || ( $current && $current['state'] === 'review' ) ) {
				return $this->error( 'Deze pagina kan nu niet worden bewerkt.' );
			}
			$title = sanitize_text_field( (string) $request['title'] );
			$html  = (string) $request['html'];
			if ( $title === '' || strlen( $html ) > 200000 ) {
				return $this->error( 'Vul een titel in en beperk de HTML tot 200 kB.', 400 );
			}
			$current  = [
				'version' => wp_generate_uuid4(),
				'state'   => 'draft',
				'title'   => $title,
				'html'    => wp_kses_post( $html ),
			];
			$question = (string) $request['result_question'];
			if ( $question !== '' ) {
				$source = $meeting['questions'][ $question ] ?? null;
				if ( ! $rights['manage'] || ! $source || $source['state'] !== 'closed' ) {
					return $this->error( 'Kies een afgesloten stemming om de uitslag op te nemen.' );
				}
				$totals = $this->totals( $id, $question, $source );
				if ( is_wp_error( $totals ) ) {
					return $totals;
				}
				$current['result'] = [
					'title'   => $source['title'],
					'options' => $source['options'],
					'totals'  => $totals,
				];
			} elseif ( isset( $page['current']['result'] ) ) {
				$current['result'] = $page['current']['result'];
			}
			$key = $key ?: wp_generate_uuid4();
		} else {
			$transitions   = [
				'submit'  => [ 'draft', 'review' ],
				'approve' => [ 'review', 'approved' ],
				'return'  => [ 'review', 'draft' ],
				'publish' => [ 'approved', 'published' ],
			];
			[ $from, $to ] = $transitions[ $action ];
			if ( ! $current || $current['state'] !== $from ) {
				return $this->error( 'Deze versie heeft niet de juiste status.' );
			}
			$current['state']   = $to;
			$current[ $action ] = [
				'by' => get_current_user_id(),
				'at' => time(),
			];
			if ( $action === 'publish' ) {
				$page['published'] = $current;
			}
		}
		if ( isset( $page['current'] ) && $page['current']['version'] !== $current['version'] ) {
			$page['history'][ $page['current']['version'] ] = $page['current'];
		}
		$page['current']          = $current;
		$meeting['pages'][ $key ] = $page;
		return [ 'ok' => true ];
	}
}
