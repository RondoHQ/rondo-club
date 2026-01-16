<?php
/**
 * User Roles for Caelis
 *
 * Registers custom user role for Caelis users with minimal permissions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PRM_User_Roles {

	const ROLE_NAME         = 'caelis_user';
	const ROLE_DISPLAY_NAME = 'Caelis User';
	const APPROVAL_META_KEY = 'caelis_user_approved';

	public function __construct() {
		// Register role on theme activation
		add_action( 'after_switch_theme', [ $this, 'register_role' ] );

		// Remove role on theme deactivation
		add_action( 'switch_theme', [ $this, 'remove_role' ] );

		// Ensure role exists on init (in case theme was already active)
		add_action( 'init', [ $this, 'ensure_role_exists' ], 20 );

		// Set default role for new users
		add_filter( 'pre_option_default_role', [ $this, 'set_default_role' ] );

		// Set new users to Caelis User role and mark as unapproved
		add_action( 'user_register', [ $this, 'handle_new_user_registration' ], 10, 1 );

		// Add admin columns for user approval
		add_filter( 'manage_users_columns', [ $this, 'add_approval_column' ] );
		add_filter( 'manage_users_custom_column', [ $this, 'show_approval_column' ], 10, 3 );

		// Add bulk actions for approval
		add_filter( 'bulk_actions-users', [ $this, 'add_bulk_approval_actions' ] );
		add_filter( 'handle_bulk_actions-users', [ $this, 'handle_bulk_approval' ], 10, 3 );

		// Add approve/deny actions to user row
		add_filter( 'user_row_actions', [ $this, 'add_user_row_actions' ], 10, 2 );

		// Handle approve/deny actions
		add_action( 'admin_init', [ $this, 'handle_approval_action' ] );

		// Delete user's posts when user is deleted
		add_action( 'delete_user', [ $this, 'delete_user_posts' ], 10, 1 );
	}

	/**
	 * Ensure the role exists (for themes already active)
	 */
	public function ensure_role_exists() {
		if ( ! get_role( self::ROLE_NAME ) ) {
			$this->register_role();
		}
	}

	/**
	 * Register the Caelis User role
	 */
	public function register_role() {
		// Get the role capabilities
		$capabilities = $this->get_role_capabilities();

		// Add the role
		add_role(
			self::ROLE_NAME,
			self::ROLE_DISPLAY_NAME,
			$capabilities
		);
	}

	/**
	 * Remove the Caelis User role
	 */
	public function remove_role() {
		// Get all users with this role
		$users = get_users( [ 'role' => self::ROLE_NAME ] );

		// Reassign to subscriber role before removing
		foreach ( $users as $user ) {
			$user->set_role( 'subscriber' );
		}

		// Remove the role
		remove_role( self::ROLE_NAME );
	}

	/**
	 * Get capabilities for Caelis User role
	 *
	 * Minimal permissions needed to:
	 * - Create, edit, and delete their own people and companies
	 * - Upload files (for photos and logos)
	 * - Read content (required for WordPress)
	 */
	private function get_role_capabilities() {
		return [
			// Basic WordPress capabilities
			'read'                   => true,

			// Post capabilities (used by person, company, important_date post types)
			'edit_posts'             => true,                    // Can create and edit their own posts
			'publish_posts'          => true,                 // Can publish their own posts
			'delete_posts'           => true,                  // Can delete their own posts
			'edit_published_posts'   => true,          // Can edit their own published posts
			'delete_published_posts' => true,        // Can delete their own published posts

			// Media capabilities
			'upload_files'           => true,                  // Can upload files (photos, logos)

			// No other capabilities - users can't:
			// - Edit other users' posts
			// - Manage other users
			// - Access WordPress admin settings
			// - Install plugins or themes
			// - Edit themes or plugins
		];
	}

	/**
	 * Set default role to Caelis User
	 */
	public function set_default_role( $value ) {
		return self::ROLE_NAME;
	}

	/**
	 * Handle new user registration
	 */
	public function handle_new_user_registration( $user_id ) {
		// Set role to Caelis User
		$user = new WP_User( $user_id );
		$user->set_role( self::ROLE_NAME );

		// Mark as unapproved by default
		update_user_meta( $user_id, self::APPROVAL_META_KEY, '0' );

		// Notify admins about the new user pending approval
		$this->notify_admins_of_pending_user( $user );
	}

	/**
	 * Notify all administrators about a new user pending approval
	 */
	private function notify_admins_of_pending_user( $user ) {
		// Get all users with manage_options capability (administrators)
		$admins = get_users(
			[
				'capability' => 'manage_options',
				'fields'     => [ 'user_email', 'display_name' ],
			]
		);

		if ( empty( $admins ) ) {
			return;
		}

		// Build the email
		$subject               = __( 'New Caelis user awaiting approval', 'caelis' );
		$approval_url          = admin_url( 'users.php' );
		$frontend_approval_url = home_url( '/settings/user-approval' );

		$message = sprintf(
			// translators: %1$s: user name, %2$s: email, %3$s: date, %4$s: admin URL, %5$s: frontend URL.
			__(
				'Hello,

A new user has registered for Caelis and is awaiting your approval:

Name: %1$s
Email: %2$s
Registered: %3$s

You can approve or deny this user from:
- WordPress Admin: %4$s
- Caelis Settings: %5$s

Best regards,
Caelis',
				'caelis'
			),
			$user->display_name ?: $user->user_login,
			$user->user_email,
			wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $user->user_registered ) ),
			$approval_url,
			$frontend_approval_url
		);

		// Send email to each admin
		foreach ( $admins as $admin ) {
			wp_mail( $admin->user_email, $subject, $message );
		}
	}

	/**
	 * Check if a user is approved
	 */
	public static function is_user_approved( $user_id ) {
		// Admins are always approved
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}

		// Check approval status.
		$approved = get_user_meta( $user_id, self::APPROVAL_META_KEY, true );
		return '1' === $approved || true === $approved || 1 === $approved;
	}

	/**
	 * Approve a user
	 */
	public function approve_user( $user_id ) {
		update_user_meta( $user_id, self::APPROVAL_META_KEY, '1' );

		// Send notification email.
		$user = get_userdata( $user_id );
		if ( $user ) {
			wp_mail(
				$user->user_email,
				__( 'Your Caelis account has been approved', 'caelis' ),
				sprintf(
					// translators: %1$s: user name, %2$s: login URL.
					__(
						'Hello %1$s,

Your Caelis account has been approved. You can now log in and start using Caelis.

Login: %2$s

Best regards,
Caelis Team',
						'caelis'
					),
					$user->display_name,
					wp_login_url()
				)
			);
		}
	}

	/**
	 * Deny/unapprove a user
	 */
	public function deny_user( $user_id ) {
		update_user_meta( $user_id, self::APPROVAL_META_KEY, '0' );
	}

	/**
	 * Add approval column to users list
	 */
	public function add_approval_column( $columns ) {
		// Insert after role column.
		$new_columns = [];
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			if ( 'role' === $key ) {
				$new_columns['caelis_approved'] = __( 'Approved', 'caelis' );
			}
		}
		return $new_columns;
	}

	/**
	 * Show approval status in column
	 *
	 * @param string $value       Current column value.
	 * @param string $column_name Column name.
	 * @param int    $user_id     User ID.
	 * @return string Column HTML.
	 */
	public function show_approval_column( $value, $column_name, $user_id ) {
		if ( 'caelis_approved' === $column_name ) {
			$is_approved = self::is_user_approved( $user_id );
			$user        = get_userdata( $user_id );

			// Only show for Caelis Users
			if ( in_array( self::ROLE_NAME, $user->roles ) ) {
				if ( $is_approved ) {
					return '<span style="color: green;">✓ ' . __( 'Yes', 'caelis' ) . '</span>';
				} else {
					return '<span style="color: red;">✗ ' . __( 'No', 'caelis' ) . '</span>';
				}
			}
			return '—';
		}
		return $value;
	}

	/**
	 * Add bulk approval actions
	 */
	public function add_bulk_approval_actions( $actions ) {
		$actions['caelis_approve'] = __( 'Approve', 'caelis' );
		$actions['caelis_deny']    = __( 'Deny', 'caelis' );
		return $actions;
	}

	/**
	 * Handle bulk approval actions
	 *
	 * @param string $sendback URL to redirect to.
	 * @param string $action   Action being performed.
	 * @param array  $user_ids User IDs to process.
	 * @return string Modified redirect URL.
	 */
	public function handle_bulk_approval( $sendback, $action, $user_ids ) {
		if ( 'caelis_approve' === $action ) {
			foreach ( $user_ids as $user_id ) {
				$this->approve_user( $user_id );
			}
			$sendback = add_query_arg( 'caelis_approved', count( $user_ids ), $sendback );
		} elseif ( 'caelis_deny' === $action ) {
			foreach ( $user_ids as $user_id ) {
				$this->deny_user( $user_id );
			}
			$sendback = add_query_arg( 'caelis_denied', count( $user_ids ), $sendback );
		}
		return $sendback;
	}

	/**
	 * Add approve/deny actions to user row
	 */
	public function add_user_row_actions( $actions, $user ) {
		// Only show for Caelis Users
		if ( in_array( self::ROLE_NAME, $user->roles ) ) {
			$is_approved = self::is_user_approved( $user->ID );

			if ( ! $is_approved ) {
				$actions['caelis_approve'] = sprintf(
					'<a href="%s">%s</a>',
					wp_nonce_url(
						admin_url( 'users.php?action=caelis_approve&user=' . $user->ID ),
						'caelis_approve_user_' . $user->ID
					),
					__( 'Approve', 'caelis' )
				);
			} else {
				$actions['caelis_deny'] = sprintf(
					'<a href="%s">%s</a>',
					wp_nonce_url(
						admin_url( 'users.php?action=caelis_deny&user=' . $user->ID ),
						'caelis_deny_user_' . $user->ID
					),
					__( 'Deny', 'caelis' )
				);
			}
		}

		return $actions;
	}

	/**
	 * Handle approve/deny actions from user row
	 */
	public function handle_approval_action() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified below for specific actions.
		if ( isset( $_GET['action'] ) && isset( $_GET['user'] ) && isset( $_GET['_wpnonce'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$action  = sanitize_text_field( wp_unslash( $_GET['action'] ) );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$user_id = absint( $_GET['user'] );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$nonce   = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) );

			if ( 'caelis_approve' === $action ) {
				if ( wp_verify_nonce( $nonce, 'caelis_approve_user_' . $user_id ) ) {
					$this->approve_user( $user_id );
					wp_redirect( admin_url( 'users.php?caelis_approved=1' ) );
					exit;
				}
			} elseif ( 'caelis_deny' === $action ) {
				if ( wp_verify_nonce( $nonce, 'caelis_deny_user_' . $user_id ) ) {
					$this->deny_user( $user_id );
					wp_redirect( admin_url( 'users.php?caelis_denied=1' ) );
					exit;
				}
			}
		}

		// Show admin notices.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only, no action.
		if ( isset( $_GET['caelis_approved'] ) ) {
			add_action(
				'admin_notices',
				function () {
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
					$count = absint( $_GET['caelis_approved'] );
					// translators: %d is the number of users approved.
					$message = sprintf( _n( '%d user approved.', '%d users approved.', $count, 'caelis' ), $count );
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
				}
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only, no action.
		if ( isset( $_GET['caelis_denied'] ) ) {
			add_action(
				'admin_notices',
				function () {
					// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
					$count = absint( $_GET['caelis_denied'] );
					// translators: %d is the number of users denied.
					$message = sprintf( _n( '%d user denied.', '%d users denied.', $count, 'caelis' ), $count );
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
				}
			);
		}
	}

	/**
	 * Delete all posts belonging to a user when user is deleted
	 * This is called by WordPress before the user is actually deleted
	 */
	public function delete_user_posts( $user_id ) {
		$post_types = [ 'person', 'company', 'important_date' ];

		foreach ( $post_types as $post_type ) {
			$posts = get_posts(
				[
					'post_type'      => $post_type,
					'author'         => $user_id,
					'posts_per_page' => -1,
					'post_status'    => 'any',
				]
			);

			foreach ( $posts as $post ) {
				wp_delete_post( $post->ID, true ); // Force delete (bypass trash)
			}
		}
	}
}
