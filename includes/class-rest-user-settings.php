<?php
/**
 * User Settings REST API Controller
 *
 * Handles per-user preferences: notification channels, dashboard settings,
 * list preferences, linked person, current user info, and password changes.
 */

namespace Rondo\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UserSettings extends Base {

	/**
	 * Default visible columns for People list.
	 * Name column is always visible and first — not included here.
	 */
	private const DEFAULT_LIST_COLUMNS = [ 'team', 'birthdate', 'modified' ];

	/**
	 * List preferences schema version. Bump when new default columns are added.
	 */
	private const LIST_PREFERENCES_VERSION = 2;

	/**
	 * Core columns (non-custom-field columns).
	 */
	private const CORE_LIST_COLUMNS = [
		[ 'id' => 'email', 'label' => 'E-mail', 'type' => 'core' ],
		[ 'id' => 'phone', 'label' => 'Telefoon', 'type' => 'core' ],
		[ 'id' => 'team', 'label' => 'Team', 'type' => 'core' ],
		[ 'id' => 'birthdate', 'label' => 'Verjaardag', 'type' => 'core' ],
		[ 'id' => 'modified', 'label' => 'Laatst gewijzigd', 'type' => 'core' ],
	];

	/**
	 * Sportlink fields (ACF fields from the person field group synced from Sportlink).
	 */
	private const SPORTLINK_FIELDS = [
		[ 'id' => 'knvb-id', 'label' => 'KNVB ID', 'type' => 'text' ],
		[ 'id' => 'type-lid', 'label' => 'Type lid', 'type' => 'text' ],
		[ 'id' => 'leeftijdsgroep', 'label' => 'Leeftijdsgroep', 'type' => 'text' ],
		[ 'id' => 'lid-sinds', 'label' => 'Lid sinds', 'type' => 'date' ],
		[ 'id' => 'vrijwilliger-sinds', 'label' => 'Vrijwilliger sinds', 'type' => 'date' ],
		[ 'id' => 'datum-foto', 'label' => 'Datum foto', 'type' => 'date' ],
		[ 'id' => 'datum-vog', 'label' => 'Datum VOG', 'type' => 'date' ],
		[ 'id' => 'isparent', 'label' => 'Is ouder', 'type' => 'true_false' ],
		[ 'id' => 'huidig-vrijwilliger', 'label' => 'Huidig vrijwilliger', 'type' => 'true_false' ],
		[ 'id' => 'financiele-blokkade', 'label' => 'Financiële blokkade', 'type' => 'true_false' ],
		[ 'id' => 'freescout-id', 'label' => 'FreeScout ID', 'type' => 'number' ],
	];

	/**
	 * Valid dashboard card IDs.
	 */
	private const VALID_DASHBOARD_CARDS = [
		'stats',
		'reminders',
		'anniversaries',
		'todos',
		'awaiting',
		'meetings',
		'recent-contacted',
		'recent-edited',
	];

	/**
	 * Default dashboard card order.
	 */
	private const DEFAULT_DASHBOARD_ORDER = [
		'stats',
		'reminders',
		'anniversaries',
		'todos',
		'awaiting',
		'meetings',
		'recent-contacted',
		'recent-edited',
	];

	/**
	 * Get Sportlink field definitions for use by other classes.
	 *
	 * @return array Sportlink field definitions.
	 */
	public static function get_sportlink_fields(): array {
		return self::SPORTLINK_FIELDS;
	}

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST routes for user settings.
	 */
	public function register_routes() {
		// Get user notification channels
		register_rest_route(
			'rondo/v1',
			'/user/notification-channels',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_notification_channels' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);

		// Update user notification channels
		register_rest_route(
			'rondo/v1',
			'/user/notification-channels',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_notification_channels' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'channels' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return is_array( $param );
						},
					],
				],
			]
		);

		// Update notification time
		register_rest_route(
			'rondo/v1',
			'/user/notification-time',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_notification_time' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'time' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $param );
						},
					],
				],
			]
		);

		// Update mention notification preference
		register_rest_route(
			'rondo/v1',
			'/user/mention-notifications',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_mention_notifications' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'preference' => [
						'required'          => true,
						'validate_callback' => function ( $param ) {
							return in_array( $param, [ 'digest', 'immediate', 'never' ], true );
						},
					],
				],
			]
		);

		// Get user dashboard settings
		register_rest_route(
			'rondo/v1',
			'/user/dashboard-settings',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_dashboard_settings' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);

		// Update user dashboard settings
		register_rest_route(
			'rondo/v1',
			'/user/dashboard-settings',
			[
				'methods'             => 'PATCH',
				'callback'            => [ $this, 'update_dashboard_settings' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'visible_cards' => [
						'required'          => false,
						'validate_callback' => [ $this, 'validate_dashboard_cards' ],
					],
					'card_order'    => [
						'required'          => false,
						'validate_callback' => [ $this, 'validate_dashboard_cards' ],
					],
				],
			]
		);

		// Get user's people list preferences
		register_rest_route(
			'rondo/v1',
			'/user/list-preferences',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_list_preferences' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);

		// Update user's people list preferences
		register_rest_route(
			'rondo/v1',
			'/user/list-preferences',
			[
				'methods'             => 'PATCH',
				'callback'            => [ $this, 'update_list_preferences' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'visible_columns' => [
						'required'          => false,
						'validate_callback' => function ( $param ) {
							return $param === null || is_array( $param );
						},
					],
					'column_order'    => [
						'required'          => false,
						'validate_callback' => function ( $param ) {
							return $param === null || is_array( $param );
						},
					],
					'column_widths'   => [
						'required'          => false,
						'validate_callback' => function ( $param ) {
							return $param === null || is_object( $param ) || is_array( $param );
						},
					],
					'reset'           => [
						'required'          => false,
						'validate_callback' => function ( $param ) {
							return is_bool( $param );
						},
					],
				],
			]
		);

		// Get user's linked person ID
		register_rest_route(
			'rondo/v1',
			'/user/linked-person',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_linked_person' ],
				'permission_callback' => 'is_user_logged_in',
			]
		);

		// Update user's linked person ID
		register_rest_route(
			'rondo/v1',
			'/user/linked-person',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_linked_person' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'person_id' => [
						'required'          => false,
						'validate_callback' => function ( $param ) {
							return $param === null || $param === 0 || ( is_numeric( $param ) && $param > 0 );
						},
					],
				],
			]
		);

		// Current user info
		register_rest_route(
			'rondo/v1',
			'/user/me',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_current_user' ],
				'permission_callback' => function () {
					return is_user_logged_in();
				},
			]
		);

		// Change password
		register_rest_route(
			'rondo/v1',
			'/user/password',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'change_password' ],
				'permission_callback' => function () {
					return is_user_logged_in();
				},
				'args'                => [
					'current_password' => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => function ( $v ) {
							return $v;
						},
					],
					'new_password'     => [
						'required'          => true,
						'type'              => 'string',
						'sanitize_callback' => function ( $v ) {
							return $v;
						},
					],
				],
			]
		);
	}

	/**
	 * Get user's notification channel preferences.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function get_notification_channels( $request ) {
		$user_id = get_current_user_id();

		$channels = get_user_meta( $user_id, 'rondo_notification_channels', true );
		if ( ! is_array( $channels ) ) {
			$channels = [ 'email' ];
		}
		$channels = array_values( array_intersect( $channels, [ 'email' ] ) );
		if ( empty( $channels ) ) {
			$channels = [ 'email' ];
		}

		$notification_time = get_user_meta( $user_id, 'rondo_notification_time', true );
		if ( empty( $notification_time ) ) {
			$notification_time = '09:00';
		}

		$mention_notifications = get_user_meta( $user_id, 'rondo_mention_notifications', true );
		if ( empty( $mention_notifications ) ) {
			$mention_notifications = 'digest';
		}

		return rest_ensure_response(
			[
				'channels'              => $channels,
				'notification_time'     => $notification_time,
				'mention_notifications' => $mention_notifications,
			]
		);
	}

	/**
	 * Update user's notification channel preferences.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function update_notification_channels( $request ) {
		$user_id  = get_current_user_id();
		$channels = $request->get_param( 'channels' );

		$valid_channels = [ 'email' ];
		$channels       = array_intersect( $channels, $valid_channels );

		update_user_meta( $user_id, 'rondo_notification_channels', $channels );

		return rest_ensure_response(
			[
				'success'  => true,
				'channels' => $channels,
			]
		);
	}

	/**
	 * Update user's notification time preference.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function update_notification_time( $request ) {
		$user_id = get_current_user_id();
		$time    = $request->get_param( 'time' );

		if ( ! preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time ) ) {
			return new \WP_Error(
				'invalid_time',
				__( 'Invalid time format. Please use HH:MM format (e.g., 09:00).', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		update_user_meta( $user_id, 'rondo_notification_time', $time );

		// Reschedule user's reminder cron job at the new time.
		$reminders       = new \Rondo\Collaboration\Reminders();
		$schedule_result = $reminders->schedule_user_reminder( $user_id );

		if ( is_wp_error( $schedule_result ) ) {
			return rest_ensure_response(
				[
					'success'           => true,
					'notification_time' => $time,
					'message'           => __( 'Notification time updated, but failed to reschedule cron job.', 'rondo' ),
					'cron_error'        => $schedule_result->get_error_message(),
				]
			);
		}

		return rest_ensure_response(
			[
				'success'           => true,
				'notification_time' => $time,
				'message'           => __( 'Notification time updated and cron job rescheduled successfully.', 'rondo' ),
			]
		);
	}

	/**
	 * Update user's mention notification preference.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_mention_notifications( $request ) {
		$user_id    = get_current_user_id();
		$preference = sanitize_text_field( $request->get_param( 'preference' ) );

		$valid_preferences = [ 'digest', 'immediate', 'never' ];
		if ( ! in_array( $preference, $valid_preferences, true ) ) {
			return new \WP_Error(
				'invalid_preference',
				__( 'Invalid mention notification preference.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		update_user_meta( $user_id, 'rondo_mention_notifications', $preference );

		return rest_ensure_response(
			[
				'success'               => true,
				'mention_notifications' => $preference,
			]
		);
	}

	/**
	 * Validate dashboard cards array.
	 *
	 * @param mixed $param The parameter value.
	 * @return bool
	 */
	public function validate_dashboard_cards( $param ) {
		if ( ! is_array( $param ) ) {
			return false;
		}

		foreach ( $param as $card ) {
			if ( ! in_array( $card, self::VALID_DASHBOARD_CARDS, true ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get user's dashboard settings.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_dashboard_settings() {
		$user_id = get_current_user_id();

		$visible_cards = get_user_meta( $user_id, 'rondo_dashboard_visible_cards', true );
		if ( empty( $visible_cards ) || ! is_array( $visible_cards ) ) {
			$visible_cards = self::DEFAULT_DASHBOARD_ORDER;
		}

		$card_order = get_user_meta( $user_id, 'rondo_dashboard_card_order', true );
		if ( empty( $card_order ) || ! is_array( $card_order ) ) {
			$card_order = self::DEFAULT_DASHBOARD_ORDER;
		}

		return rest_ensure_response(
			[
				'visible_cards' => $visible_cards,
				'card_order'    => $card_order,
			]
		);
	}

	/**
	 * Update user's dashboard settings.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_dashboard_settings( $request ) {
		$user_id = get_current_user_id();

		$visible_cards = $request->get_param( 'visible_cards' );
		$card_order    = $request->get_param( 'card_order' );

		if ( $visible_cards !== null ) {
			$visible_cards = array_values( array_intersect( $visible_cards, self::VALID_DASHBOARD_CARDS ) );
			update_user_meta( $user_id, 'rondo_dashboard_visible_cards', $visible_cards );
		}

		if ( $card_order !== null ) {
			$card_order = array_values( array_unique( array_intersect( $card_order, self::VALID_DASHBOARD_CARDS ) ) );
			update_user_meta( $user_id, 'rondo_dashboard_card_order', $card_order );
		}

		$updated_visible = get_user_meta( $user_id, 'rondo_dashboard_visible_cards', true );
		if ( empty( $updated_visible ) || ! is_array( $updated_visible ) ) {
			$updated_visible = self::DEFAULT_DASHBOARD_ORDER;
		}

		$updated_order = get_user_meta( $user_id, 'rondo_dashboard_card_order', true );
		if ( empty( $updated_order ) || ! is_array( $updated_order ) ) {
			$updated_order = self::DEFAULT_DASHBOARD_ORDER;
		}

		return rest_ensure_response(
			[
				'visible_cards' => $updated_visible,
				'card_order'    => $updated_order,
			]
		);
	}

	/**
	 * Get user's people list column preferences.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function get_list_preferences( $request ) {
		$user_id = get_current_user_id();

		$visible_columns = get_user_meta( $user_id, 'rondo_people_list_preferences', true );
		$column_order    = get_user_meta( $user_id, 'rondo_people_list_column_order', true );
		$column_widths   = get_user_meta( $user_id, 'rondo_people_list_column_widths', true );

		if ( empty( $visible_columns ) || ! is_array( $visible_columns ) ) {
			$visible_columns = self::DEFAULT_LIST_COLUMNS;
		}

		$available_columns = $this->get_available_columns_metadata();
		$valid_column_ids  = array_column( $available_columns, 'id' );

		$visible_columns = array_values( array_intersect( $visible_columns, $valid_column_ids ) );

		if ( empty( $visible_columns ) ) {
			$visible_columns = self::DEFAULT_LIST_COLUMNS;
			delete_user_meta( $user_id, 'rondo_people_list_preferences' );
		}

		if ( empty( $column_order ) || ! is_array( $column_order ) ) {
			$column_order = array_column( $available_columns, 'id' );
		} else {
			$column_order = array_values( array_intersect( $column_order, $valid_column_ids ) );
			if ( empty( $column_order ) ) {
				$column_order = array_column( $available_columns, 'id' );
			}
		}

		// Ensure new columns are appended for users with older saved column_order.
		$ordered_set = array_fill_keys( $column_order, true );
		foreach ( $valid_column_ids as $column_id ) {
			if ( ! isset( $ordered_set[ $column_id ] ) ) {
				$column_order[] = $column_id;
			}
		}

		if ( empty( $column_widths ) || ! is_array( $column_widths ) ) {
			$column_widths = new \stdClass();
		}

		// One-time migration: append new default columns for legacy preference sets.
		$preferences_version = (int) get_user_meta( $user_id, 'rondo_people_list_pref_version', true );
		if ( $preferences_version < self::LIST_PREFERENCES_VERSION ) {
			if ( in_array( 'birthdate', $valid_column_ids, true ) && ! in_array( 'birthdate', $visible_columns, true ) ) {
				$visible_columns[] = 'birthdate';
				update_user_meta( $user_id, 'rondo_people_list_preferences', $visible_columns );
			}
			update_user_meta( $user_id, 'rondo_people_list_pref_version', self::LIST_PREFERENCES_VERSION );
		}

		return rest_ensure_response(
			[
				'visible_columns'   => $visible_columns,
				'column_order'      => $column_order,
				'column_widths'     => $column_widths,
				'available_columns' => $available_columns,
			]
		);
	}

	/**
	 * Update user's people list column preferences.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response
	 */
	public function update_list_preferences( $request ) {
		$user_id = get_current_user_id();

		// Handle reset action
		if ( $request->get_param( 'reset' ) === true ) {
			delete_user_meta( $user_id, 'rondo_people_list_preferences' );
			delete_user_meta( $user_id, 'rondo_people_list_column_order' );
			delete_user_meta( $user_id, 'rondo_people_list_column_widths' );
			update_user_meta( $user_id, 'rondo_people_list_pref_version', self::LIST_PREFERENCES_VERSION );

			$available_columns = $this->get_available_columns_metadata();

			return rest_ensure_response(
				[
					'visible_columns'   => self::DEFAULT_LIST_COLUMNS,
					'column_order'      => array_column( $available_columns, 'id' ),
					'column_widths'     => new \stdClass(),
					'available_columns' => $available_columns,
					'reset'             => true,
				]
			);
		}

		$available_columns = $this->get_available_columns_metadata();
		$valid_columns     = array_column( $available_columns, 'id' );

		// Handle visible_columns update
		$visible_columns = $request->get_param( 'visible_columns' );
		if ( $visible_columns !== null ) {
			if ( ! is_array( $visible_columns ) || count( $visible_columns ) === 0 ) {
				delete_user_meta( $user_id, 'rondo_people_list_preferences' );
			} else {
				$validated_columns = array_values( array_intersect( $visible_columns, $valid_columns ) );

				if ( count( $validated_columns ) !== count( $visible_columns ) ) {
					error_log(
						sprintf(
							'Rondo: Filtered %d invalid column IDs from user %d visible_columns preferences',
							count( $visible_columns ) - count( $validated_columns ),
							$user_id
						)
					);
				}

				update_user_meta( $user_id, 'rondo_people_list_preferences', $validated_columns );
				update_user_meta( $user_id, 'rondo_people_list_pref_version', self::LIST_PREFERENCES_VERSION );
			}
		}

		// Handle column_order update
		$column_order = $request->get_param( 'column_order' );
		if ( $column_order !== null ) {
			if ( ! is_array( $column_order ) || count( $column_order ) === 0 ) {
				delete_user_meta( $user_id, 'rondo_people_list_column_order' );
			} else {
				$validated_order = array_values( array_intersect( $column_order, $valid_columns ) );

				if ( count( $validated_order ) !== count( $column_order ) ) {
					error_log(
						sprintf(
							'Rondo: Filtered %d invalid column IDs from user %d column_order preferences',
							count( $column_order ) - count( $validated_order ),
							$user_id
						)
					);
				}

				if ( count( $validated_order ) > 0 ) {
					update_user_meta( $user_id, 'rondo_people_list_column_order', $validated_order );
				} else {
					delete_user_meta( $user_id, 'rondo_people_list_column_order' );
				}
			}
		}

		// Handle column_widths update
		$column_widths = $request->get_param( 'column_widths' );
		if ( $column_widths !== null ) {
			$widths_array = (array) $column_widths;

			if ( count( $widths_array ) === 0 ) {
				delete_user_meta( $user_id, 'rondo_people_list_column_widths' );
			} else {
				$validated_widths = [];
				foreach ( $widths_array as $column_id => $width ) {
					if ( in_array( $column_id, $valid_columns, true ) && is_numeric( $width ) && (int) $width > 0 ) {
						$validated_widths[ $column_id ] = (int) $width;
					}
				}

				if ( count( $validated_widths ) !== count( $widths_array ) ) {
					error_log(
						sprintf(
							'Rondo: Filtered %d invalid entries from user %d column_widths preferences',
							count( $widths_array ) - count( $validated_widths ),
							$user_id
						)
					);
				}

				if ( count( $validated_widths ) > 0 ) {
					update_user_meta( $user_id, 'rondo_people_list_column_widths', $validated_widths );
				} else {
					delete_user_meta( $user_id, 'rondo_people_list_column_widths' );
				}
			}
		}

		// Return current state
		$stored_visible = get_user_meta( $user_id, 'rondo_people_list_preferences', true );
		$stored_order   = get_user_meta( $user_id, 'rondo_people_list_column_order', true );
		$stored_widths  = get_user_meta( $user_id, 'rondo_people_list_column_widths', true );

		if ( empty( $stored_visible ) || ! is_array( $stored_visible ) ) {
			$stored_visible = self::DEFAULT_LIST_COLUMNS;
		}
		if ( empty( $stored_order ) || ! is_array( $stored_order ) ) {
			$stored_order = array_column( $available_columns, 'id' );
		} else {
			$valid_column_ids = array_column( $available_columns, 'id' );
			$stored_order     = array_values( array_intersect( $stored_order, $valid_column_ids ) );
			$ordered_set      = array_fill_keys( $stored_order, true );
			foreach ( $valid_column_ids as $column_id ) {
				if ( ! isset( $ordered_set[ $column_id ] ) ) {
					$stored_order[] = $column_id;
				}
			}
		}
		if ( empty( $stored_widths ) || ! is_array( $stored_widths ) ) {
			$stored_widths = new \stdClass();
		}

		return rest_ensure_response(
			[
				'visible_columns'   => $stored_visible,
				'column_order'      => $stored_order,
				'column_widths'     => $stored_widths,
				'available_columns' => $available_columns,
			]
		);
	}

	/**
	 * Get metadata for all available columns.
	 *
	 * @return array Column definitions with id, label, type, custom flag.
	 */
	private function get_available_columns_metadata(): array {
		$columns = [];

		$columns = array_merge( $columns, self::CORE_LIST_COLUMNS );

		foreach ( self::SPORTLINK_FIELDS as $field ) {
			$columns[] = [
				'id'     => $field['id'],
				'label'  => $field['label'],
				'type'   => $field['type'],
				'custom' => true,
			];
		}

		$manager       = new \Rondo\CustomFields\Manager();
		$custom_fields = $manager->get_fields( 'person', false );

		foreach ( $custom_fields as $field ) {
			$columns[] = [
				'id'     => $field['name'],
				'label'  => $field['label'],
				'type'   => $field['type'],
				'custom' => true,
			];
		}

		return $columns;
	}

	/**
	 * Get user's linked person ID.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_linked_person() {
		$user_id   = get_current_user_id();
		$person_id = (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );

		$response = [
			'person_id' => $person_id ?: null,
		];

		if ( $person_id ) {
			$person = get_post( $person_id );
			if ( $person && $person->post_type === 'person' && $person->post_status === 'publish' ) {
				$first_name = get_field( 'first_name', $person_id ) ?: '';
				$last_name  = get_field( 'last_name', $person_id ) ?: '';
				$thumbnail  = get_the_post_thumbnail_url( $person_id, 'thumbnail' );

				$response['person'] = [
					'id'        => $person_id,
					'name'      => trim( $first_name . ' ' . $last_name ),
					'thumbnail' => $thumbnail ?: null,
				];
			} else {
				$response['person_id'] = null;
			}
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Update user's linked person ID.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_linked_person( $request ) {
		$user_id   = get_current_user_id();
		$person_id = $request->get_param( 'person_id' );

		// Handle unlinking
		if ( ! $person_id || $person_id === 0 ) {
			$old_person_id = (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );
			delete_user_meta( $user_id, 'rondo_linked_person_id' );
			if ( $old_person_id ) {
				delete_post_meta( $old_person_id, \Rondo\Users\UserProvisioning::META_USER_ID );
			}
			return rest_ensure_response(
				[
					'success'   => true,
					'person_id' => null,
					'message'   => __( 'Person link removed.', 'rondo' ),
				]
			);
		}

		$person = get_post( (int) $person_id );
		if ( ! $person || $person->post_type !== 'person' || $person->post_status !== 'publish' ) {
			return new \WP_Error(
				'invalid_person',
				__( 'Invalid person ID.', 'rondo' ),
				[ 'status' => 400 ]
			);
		}

		if ( $person->post_author != $user_id && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'permission_denied',
				__( 'You can only link to your own person records.', 'rondo' ),
				[ 'status' => 403 ]
			);
		}

		update_user_meta( $user_id, 'rondo_linked_person_id', (int) $person_id );
		update_post_meta( (int) $person_id, \Rondo\Users\UserProvisioning::META_USER_ID, $user_id );

		$first_name = get_field( 'first_name', $person_id ) ?: '';
		$last_name  = get_field( 'last_name', $person_id ) ?: '';
		$thumbnail  = get_the_post_thumbnail_url( $person_id, 'thumbnail' );

		return rest_ensure_response(
			[
				'success'   => true,
				'person_id' => (int) $person_id,
				'person'    => [
					'id'        => (int) $person_id,
					'name'      => trim( $first_name . ' ' . $last_name ),
					'thumbnail' => $thumbnail ?: null,
				],
				'message'   => __( 'Person linked successfully.', 'rondo' ),
			]
		);
	}

	/**
	 * Get current user info.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_current_user( $request ) {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return new \WP_Error( 'not_logged_in', __( 'User is not logged in.', 'rondo' ), [ 'status' => 401 ] );
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return new \WP_Error( 'user_not_found', __( 'User not found.', 'rondo' ), [ 'status' => 404 ] );
		}

		$avatar_url = get_avatar_url( $user_id, [ 'size' => 96 ] );
		$is_admin   = current_user_can( 'manage_options' );

		$person_id           = (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );
		$linked_person_name  = null;
		$linked_person_photo = null;
		$active_functies     = [];
		if ( $person_id ) {
			$person = get_post( $person_id );
			if ( $person && 'person' === $person->post_type ) {
				$first               = get_field( 'first_name', $person_id ) ?: '';
				$infix               = get_field( 'infix', $person_id ) ?: '';
				$last                = get_field( 'last_name', $person_id ) ?: '';
				$linked_person_name  = implode( ' ', array_filter( [ $first, $infix, $last ] ) ) ?: null;
				$linked_person_photo = get_the_post_thumbnail_url( $person_id, 'thumbnail' ) ?: null;

				$work_history = get_field( 'work_history', $person_id ) ?: [];
				foreach ( $work_history as $job ) {
					if ( ! empty( $job['is_current'] ) && ! empty( $job['job_title'] ) ) {
						$active_functies[] = $job['job_title'];
					}
				}
			}
		}

		return rest_ensure_response(
			[
				'id'                          => $user_id,
				'name'                        => $user->display_name,
				'email'                       => $user->user_email,
				'avatar_url'                  => $avatar_url,
				'is_admin'                    => $is_admin,
				'can_edit_people'             => \Rondo\Core\AccessControl::can_edit_people(),
				'can_access_fairplay'         => current_user_can( 'fairplay' ),
				'can_access_vog'              => current_user_can( 'vog' ),
				'can_access_financieel'       => current_user_can( 'financieel' ),
				'can_access_toegangscontrole' => current_user_can( 'toegangscontrole' ),
				'can_access_clothing'         => current_user_can( 'manage_clothing' ) || current_user_can( 'manage_options' ),
				'permitted_age_groups'        => \Rondo\Core\AccessControl::get_permitted_age_groups(),
				'profile_url'                 => admin_url( 'profile.php' ),
				'admin_url'                   => admin_url(),
				'linked_person_name'          => $linked_person_name,
				'active_functies'             => $active_functies,
				'linked_person_photo'         => $linked_person_photo,
			]
		);
	}

	/**
	 * Change the current user's password.
	 *
	 * @param \WP_REST_Request $request The REST request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function change_password( $request ) {
		$user_id          = get_current_user_id();
		$user             = get_userdata( $user_id );
		$current_password = $request->get_param( 'current_password' );
		$new_password     = $request->get_param( 'new_password' );

		// Demo guard
		if ( $user->user_login === 'demo' ) {
			return new \WP_Error( 'demo_user', 'Wachtwoord wijzigen is niet beschikbaar in de demo.', [ 'status' => 403 ] );
		}

		if ( ! wp_check_password( $current_password, $user->user_pass, $user_id ) ) {
			return new \WP_Error( 'wrong_password', 'Huidig wachtwoord is onjuist.', [ 'status' => 400 ] );
		}

		wp_set_password( $new_password, $user_id );

		$sessions = \WP_Session_Tokens::get_instance( $user_id );
		$sessions->destroy_all();

		return rest_ensure_response( [ 'success' => true, 'message' => 'Wachtwoord succesvol gewijzigd. Log opnieuw in.' ] );
	}
}
