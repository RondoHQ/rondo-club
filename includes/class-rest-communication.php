<?php
/**
 * REST API for the shared communication planner.
 */

namespace Rondo\REST;

use Rondo\Core\UserRoles;
use Rondo\Config\ClubConfig;
use Rondo\Fields\Fields;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Communication extends Base {
	private const ITEM_TYPE    = 'rondo_comm_item';
	private const SERIES_TYPE  = 'rondo_comm_series';
	private const COMMENT_TYPE = 'rondo_communication_note';
	private const STATUSES     = [ 'concept', 'preparing', 'ready', 'sent', 'skipped', 'cancelled', 'paused' ];
	private const RECURRENCES  = [ 'none', 'monthly', 'yearly' ];
	private const COPY_FIELDS  = [ 'description', 'channel_ids', 'google_docs_url', 'audience', 'assignee_id', 'attachments' ];

	public function __construct() {
		parent::__construct();
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route(
			'rondo/v1',
			'/communications',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'index' ],
					'permission_callback' => [ $this, 'can_access' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create' ],
					'permission_callback' => [ $this, 'can_access' ],
				],
			]
			);

		register_rest_route(
			'rondo/v1',
			'/communications/(?P<id>\d+)',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'show' ],
					'permission_callback' => [ $this, 'can_access_item' ],
				],
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update' ],
					'permission_callback' => [ $this, 'can_access_item' ],
				],
			]
			);

		register_rest_route(
			'rondo/v1',
			'/communications/(?P<id>\d+)/action',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'action' ],
				'permission_callback' => [ $this, 'can_access_item' ],
			]
			);

		register_rest_route(
			'rondo/v1',
			'/communications/(?P<id>\d+)/comments',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'comments' ],
					'permission_callback' => [ $this, 'can_access_item' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'add_comment' ],
					'permission_callback' => [ $this, 'can_access_item' ],
				],
			]
			);

		register_rest_route(
			'rondo/v1',
			'/communications/(?P<id>\d+)/attachments',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'upload' ],
				'permission_callback' => [ $this, 'can_access_item' ],
			]
			);

		register_rest_route(
			'rondo/v1',
			'/communications/(?P<id>\d+)/attachments/(?P<key>[a-f0-9]{32})',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'download' ],
				'permission_callback' => [ $this, 'can_access_item' ],
			]
			);

		register_rest_route(
			'rondo/v1',
			'/communication-series/(?P<id>\d+)/action',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'series_action' ],
				'permission_callback' => [ $this, 'can_access_series' ],
			]
			);
	}

	public function can_access() {
		return $this->check_user_approved() && UserRoles::can_access_section( 'communicatie' );
	}

	public function can_access_item( $request ) {
		return $this->can_access() && self::ITEM_TYPE === get_post_type( (int) $request['id'] ) && get_post_status( (int) $request['id'] ) === 'publish';
	}

	public function can_access_series( $request ) {
		return $this->can_access() && self::SERIES_TYPE === get_post_type( (int) $request['id'] );
	}

	public function index( $request = null ) {
		if ( ! $request || ! $request->get_param( 'read_only' ) ) {
			$this->expand_active_series();
		}
		$posts = get_posts(
			[
				'post_type'        => self::ITEM_TYPE,
				'post_status'      => 'publish',
				'posts_per_page'   => -1,
				'suppress_filters' => false,
				'orderby'          => 'date',
				'order'            => 'DESC',
			]
			);

		$users = get_users(
			[
				'orderby' => 'display_name',
				'order'   => 'ASC',
			]
			);
		$users = array_values( array_filter( $users, static fn( $user ) => UserRoles::can_access_section( 'communicatie', $user->ID ) ) );

		return rest_ensure_response(
			[
				'channels' => ClubConfig::get_communication_channels(),
				'items'    => array_map( [ $this, 'format_item' ], $posts ),
				'users'    => array_map(
				static fn( $user ) => [
					'id'   => $user->ID,
					'name' => $user->display_name,
				],
				$users
				),
			]
			);
	}

	public function show( $request ) {
		return rest_ensure_response( $this->format_item( get_post( (int) $request['id'] ), true ) );
	}

	public function create( $request ) {
		$data = $request->get_json_params();
		if ( ! in_array( $data['status'] ?? 'concept', [ 'concept', 'preparing', 'ready' ], true ) ) {
			return new \WP_Error( 'invalid_status', 'Nieuwe items beginnen met een open status.', [ 'status' => 400 ] );
		}
		$error = $this->validate_payload( $data );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		$recurrence = sanitize_key( $data['recurrence'] ?? 'none' );
		if ( $recurrence !== 'none' ) {
			$series_id = wp_insert_post(
				[
					'post_type'   => self::SERIES_TYPE,
					'post_status' => 'publish',
					'post_title'  => sanitize_text_field( $data['title'] ),
					'post_author' => get_current_user_id(),
				],
				true
				);
			if ( is_wp_error( $series_id ) ) {
				return $series_id;
			}
			$this->save_series( $series_id, $data );
			$this->audit( $series_id, 'series_created', [ 'recurrence' => $recurrence ] );
			$this->expand_series( $series_id );
			$item = get_posts(
				[
					'post_type'      => self::ITEM_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'meta_key'       => 'series_id',
					'meta_value'     => $series_id,
					'orderby'        => 'meta_value',
					'order'          => 'ASC',
				]
				);
			return new \WP_REST_Response( $this->format_item( $item[0], true ), 201 );
		}

		$item_id = $this->insert_item( $data );
		if ( is_wp_error( $item_id ) ) {
			return $item_id;
		}
		$this->audit( $item_id, 'created' );
		return new \WP_REST_Response( $this->format_item( get_post( $item_id ), true ), 201 );
	}

	private function update_item( $request ) {
		$id      = (int) $request['id'];
		$data    = $request->get_json_params();
		$current = $this->format_item( get_post( $id ), true );
		if ( ! empty( $data['version'] ) && $data['version'] !== $current['modified_gmt'] ) {
			return new \WP_Error( 'communication_conflict', 'Dit item is intussen door iemand anders gewijzigd. Vernieuw de pagina en probeer opnieuw.', [ 'status' => 409 ] );
		}

		if ( isset( $data['status'] ) && $data['status'] !== $current['status'] && ( ! in_array( $current['status'], [ 'concept', 'preparing', 'ready' ], true ) || ! in_array( $data['status'], [ 'concept', 'preparing', 'ready' ], true ) ) ) {
			return new \WP_Error( 'invalid_status_transition', 'Gebruik de kanaalcheckboxes of de bijbehorende actie om dit item af te handelen.', [ 'status' => 400 ] );
		}
		if ( isset( $data['channel'] ) && ! isset( $data['channel_ids'] ) ) {
			$data['channel_ids'] = [ $data['channel'] ];
		}
		$merged                   = array_merge( $current, $data );
		$merged['series_id']      = $current['series_id'];
		$merged['occurrence_key'] = Fields::get_for_post( $id, 'occurrence_key' ) ?: '';
		foreach ( $current['channels'] as $channel ) {
			if ( ! empty( $channel['actual_date'] ) && is_array( $merged['channel_ids'] ) && ! in_array( $channel['channel_id'], $merged['channel_ids'], true ) ) {
				return new \WP_Error( 'completed_channel', 'Maak het afvinken eerst ongedaan voordat je een afgerond kanaal verwijdert.', [ 'status' => 400 ] );
			}
		}

		$error = $this->validate_payload( $merged, $current['channel_ids'] );
		if ( is_wp_error( $error ) ) {
			return $error;
		}

		wp_update_post(
			[
				'ID'         => $id,
				'post_title' => sanitize_text_field( $merged['title'] ),
			]
			);
		$this->save_item_meta( $id, $merged );
		$this->sync_completion( $id, $this->channel_rows( $id ) );
		$changed = [];
		foreach ( [ 'title', 'description', 'channel_ids', 'google_docs_url', 'audience', 'planned_date', 'assignee_id', 'status', 'published_url', 'attachments' ] as $field ) {
			if ( ( $current[ $field ] ?? null ) !== ( $merged[ $field ] ?? null ) ) {
				$changed[] = $field;
			}
		}
		$this->audit( $id, 'updated', [ 'fields' => $changed ] );

		if ( ! empty( $data['apply_to_future'] ) && ! empty( $current['series_id'] ) ) {
			$this->update_future_items( (int) $current['series_id'], $id, $merged );
		}

		return rest_ensure_response( $this->format_item( get_post( $id ), true ) );
	}

	private function item_action( $request ) {
		$id     = (int) $request['id'];
		$data   = $request->get_json_params();
		$action = sanitize_key( $data['action'] ?? '' );
		$item   = $this->format_item( get_post( $id ), true );

		switch ( $action ) {
			case 'complete':
			case 'reopen':
				if ( ! in_array( $item['status'], [ 'concept', 'preparing', 'ready', 'sent' ], true ) ) {
					return new \WP_Error( 'item_closed', 'Herstel of hervat dit item voordat je een kanaal afvinkt.', [ 'status' => 400 ] );
				}
				$channel_id = $data['channel_id'] ?? ( count( $item['channel_ids'] ) === 1 ? $item['channel_ids'][0] : '' );
				if ( ! in_array( $channel_id, $item['channel_ids'], true ) ) {
					return new \WP_Error( 'invalid_channel', 'Kies een kanaal van dit item.', [ 'status' => 400 ] );
				}
				$rows = $this->channel_rows( $id );
				foreach ( $rows as &$row ) {
					if ( $row['channel_id'] !== $channel_id || ( ( ! empty( $row['actual_date'] ) ) === ( $action === 'complete' ) && ! isset( $data['actual_date'] ) && ! isset( $data['published_url'] ) ) ) {
						continue;
					}
					$original_row = $row;
					if ( $action === 'complete' ) {
						$error = $this->validate_payload( array_merge( $item, [ 'status' => 'sent' ] ), $item['channel_ids'] );
						if ( is_wp_error( $error ) ) {
							return $error;
						}
						$actual_date = $data['actual_date'] ?? ( $row['actual_date'] ?: wp_date( 'Y-m-d' ) );
						if ( ! is_string( $actual_date ) || ! $this->valid_date( $actual_date ) || $actual_date > wp_date( 'Y-m-d' ) ) {
							return new \WP_Error( 'invalid_actual_date', 'De werkelijke datum moet vandaag of eerder zijn.', [ 'status' => 400 ] );
						}
						$row['actual_date']   = $actual_date;
						$row['completed_by']  = $row['completed_by'] ?: get_current_user_id();
						$row['published_url'] = esc_url_raw( $data['published_url'] ?? $row['published_url'] ?? '' );
					} else {
						$row = $this->selection_rows( [ $channel_id ] )[0];
					}
					if ( $row === $original_row ) {
						continue;
					}
					$this->audit(
						$id,
						$action === 'complete' ? 'channel_completed' : 'channel_reopened',
						[
							'channel_id'  => $channel_id,
							'actual_date' => $row['actual_date'],
						]
						);
				}
				unset( $row );
				Fields::update_for_post( $id, 'channels', $rows );
				$this->sync_completion( $id, $rows );
				wp_update_post( [ 'ID' => $id ] );
				break;
			case 'skip':
			case 'cancel':
				update_post_meta( $id, 'previous_open_status', $item['status'] );
					update_post_meta( $id, 'status', $action === 'skip' ? 'skipped' : 'cancelled' );
				update_post_meta( $id, 'status_reason', sanitize_textarea_field( $data['reason'] ?? '' ) );
				$this->audit( $id, $action . 'ped', [ 'reason' => sanitize_textarea_field( $data['reason'] ?? '' ) ] );
				break;
			case 'restore':
				update_post_meta( $id, 'status', get_post_meta( $id, 'previous_open_status', true ) ?: 'concept' );
				delete_post_meta( $id, 'status_reason' );
				$this->audit( $id, 'restored' );
				break;
			case 'duplicate':
				$copy                 = $item;
				$copy['attachments']  = get_post_meta( $id, 'attachments', true ) ?: [];
				$copy['title']        = $item['title'] . ' (kopie)';
				$copy['status']       = 'concept';
				$copy['planned_date'] = '';
				$copy['assignee_id']  = get_current_user_id();
				$copy['recurrence']   = 'none';
				$new_id               = $this->insert_item( $copy );
				$this->audit( $new_id, 'duplicated', [ 'source_id' => $id ] );
				return new \WP_REST_Response( $this->format_item( get_post( $new_id ), true ), 201 );
			default:
				return new \WP_Error( 'invalid_action', 'Onbekende actie.', [ 'status' => 400 ] );
		}

		return rest_ensure_response( $this->format_item( get_post( $id ), true ) );
	}

	public function comments( $request ) {
		$comments = get_comments(
			[
				'post_id' => (int) $request['id'],
				'type'    => self::COMMENT_TYPE,
				'status'  => 'approve',
				'order'   => 'ASC',
			]
			);
		return rest_ensure_response( array_map( [ $this, 'format_comment' ], $comments ) );
	}

	public function add_comment( $request ) {
		$content = sanitize_textarea_field( $request->get_param( 'content' ) );
		if ( trim( $content ) === '' ) {
			return new \WP_Error( 'empty_comment', 'Vul een opmerking in.', [ 'status' => 400 ] );
		}
		$user = wp_get_current_user();
		$id   = wp_insert_comment(
			[
				'comment_post_ID'      => (int) $request['id'],
				'comment_content'      => $content,
				'comment_type'         => self::COMMENT_TYPE,
				'comment_approved'     => 1,
				'user_id'              => $user->ID,
				'comment_author'       => $user->display_name,
				'comment_author_email' => $user->user_email,
			]
			);
		$this->audit( (int) $request['id'], 'comment_added' );
		return new \WP_REST_Response( $this->format_comment( get_comment( $id ) ), 201 );
	}

	public function upload( $request ) {
		$id = (int) $request['id'];
		if ( empty( $_FILES['file'] ) ) {
			return new \WP_Error( 'missing_file', 'Kies een afbeelding.', [ 'status' => 400 ] );
		}
		$file = $_FILES['file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( (int) $file['size'] > 10 * MB_IN_BYTES ) {
			return new \WP_Error( 'file_too_large', 'Een afbeelding mag maximaal 10 MB zijn.', [ 'status' => 400 ] );
		}
		$check = wp_check_filetype_and_ext(
			$file['tmp_name'],
			$file['name'],
			[
				'jpg|jpeg' => 'image/jpeg',
				'png'      => 'image/png',
				'webp'     => 'image/webp',
			]
			);
		if ( empty( $check['type'] ) ) {
			return new \WP_Error( 'invalid_file_type', 'Alleen JPEG, PNG en WebP zijn toegestaan.', [ 'status' => 400 ] );
		}
		$attachments = get_post_meta( $id, 'attachments', true );
		$attachments = is_array( $attachments ) ? $attachments : [];
		if ( count( $attachments ) >= 10 ) {
			return new \WP_Error( 'too_many_files', 'Een item kan maximaal 10 afbeeldingen bevatten.', [ 'status' => 400 ] );
		}
		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'rondo-communication';
		wp_mkdir_p( $dir );
		$deny_file = trailingslashit( $dir ) . '.htaccess';
		if ( ! file_exists( $deny_file ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $deny_file, "Require all denied\nDeny from all\n" );
		}
		$key      = md5( wp_generate_uuid4() );
		$filename = $key . '.' . ( $check['type'] === 'image/jpeg' ? 'jpg' : ( $check['type'] === 'image/png' ? 'png' : 'webp' ) );
		$path     = trailingslashit( $dir ) . $filename;
		if ( ! move_uploaded_file( $file['tmp_name'], $path ) ) {
			return new \WP_Error( 'upload_failed', 'De afbeelding kon niet worden opgeslagen.', [ 'status' => 500 ] );
		}
		$attachments[] = [
			'key'  => $key,
			'name' => sanitize_file_name( $file['name'] ),
			'type' => $check['type'],
			'path' => $path,
			'alt'  => '',
		];
		update_post_meta( $id, 'attachments', $attachments );
		$this->audit( $id, 'attachment_added', [ 'name' => sanitize_file_name( $file['name'] ) ] );
		return new \WP_REST_Response( $this->format_item( get_post( $id ), true )['attachments'], 201 );
	}

	public function download( $request ) {
		$attachments = get_post_meta( (int) $request['id'], 'attachments', true );
		$key         = sanitize_key( $request['key'] );
		foreach ( is_array( $attachments ) ? $attachments : [] as $attachment ) {
			if ( hash_equals( $attachment['key'], $key ) && is_readable( $attachment['path'] ) ) {
				header( 'Content-Type: ' . $attachment['type'] );
				header( 'Content-Disposition: inline; filename="' . sanitize_file_name( $attachment['name'] ) . '"' );
				readfile( $attachment['path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
				exit;
			}
		}
		return new \WP_Error( 'attachment_not_found', 'Afbeelding niet gevonden.', [ 'status' => 404 ] );
	}

	public function series_action( $request ) {
		$id     = (int) $request['id'];
		$action = sanitize_key( $request->get_param( 'action' ) );
		if ( ! in_array( $action, [ 'pause', 'resume', 'end' ], true ) ) {
			return new \WP_Error( 'invalid_action', 'Onbekende reeksactie.', [ 'status' => 400 ] );
		}
		update_post_meta( $id, 'series_status', $action === 'resume' ? 'active' : ( $action === 'pause' ? 'paused' : 'ended' ) );
		$items = get_posts(
			[
				'post_type'      => self::ITEM_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_key'       => 'series_id',
				'meta_value'     => $id,
			]
			);
		foreach ( $items as $item ) {
			$planned_date = $this->wire_date( get_post_meta( $item->ID, 'planned_date', true ) );
			$status       = get_post_meta( $item->ID, 'status', true );
			if ( $planned_date < wp_date( 'Y-m-d' ) ) {
				continue;
			}
			if ( $action === 'pause' && in_array( $status, [ 'concept', 'preparing', 'ready' ], true ) ) {
				update_post_meta( $item->ID, 'previous_open_status', $status );
				update_post_meta( $item->ID, 'status', 'paused' );
			} elseif ( $action === 'resume' && $status === 'paused' ) {
				update_post_meta( $item->ID, 'status', get_post_meta( $item->ID, 'previous_open_status', true ) ?: 'concept' );
			} elseif ( $action === 'end' && in_array( $status, [ 'concept', 'preparing', 'ready', 'paused' ], true ) ) {
				update_post_meta( $item->ID, 'previous_open_status', $status === 'paused' ? 'concept' : $status );
				update_post_meta( $item->ID, 'status', 'cancelled' );
				update_post_meta( $item->ID, 'status_reason', 'Reeks beëindigd' );
			}
		}
		if ( $action === 'resume' ) {
			$this->expand_series( $id );
		}
		if ( $action === 'end' ) {
			update_post_meta( $id, 'end_date', sanitize_text_field( $request->get_param( 'end_date' ) ?: wp_date( 'Y-m-d' ) ) );
		}
		$this->audit( $id, 'series_' . $action );
		return rest_ensure_response(
			[
				'success' => true,
				'status'  => get_post_meta( $id, 'series_status', true ),
			]
			);
	}


	/** Lazily read legacy single-channel records without changing production data. */
	private function channel_rows( int $id ): array {
		$rows = Fields::get_for_post( $id, 'channels' );
		if ( is_array( $rows ) && $rows ) {
			foreach ( $rows as &$row ) {
				if ( array_key_exists( 'actual_date', $row ) ) {
					$row['actual_date'] = $this->wire_date( $row['actual_date'] );
				}
			}
			unset( $row );
			return $rows;
		}
		$legacy = Fields::get_for_post( $id, 'channel' );
		if ( ! $legacy ) {
			return [];
		}
		$sent = get_post_type( $id ) === self::ITEM_TYPE && Fields::get_for_post( $id, 'status' ) === 'sent';
		return [
			[
				'channel_id'    => $legacy,
				'actual_date'   => $sent ? $this->wire_date( Fields::get_for_post( $id, 'actual_date' ) ) : '',
				'completed_by'  => $sent ? (int) get_post_meta( $id, 'completed_by', true ) : 0,
				'published_url' => $sent ? ( Fields::get_for_post( $id, 'published_url' ) ?: '' ) : '',
			],
		];
	}

	private function selected_channels( array $data ): array {
		return $data['channel_ids'] ?? ( empty( $data['channel'] ) ? [] : [ $data['channel'] ] );
	}

	/** Retain completion only for channels that remain on this occurrence. */
	private function selection_rows( array $ids, array $existing = [] ): array {
		$existing = array_column( $existing, null, 'channel_id' );
		return array_map(
			static fn( $id ) => $existing[ $id ] ?? [
				'channel_id'    => $id,
				'actual_date'   => '',
				'completed_by'  => 0,
				'published_url' => '',
			],
			$ids
			);
	}

	/** Overall completion is always derived from the channel checklist. */
	private function sync_completion( int $id, array $rows ): void {
		$status = Fields::get_for_post( $id, 'status' );
		if ( in_array( $status, [ 'cancelled', 'skipped', 'paused' ], true ) ) {
			return;
		}
		$dates    = array_filter( array_column( $rows, 'actual_date' ) );
		$complete = count( $rows ) > 0 && count( $dates ) === count( $rows );
		if ( $complete && $status !== 'sent' ) {
			update_post_meta( $id, 'previous_open_status', $status );
		}
		$open_status = get_post_meta( $id, 'previous_open_status', true );
		if ( ! in_array( $open_status, [ 'concept', 'preparing', 'ready' ], true ) ) {
			$open_status = 'ready';
		}
		Fields::update_many_for_post(
			$id,
			[
				'status'      => $complete ? 'sent' : ( $status === 'sent' ? $open_status : $status ),
				'actual_date' => $complete ? max( $dates ) : '',
			]
			);
	}

	/** Serialize checklist edits so two channel completions cannot overwrite each other. */
	private function with_item_lock( $request, string $method ) {
		$key = 'rondo_comm_edit_' . (int) $request['id'];
		if ( ! add_option( $key, time(), '', false ) ) {
			return new \WP_Error( 'communication_busy', 'Dit item wordt al bijgewerkt. Probeer het opnieuw.', [ 'status' => 409 ] );
		}
		try {
			return $this->$method( $request );
		} finally {
			delete_option( $key );
		}
	}

	public function update( $request ) {
		return $this->with_item_lock( $request, 'update_item' );
	}

	public function action( $request ) {
		return $this->with_item_lock( $request, 'item_action' );
	}

	private function validate_payload( array $data, array $retained = [] ) {
		if ( trim( (string) ( $data['title'] ?? '' ) ) === '' ) {
			return new \WP_Error(
				'title_required',
				'Titel is verplicht.',
				[
					'status' => 400,
					'field'  => 'title',
				]
				);
		}
		$ids         = $data['channel_ids'] ?? ( isset( $data['channel'] ) ? [ $data['channel'] ] : [] );
		$definitions = array_column( ClubConfig::get_communication_channels(), null, 'id' );
		if ( ! is_array( $ids ) || ! $ids || count( $ids ) > 100 ) {
			return new \WP_Error(
				'channel_required',
				'Kies minimaal één kanaal.',
				[
					'status' => 400,
					'field'  => 'channel_ids',
				]
				);
		}
		$seen = [];
		foreach ( $ids as $id ) {
			if ( ! is_string( $id ) || isset( $seen[ $id ] ) || ( ! in_array( $id, $retained, true ) && empty( $definitions[ $id ]['active'] ) ) ) {
				return new \WP_Error(
					'invalid_channel',
					'Kies geldige, actieve kanalen zonder dubbelen.',
					[
						'status' => 400,
						'field'  => 'channel_ids',
					]
					);
			}
			$seen[ $id ] = true;
		}
		$status = $data['status'] ?? 'concept';
		if ( ! in_array( $status, self::STATUSES, true ) ) {
			return new \WP_Error( 'invalid_status', 'Ongeldige status.', [ 'status' => 400 ] );
		}
		if ( ! in_array( $data['recurrence'] ?? 'none', self::RECURRENCES, true ) ) {
			return new \WP_Error( 'invalid_recurrence', 'Ongeldige herhaling.', [ 'status' => 400 ] );
		}
		if ( $status !== 'concept' && ( empty( trim( (string) ( $data['description'] ?? '' ) ) ) || empty( $data['planned_date'] ) || empty( $data['audience'] ) || empty( $data['assignee_id'] ) ) ) {
			return new \WP_Error( 'incomplete_item', 'Beschrijving, datum, verantwoordelijke en doelgroep zijn verplicht vanaf In voorbereiding.', [ 'status' => 400 ] );
		}
		if ( ! empty( $data['assignee_id'] ) && ! UserRoles::can_access_section( 'communicatie', (int) $data['assignee_id'] ) ) {
			return new \WP_Error(
				'invalid_assignee',
				'De gekozen verantwoordelijke heeft geen toegang tot Communicatie.',
				[
					'status' => 400,
					'field'  => 'assignee_id',
				]
				);
		}
		foreach ( [ 'planned_date', 'start_date', 'end_date' ] as $field ) {
			if ( ! empty( $data[ $field ] ) && ! $this->valid_date( $data[ $field ] ) ) {
				return new \WP_Error(
					'invalid_date',
					'Gebruik een geldige datum.',
					[
						'status' => 400,
						'field'  => $field,
					]
					);
			}
		}
		if ( ( $data['recurrence'] ?? 'none' ) !== 'none' && empty( $data['series_id'] ) && empty( $data['start_date'] ) ) {
			return new \WP_Error( 'start_date_required', 'Kies een startdatum voor de reeks.', [ 'status' => 400 ] );
		}
		if ( ! empty( $data['start_date'] ) && ! empty( $data['end_date'] ) && $data['end_date'] < $data['start_date'] ) {
			return new \WP_Error(
				'invalid_end_date',
				'De einddatum mag niet vóór de startdatum liggen.',
				[
					'status' => 400,
					'field'  => 'end_date',
				]
				);
		}
		if ( ! empty( $data['google_docs_url'] ) && ! preg_match( '#^https://docs\.google\.com/(document|spreadsheets|presentation)/#i', $data['google_docs_url'] ) ) {
			return new \WP_Error(
				'invalid_google_docs_url',
				'Gebruik een geldige Google Docs-link.',
				[
					'status' => 400,
					'field'  => 'google_docs_url',
				]
				);
		}
		return true;
	}

	private function insert_item( array $data, int $series_id = 0, string $occurrence_key = '' ) {
		$id = wp_insert_post(
			[
				'post_type'   => self::ITEM_TYPE,
				'post_status' => 'publish',
				'post_title'  => sanitize_text_field( $data['title'] ),
				'post_author' => get_current_user_id(),
			],
			true
			);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		$data['series_id']      = $series_id;
		$data['occurrence_key'] = $occurrence_key;
		$this->save_item_meta( $id, $data );
		if ( ! empty( $data['attachments'] ) && isset( $data['attachments'][0]['path'] ) ) {
			update_post_meta( $id, 'attachments', $data['attachments'] );
		}
		return $id;
	}

	private function save_item_meta( int $id, array $data ) {
		$values = [
			'description'     => sanitize_textarea_field( $data['description'] ?? '' ),
			'channels'        => $this->selection_rows( $this->selected_channels( $data ), $this->channel_rows( $id ) ),
			'google_docs_url' => esc_url_raw( $data['google_docs_url'] ?? '' ),
			'audience'        => sanitize_text_field( $data['audience'] ?? '' ),
			'planned_date'    => sanitize_text_field( $data['planned_date'] ?? '' ),
			'assignee_id'     => absint( $data['assignee_id'] ?? get_current_user_id() ),
			'status'          => sanitize_key( $data['status'] ?? 'concept' ),
			'series_id'       => absint( $data['series_id'] ?? 0 ),
			'occurrence_key'  => sanitize_text_field( $data['occurrence_key'] ?? '' ),
		];
		if ( isset( $data['attachments'] ) && is_array( $data['attachments'] ) ) {
			$existing = get_post_meta( $id, 'attachments', true );
			$by_key   = [];
			foreach ( is_array( $existing ) ? $existing : [] as $attachment ) {
				$by_key[ $attachment['key'] ] = $attachment;
			}
			$values['attachments'] = [];
			foreach ( $data['attachments'] as $attachment ) {
				$key = sanitize_key( $attachment['key'] ?? '' );
				if ( isset( $by_key[ $key ] ) ) {
					$by_key[ $key ]['alt']   = sanitize_text_field( $attachment['alt'] ?? $by_key[ $key ]['alt'] ?? '' );
					$values['attachments'][] = $by_key[ $key ];
				}
			}
		}
		$attachments = $values['attachments'] ?? null;
		unset( $values['attachments'] );
		Fields::update_many_for_post( $id, $values );
		if ( $attachments !== null ) {
			update_post_meta( $id, 'attachments', $attachments );
		}
	}

	private function save_series( int $id, array $data ) {
		$start = sanitize_text_field( $data['start_date'] ?? $data['planned_date'] ?? '' );
		Fields::update_many_for_post(
			$id,
			[
				'recurrence'    => sanitize_key( $data['recurrence'] ),
				'start_date'    => $start,
				'end_date'      => sanitize_text_field( $data['end_date'] ?? '' ),
				'series_status' => 'active',
			]
		);
		$this->copy_template_fields( $id, $data );
	}

	private function expand_active_series() {
		$series = get_posts(
			[
				'post_type'      => self::SERIES_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_key'       => 'series_status',
				'meta_value'     => 'active',
			]
			);
		foreach ( $series as $record ) {
			$this->expand_series( $record->ID );
		}
	}

	private function expand_series( int $series_id ) {
		if ( get_post_meta( $series_id, 'series_status', true ) !== 'active' ) {
			return;
		}
		$start = $this->wire_date( get_post_meta( $series_id, 'start_date', true ) );
		if ( ! $this->valid_date( $start ) ) {
			return;
		}
		$frequency = get_post_meta( $series_id, 'recurrence', true );
		$end       = $this->wire_date( get_post_meta( $series_id, 'end_date', true ) );
		$horizon   = wp_date( 'Y-m-d', strtotime( '+12 months' ) );
		if ( $start > $horizon ) {
			$horizon = $start;
		}
		if ( $end && $end < $horizon ) {
			$horizon = $end;
		}
		$dates = $this->recurrence_dates( $start, $frequency, $horizon );
		foreach ( $dates as $date ) {
			$key      = $series_id . ':' . $date;
			$existing = get_posts(
				[
					'post_type'      => self::ITEM_TYPE,
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'meta_key'       => 'occurrence_key',
					'meta_value'     => $key,
					'fields'         => 'ids',
				]
				);
			if ( $existing ) {
				continue;
			}
			$lock_key = 'rondo_comm_occ_' . md5( $key );
			$locked   = add_option( $lock_key, time(), '', false );
			if ( ! $locked ) {
				$lock_value = (int) get_option( $lock_key );
				if ( $lock_value > 0 && $lock_value < time() - 300 ) {
					delete_option( $lock_key );
					$locked = add_option( $lock_key, time(), '', false );
				}
			}
			if ( ! $locked ) {
				continue;
			}
			$data = [
				'title'        => get_the_title( $series_id ),
				'planned_date' => $date,
				'status'       => 'concept',
			];
			foreach ( self::COPY_FIELDS as $field ) {
				$data[ $field ] = $field === 'channel_ids' ? array_column( $this->channel_rows( $series_id ), 'channel_id' ) : get_post_meta( $series_id, $field, true );
			}
			$item_id = $this->insert_item( $data, $series_id, $key );
			if ( is_wp_error( $item_id ) ) {
				delete_option( $lock_key );
				continue;
			}
			update_option( $lock_key, $item_id, false );
			$this->audit( $item_id, 'occurrence_created', [ 'series_id' => $series_id ] );
		}
	}

	private function recurrence_dates( string $start, string $frequency, string $horizon ): array {
		$parts                  = array_map( 'intval', explode( '-', $start ) );
		[ $year, $month, $day ] = $parts;
		$dates                  = [];
		for ( $i = 0; $i < 240; $i++ ) {
			if ( $frequency === 'monthly' ) {
				$total        = ( $year * 12 + $month - 1 ) + $i;
				$target_year  = intdiv( $total, 12 );
				$target_month = $total % 12 + 1;
			} else {
				$target_year  = $year + $i;
				$target_month = $month;
			}
			$target_day = min( $day, cal_days_in_month( CAL_GREGORIAN, $target_month, $target_year ) );
			$date       = sprintf( '%04d-%02d-%02d', $target_year, $target_month, $target_day );
			if ( $date > $horizon ) {
				break;
			}
			$dates[] = $date;
		}
		return $dates;
	}

	private function update_future_items( int $series_id, int $current_id, array $data ) {
		$posts = get_posts(
			[
				'post_type'      => self::ITEM_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'meta_key'       => 'series_id',
				'meta_value'     => $series_id,
			]
			);
		foreach ( $posts as $post ) {
			if ( $post->ID === $current_id || $this->wire_date( get_post_meta( $post->ID, 'planned_date', true ) ) < wp_date( 'Y-m-d' ) || ! in_array( get_post_meta( $post->ID, 'status', true ), [ 'concept', 'preparing', 'ready' ], true ) ) {
				continue;
			}
			if ( array_filter( $this->channel_rows( $post->ID ), static fn( $row ) => ! empty( $row['actual_date'] ) ) ) {
				continue;
			}
			wp_update_post(
				[
					'ID'         => $post->ID,
					'post_title' => sanitize_text_field( $data['title'] ),
				]
				);
			$this->copy_template_fields( $post->ID, $data, true );
			$this->audit( $post->ID, 'updated_from_series' );
		}
		wp_update_post(
			[
				'ID'         => $series_id,
				'post_title' => sanitize_text_field( $data['title'] ),
			]
			);
		$this->copy_template_fields( $series_id, $data );
	}

	/** Copy shared template data once through the native field layer. */
	private function copy_template_fields( int $id, array $data, bool $occurrence = false ): void {
		$values = [];
		foreach ( self::COPY_FIELDS as $field ) {
			if ( $field === 'attachments' ) {
				update_post_meta( $id, $field, $data[ $field ] ?? [] );
			} elseif ( $field === 'channel_ids' ) {
				$values['channels'] = $occurrence
					? $this->selection_rows( $this->selected_channels( $data ), $this->channel_rows( $id ) )
					: array_map( static fn( $channel_id ) => [ 'channel_id' => $channel_id ], $this->selected_channels( $data ) );
			} else {
				$values[ $field ] = $data[ $field ] ?? '';
			}
		}
		Fields::update_many_for_post( $id, $values );
		if ( $occurrence ) {
			$this->sync_completion( $id, $values['channels'] );
		}
	}

	public function format_item( $post, bool $full = false ): array {
		$fields                = Fields::all_for_post( $post->ID );
		$rows                  = $this->channel_rows( $post->ID );
		$definitions           = array_column( ClubConfig::get_communication_channels(), null, 'id' );
		$assignee_id           = (int) ( $fields['assignee_id'] ?? 0 );
		$series_id             = (int) ( $fields['series_id'] ?? 0 );
		$attachments           = get_post_meta( $post->ID, 'attachments', true );
		$formatted_attachments = [];
		foreach ( is_array( $attachments ) ? $attachments : [] as $attachment ) {
			$formatted_attachments[] = [
				'key'  => $attachment['key'],
				'name' => $attachment['name'],
				'type' => $attachment['type'],
				'alt'  => $attachment['alt'] ?? '',
				'url'  => rest_url( 'rondo/v1/communications/' . $post->ID . '/attachments/' . $attachment['key'] ) . '?_wpnonce=' . wp_create_nonce( 'wp_rest' ),
			];
		}
		$data = [
			'id'              => $post->ID,
			'title'           => $post->post_title,
			'description'     => $fields['description'] ?? '',
			'channel'         => $rows[0]['channel_id'] ?? '',
			'channel_ids'     => array_column( $rows, 'channel_id' ),
			'channels'        => array_map(
				static fn( $row ) => array_merge(
				$row,
				[
					'label'  => $definitions[ $row['channel_id'] ]['label'] ?? $row['channel_id'],
					'active' => $definitions[ $row['channel_id'] ]['active'] ?? false,
				]
				),
				$rows
				),
			'google_docs_url' => $fields['google_docs_url'] ?? '',
			'audience'        => $fields['audience'] ?? '',
			'planned_date'    => $this->wire_date( $fields['planned_date'] ?? '' ),
			'actual_date'     => $this->wire_date( $fields['actual_date'] ?? '' ),
			'published_url'   => $fields['published_url'] ?? '',
			'assignee_id'     => $assignee_id,
			'assignee_name'   => $assignee_id ? ( get_userdata( $assignee_id )->display_name ?? 'Onbekend' ) : '',
			'status'          => $fields['status'] ?? 'concept',
			'status_reason'   => get_post_meta( $post->ID, 'status_reason', true ),
			'series_id'       => $series_id,
			'recurrence'      => $series_id ? get_post_meta( $series_id, 'recurrence', true ) : 'none',
			'series_status'   => $series_id ? ( get_post_meta( $series_id, 'series_status', true ) ?: 'active' ) : '',
			'attachments'     => $formatted_attachments,
			'author_name'     => get_the_author_meta( 'display_name', $post->post_author ),
			'modified_gmt'    => get_post_modified_time( 'c', true, $post ),
		];
		if ( $full ) {
			$data['audit'] = get_post_meta( $post->ID, 'audit_log', true ) ?: [];
		}
		return $data;
	}

	private function format_comment( $comment ): array {
		return [
			'id'      => (int) $comment->comment_ID,
			'content' => $comment->comment_content,
			'author'  => $comment->comment_author,
			'date'    => mysql_to_rfc3339( $comment->comment_date_gmt ),
		];
	}

	private function audit( int $post_id, string $event, array $details = [] ) {
		$log   = get_post_meta( $post_id, 'audit_log', true );
		$log   = is_array( $log ) ? $log : [];
		$user  = wp_get_current_user();
		$log[] = [
			'event'     => $event,
			'details'   => $details,
			'user_id'   => $user->ID,
			'user_name' => $user->display_name,
			'date'      => current_time( 'c', true ),
		];
		update_post_meta( $post_id, 'audit_log', array_slice( $log, -200 ) );
	}

	private function valid_date( string $date ): bool {
		$value = \DateTimeImmutable::createFromFormat( '!Y-m-d', $date );
		return $value && $value->format( 'Y-m-d' ) === $date;
	}

	private function wire_date( $date ): string {
		$date = (string) $date;
		if ( preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $date, $matches ) ) {
			return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
		}
		return $date;
	}
}
