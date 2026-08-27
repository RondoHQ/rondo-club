<?php
/**
 * User Settings REST API Controller
 *
 * Handles per-user preferences: notification channels, dashboard settings,
 * list preferences, linked person, current user info, and password changes.
 */

namespace Rondo\REST;

use Rondo\People\ParentRelationshipService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UserSettings extends Base {
	/** User meta key storing when the feedback introduction was acknowledged. */
	private const FEEDBACK_INTRO_SEEN_META = 'rondo_feedback_intro_seen_at';

	/**
	 * Default visible columns for People list.
	 * Person name parts are regular columns so users can select and order them.
	 */
	private const DEFAULT_LIST_COLUMNS = [ 'first_name', 'last_name', 'company_name', 'characteristics', 'team', 'birthdate', 'modified' ];

	/**
	 * List preferences schema version. Bump when new default columns are added.
	 */
	private const LIST_PREFERENCES_VERSION = 5;

	/**
	 * Core columns (non-custom-field columns).
	 */
	private const CORE_LIST_COLUMNS = [
		[
			'id'    => 'first_name',
			'label' => 'Voornaam',
			'type'  => 'core',
		],
		[
			'id'    => 'last_name',
			'label' => 'Achternaam',
			'type'  => 'core',
		],
		[
			'id'    => 'company_name',
			'label' => 'Organisatie',
			'type'  => 'core',
		],
		[
			'id'    => 'characteristics',
			'label' => 'Kenmerken',
			'type'  => 'core',
		],
		[
			'id'    => 'email',
			'label' => 'E-mail',
			'type'  => 'core',
		],
		[
			'id'    => 'phone',
			'label' => 'Telefoon',
			'type'  => 'core',
		],
		[
			'id'    => 'team',
			'label' => 'Team',
			'type'  => 'core',
		],
		[
			'id'    => 'birthdate',
			'label' => 'Verjaardag',
			'type'  => 'core',
		],
		[
			'id'    => 'modified',
			'label' => 'Laatst gewijzigd',
			'type'  => 'core',
		],
		[
			'id'    => 'address',
			'label' => 'Adres',
			'type'  => 'core',
		],
		[
			'id'    => 'postal_code',
			'label' => 'Postcode',
			'type'  => 'core',
		],
		[
			'id'    => 'city',
			'label' => 'Plaats',
			'type'  => 'core',
		],
		[
			'id'    => 'country',
			'label' => 'Land',
			'type'  => 'core',
		],
	];

	/**
	 * Sportlink fields (canonical fields from the person field group synced from Sportlink).
	 */
	private const SPORTLINK_FIELDS = [
		[
			'id'    => 'knvb-id',
			'label' => 'KNVB ID',
			'type'  => 'text',
		],
		[
			'id'    => 'type-lid',
			'label' => 'Type',
			'type'  => 'text',
		],
		[
			'id'    => 'leeftijdsgroep',
			'label' => 'Leeftijdsgroep',
			'type'  => 'text',
		],
		[
			'id'    => 'lid-sinds',
			'label' => 'Lid sinds',
			'type'  => 'date',
		],
		[
			'id'    => 'lid-tot',
			'label' => 'Lid tot',
			'type'  => 'date',
		],
		[
			'id'    => 'vrijwilliger-sinds',
			'label' => 'Vrijwilliger sinds',
			'type'  => 'date',
		],
		[
			'id'    => 'datum-foto',
			'label' => 'Datum foto',
			'type'  => 'date',
		],
		[
			'id'    => 'datum-vog',
			'label' => 'Datum VOG',
			'type'  => 'date',
		],
		[
			'id'    => 'isparent',
			'label' => 'Is ouder',
			'type'  => 'true_false',
		],
		[
			'id'    => 'huidig-vrijwilliger',
			'label' => 'Huidig vrijwilliger',
			'type'  => 'true_false',
		],
		[
			'id'    => 'financiele-blokkade',
			'label' => 'Financiële blokkade',
			'type'  => 'true_false',
		],
		[
			'id'    => 'freescout-id',
			'label' => 'FreeScout ID',
			'type'  => 'number',
		],
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

		// A parent can temporarily identify themselves through an account that is
		// still linked to their child, pending the Sportlink person sync.
		register_rest_route(
			'rondo/v1',
			'/user/guardian-claim',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'claim_guardian_account' ],
				'permission_callback' => 'is_user_logged_in',
				'args'                => [
					'name' => [
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
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

		// Mark the one-time feedback introduction as seen.
		register_rest_route(
			'rondo/v1',
			'/user/feedback-intro-seen',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'mark_feedback_intro_seen' ],
				'permission_callback' => 'is_user_logged_in',
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

		// Send the current user a one-time password reset link.
		register_rest_route(
			'rondo/v1',
			'/user/password-reset',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'request_password_reset' ],
				'permission_callback' => function () {
					return is_user_logged_in();
				},
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
	/**
	 * Get dashboard settings data for a user.
	 *
	 * @param int $user_id The user ID.
	 * @return array Dashboard settings array.
	 */
	public function get_dashboard_settings_data( $user_id ) {
		$visible_cards = get_user_meta( $user_id, 'rondo_dashboard_visible_cards', true );
		if ( empty( $visible_cards ) || ! is_array( $visible_cards ) ) {
			$visible_cards = self::DEFAULT_DASHBOARD_ORDER;
		}

		$card_order = get_user_meta( $user_id, 'rondo_dashboard_card_order', true );
		if ( empty( $card_order ) || ! is_array( $card_order ) ) {
			$card_order = self::DEFAULT_DASHBOARD_ORDER;
		}

		return [
			'visible_cards' => $visible_cards,
			'card_order'    => $card_order,
		];
	}

	/**
	 * REST callback for getting dashboard settings.
	 */
	public function get_dashboard_settings() {
		return rest_ensure_response( $this->get_dashboard_settings_data( get_current_user_id() ) );
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

		$available_columns   = $this->get_available_columns_metadata();
		$valid_column_ids    = array_column( $available_columns, 'id' );
		$preferences_version = (int) get_user_meta( $user_id, 'rondo_people_list_pref_version', true );

		if ( $preferences_version < 3 ) {
			$aliases = [];
			foreach ( $available_columns as $column ) {
				if ( ! empty( $column['legacy_id'] ) && $column['legacy_id'] !== $column['id'] ) {
					$aliases[ $column['legacy_id'] ] = $column['id'];
				}
			}

			$map_identifiers = static function ( array $identifiers ) use ( $aliases ): array {
				return array_values( array_unique( array_map( static fn( $identifier ) => $aliases[ $identifier ] ?? $identifier, $identifiers ) ) );
			};
			$visible_columns = $map_identifiers( $visible_columns );
			$column_order    = is_array( $column_order ) ? $map_identifiers( $column_order ) : $column_order;

			if ( is_array( $column_widths ) ) {
				$migrated_widths = [];
				foreach ( $column_widths as $identifier => $width ) {
					$migrated_widths[ $aliases[ $identifier ] ?? $identifier ] = $width;
				}
				$column_widths = $migrated_widths;
			}

			$all_identifiers = array_merge(
				$visible_columns,
				is_array( $column_order ) ? $column_order : [],
				is_array( $column_widths ) ? array_keys( $column_widths ) : []
			);
			$unresolved      = array_values( array_unique( array_diff( $all_identifiers, $valid_column_ids ) ) );
			if ( $unresolved ) {
				update_user_meta( $user_id, 'rondo_people_list_unresolved_identifiers', $unresolved );
			} else {
				delete_user_meta( $user_id, 'rondo_people_list_unresolved_identifiers' );
			}

			update_user_meta( $user_id, 'rondo_people_list_preferences', $visible_columns );
			if ( is_array( $column_order ) ) {
				update_user_meta( $user_id, 'rondo_people_list_column_order', $column_order );
			}
			if ( is_array( $column_widths ) ) {
				update_user_meta( $user_id, 'rondo_people_list_column_widths', $column_widths );
			}
		}

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
		if ( $preferences_version < self::LIST_PREFERENCES_VERSION ) {
			if ( in_array( 'birthdate', $valid_column_ids, true ) && ! in_array( 'birthdate', $visible_columns, true ) ) {
				$visible_columns[] = 'birthdate';
			}
			if ( in_array( 'characteristics', $valid_column_ids, true ) && ! in_array( 'characteristics', $visible_columns, true ) ) {
				array_unshift( $visible_columns, 'characteristics' );
			}
			if ( $preferences_version < 5 ) {
				$visible_columns = array_values( array_diff( $visible_columns, [ 'name' ] ) );
				$column_order    = array_values( array_diff( $column_order, [ 'name', 'first_name', 'last_name', 'company_name' ] ) );
				foreach ( array_reverse( [ 'first_name', 'last_name', 'company_name' ] ) as $name_column ) {
					if ( in_array( $name_column, $valid_column_ids, true ) && ! in_array( $name_column, $visible_columns, true ) ) {
						array_unshift( $visible_columns, $name_column );
					}
					if ( in_array( $name_column, $valid_column_ids, true ) ) {
						array_unshift( $column_order, $name_column );
					}
				}
				if ( is_array( $column_widths ) && isset( $column_widths['name'] ) ) {
					$name_width                    = (int) $column_widths['name'];
					$column_widths['first_name'] ??= max( 120, (int) floor( $name_width * 0.45 ) );
					$column_widths['last_name']  ??= max( 150, (int) ceil( $name_width * 0.55 ) );
					unset( $column_widths['name'] );
					update_user_meta( $user_id, 'rondo_people_list_column_widths', $column_widths );
				}
				update_user_meta( $user_id, 'rondo_people_list_column_order', $column_order );
			}
			update_user_meta( $user_id, 'rondo_people_list_preferences', $visible_columns );
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
			$definition = \Rondo\Fields\Registry::resolve( 'person', $field['id'] );
			$columns[]  = [
				'id'             => $definition['canonical_name'],
				'legacy_id'      => $definition['storage_name'],
				'canonical_name' => $definition['canonical_name'],
				'label'          => $field['label'],
				'type'           => $field['type'],
				'custom'         => true,
			];
		}

		$manager       = new \Rondo\CustomFields\Manager();
		$custom_fields = $manager->get_fields( 'person', false );

		foreach ( $custom_fields as $field ) {
			$columns[] = [
				'id'             => $field['canonical_name'],
				'legacy_id'      => $field['storage_key'],
				'canonical_name' => $field['canonical_name'],
				'label'          => $field['label'],
				'type'           => $field['type'],
				'custom'         => true,
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
				$first_name = \Rondo\Fields\Fields::get_for_post( $person_id, 'first_name' ) ?: '';
				$last_name  = \Rondo\Fields\Fields::get_for_post( $person_id, 'last_name' ) ?: '';
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
		$user_id           = get_current_user_id();
		$person_id         = $request->get_param( 'person_id' );
		$current_person_id = (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );

		// Provisioned members cannot move or remove their identity link themselves.
		// Administrators can repair links through the user-management workflow.
		if ( $current_person_id > 0 && (int) $person_id !== $current_person_id && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'permission_denied',
				__( 'Your linked person can only be changed by an administrator.', 'rondo' ),
				[ 'status' => 403 ]
			);
		}

		// Handle unlinking
		if ( ! $person_id || $person_id === 0 ) {
			delete_user_meta( $user_id, 'rondo_linked_person_id' );
			// Keep the forward provisioning marker as a duplicate-account guard.
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

		if ( (int) $person->post_author !== $user_id && ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'permission_denied',
				__( 'You can only link to your own person records.', 'rondo' ),
				[ 'status' => 403 ]
			);
		}

		update_user_meta( $user_id, 'rondo_linked_person_id', (int) $person_id );
		update_post_meta( (int) $person_id, \Rondo\Users\UserProvisioning::META_USER_ID, $user_id );

		$first_name = \Rondo\Fields\Fields::get_for_post( $person_id, 'first_name' ) ?: '';
		$last_name  = \Rondo\Fields\Fields::get_for_post( $person_id, 'last_name' ) ?: '';
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
	 * Store a temporary parent/verzorger identity on the current child account.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function claim_guardian_account( $request ) {
		$user_id   = get_current_user_id();
		$person_id = (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );
		$result    = \Rondo\Users\GuardianAccountService::claim(
			$user_id,
			$person_id,
			(string) $request->get_param( 'name' )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Get current user info.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	/**
	 * Get current user data as an array.
	 *
	 * @param int $user_id The user ID.
	 * @return array|null User data array, or null if user not found.
	 */
	public function get_current_user_data( $user_id ) {
		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return null;
		}

		$avatar_url = get_avatar_url( $user_id, [ 'size' => 96 ] );
		$is_admin   = current_user_can( 'manage_options' );

		// Keep the role and route definitions server-side. The dedicated Kaderlijst
		// role is an extra WordPress role, but deliberately not a general kader role.
		$has_extra_roles = \Rondo\Core\UserRoles::has_extra_staff_role( $user_id );
		$is_kader        = \Rondo\Core\UserRoles::is_kader( $user_id );

		$person_id           = (int) get_user_meta( $user_id, 'rondo_linked_person_id', true );
		$is_parent           = $person_id ? ( new ParentRelationshipService() )->has_current_child( $person_id ) : false;
		$pending_guardian    = \Rondo\Users\GuardianAccountService::pending_for_user( $user_id );
		$linked_person_name  = null;
		$linked_person_photo = null;
		$active_functies     = [];
		if ( $person_id ) {
			$person = get_post( $person_id );
			if ( $person && $person->post_type === 'person' ) {
				$first               = \Rondo\Fields\Fields::get_for_post( $person_id, 'first_name' ) ?: '';
				$infix               = \Rondo\Fields\Fields::get_for_post( $person_id, 'infix' ) ?: '';
				$last                = \Rondo\Fields\Fields::get_for_post( $person_id, 'last_name' ) ?: '';
				$linked_person_name  = implode( ' ', array_filter( [ $first, $infix, $last ] ) ) ?: null;
				$linked_person_photo = get_the_post_thumbnail_url( $person_id, 'thumbnail' ) ?: null;

				$work_history = \Rondo\Fields\Fields::get_for_post( $person_id, 'work_history' ) ?: [];
				foreach ( $work_history as $job ) {
					if ( ! empty( $job['is_current'] ) && ! empty( $job['job_title'] ) ) {
						$active_functies[] = $job['job_title'];
					}
				}
			}
		}

		return [
			'id'                            => $user_id,
			'name'                          => $user->display_name,
			'email'                         => \Rondo\Users\UserProvisioning::contact_email( $user_id ) ?: $user->user_email,
			'avatar_url'                    => $avatar_url,
			'is_admin'                      => $is_admin,
			'has_extra_roles'               => $has_extra_roles,
			'is_kader'                      => $is_kader,
			'can_access_kaderlijst'         => \Rondo\Core\UserRoles::can_access_kaderlijst( $user_id ),
			'is_sponsor'                    => $person_id ? \Rondo\Core\SponsorStatus::is_sponsor( $person_id ) : false,
			'is_parent'                     => $is_parent,
			'can_edit_people'               => \Rondo\Core\AccessControl::can_edit_people(),
			'can_edit_person_contact'       => \Rondo\Core\AccessControl::can_edit_person_contact(),
			'can_manage_sponsors'           => \Rondo\Core\AccessControl::can_manage_sponsors(),
			'can_access_narrowcasting'      => \Rondo\Config\FeatureToggles::can_access( 'narrowcasting' ) && ( current_user_can( 'narrowcasting' ) || current_user_can( 'sponsorbeheer' ) || $is_admin ),
			'can_manage_narrowcasting'      => \Rondo\Config\FeatureToggles::can_access( 'narrowcasting' ) && ( current_user_can( 'narrowcasting' ) || $is_admin ),
			'can_manage_accommodatie'       => \Rondo\Config\FeatureToggles::can_access( 'rooms' ) && ( current_user_can( 'accommodatiebeheer' ) || $is_admin ),
			'can_manage_tournaments'        => \Rondo\Tournaments\TournamentAccess::can_manage( $user_id ),
			'has_tournament_assignments'    => \Rondo\Tournaments\TournamentAccess::has_assignments( $user_id ),
			'can_access_fairplay'           => current_user_can( 'fairplay' ),
			'can_access_vog'                => current_user_can( 'vog' ),
			'can_access_financieel'         => \Rondo\Core\UserRoles::can_view_finances(),
			'can_edit_financieel'           => \Rondo\Core\UserRoles::can_manage_finances(),
			'can_edit_commissie_info'       => \Rondo\Core\UserRoles::can_manage_commissie_info(),
			'can_access_toegangscontrole'   => current_user_can( 'toegangscontrole' ),
			'can_access_clothing'           => \Rondo\Config\FeatureToggles::can_access( 'clothing' ) && ( current_user_can( 'manage_clothing' ) || current_user_can( 'manage_options' ) ),
			'can_access_ledenadministratie' => current_user_can( 'ledenadministratie' ) || current_user_can( 'manage_options' ),
			'can_access_vrijwilligers'      => current_user_can( 'vrijwilligers' ) || current_user_can( 'manage_options' ),
			'can_access_person_notes'       => \Rondo\Core\AccessControl::can_access_person_notes(),
			'permitted_age_groups'          => \Rondo\Core\AccessControl::get_permitted_age_groups(),
			'profile_url'                   => admin_url( 'profile.php' ),
			'admin_url'                     => admin_url(),
			'linked_person_id'              => $person_id ?: null,
			'linked_person_name'            => $linked_person_name,
			'active_functies'               => $active_functies,
			'linked_person_photo'           => $linked_person_photo,
			'pending_guardian'              => $pending_guardian,
			'feedback_intro_seen'           => get_user_meta( $user_id, self::FEEDBACK_INTRO_SEEN_META, true ) !== '',
		];
	}

	/**
	 * REST callback for getting current user.
	 *
	 * @param \WP_REST_Request $request The request object.
	 */
	public function get_current_user( $request ) {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return new \WP_Error( 'not_logged_in', __( 'User is not logged in.', 'rondo' ), [ 'status' => 401 ] );
		}

		$data = $this->get_current_user_data( $user_id );

		if ( ! $data ) {
			return new \WP_Error( 'user_not_found', __( 'User not found.', 'rondo' ), [ 'status' => 404 ] );
		}

		return rest_ensure_response( $data );
	}

	/**
	 * Mark the feedback introduction as acknowledged for the current account.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function mark_feedback_intro_seen() {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return new \WP_Error( 'not_logged_in', __( 'User is not logged in.', 'rondo' ), [ 'status' => 401 ] );
		}

		update_user_meta( $user_id, self::FEEDBACK_INTRO_SEEN_META, current_time( 'mysql', true ) );

		return rest_ensure_response( [ 'feedback_intro_seen' => true ] );
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

		return rest_ensure_response(
			[
				'success' => true,
				'message' => 'Wachtwoord succesvol gewijzigd. Log opnieuw in.',
			]
		);
	}

	/**
	 * Send the current user a one-time link for setting a password.
	 *
	 * The link is sent through WordPress core so its normal reset-key security and
	 * expiry apply. ContactEmailRouter reroutes household placeholder addresses to
	 * the user's real contact address.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function request_password_reset() {
		$user_id = get_current_user_id();
		$user    = get_userdata( $user_id );

		if ( ! $user ) {
			return new \WP_Error( 'user_not_found', 'Gebruiker niet gevonden.', [ 'status' => 404 ] );
		}

		if ( $user->user_login === 'demo' ) {
			return new \WP_Error( 'demo_user', 'Wachtwoord wijzigen is niet beschikbaar in de demo.', [ 'status' => 403 ] );
		}

		if ( ! \Rondo\Users\UserProvisioning::contact_email( $user_id ) ) {
			return new \WP_Error(
				'no_contact_email',
				'Er is geen bruikbaar e-mailadres aan dit account gekoppeld.',
				[ 'status' => 422 ]
			);
		}

		$result = retrieve_password( $user->user_login );
		if ( is_wp_error( $result ) ) {
			return new \WP_Error(
				'password_reset_email_failed',
				'De e-mail kon niet worden verstuurd. Probeer het later opnieuw.',
				[ 'status' => 500 ]
			);
		}

		return rest_ensure_response(
			[
				'success' => true,
				'message' => 'Controleer je e-mail voor een link om je wachtwoord in te stellen.',
			]
		);
	}
}
