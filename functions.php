<?php
/**
 * Rondo Club Theme Functions
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reconstruct Authorization header from PHP_AUTH_* variables.
 *
 * Some Apache/CGI configurations strip the Authorization header before it reaches PHP,
 * but still populate PHP_AUTH_USER and PHP_AUTH_PW. WordPress REST API Application
 * Password authentication only looks for the Authorization header, not these variables.
 *
 * This fix reconstructs the header so WordPress can authenticate REST API requests.
 */
if ( ! isset( $_SERVER['HTTP_AUTHORIZATION'] ) && isset( $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] ) ) {
	$_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode( $_SERVER['PHP_AUTH_USER'] . ':' . $_SERVER['PHP_AUTH_PW'] );
}

// Load Composer autoloader for PSR-4 classes
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Register a fallback autoloader for Rondo classes.
 *
 * Composer classmaps can become stale on deployments where autoload regeneration
 * fails. This loader lazily builds a class map from includes/class-*.php and only
 * resolves Rondo classes that Composer did not load.
 */
function rondo_register_fallback_autoloader() {
	spl_autoload_register(
		static function ( $class ) {
			if ( strpos( $class, 'Rondo\\' ) !== 0 ) {
				return;
			}

			static $classmap = null;
			if ( $classmap === null ) {
				$classmap = [];
				$files    = glob( __DIR__ . '/includes/class-*.php' );
				if ( $files === false ) {
					$files = [];
				}

				foreach ( $files as $file ) {
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
					$contents = file_get_contents( $file );
					if ( $contents === false ) {
						continue;
					}

					if ( ! preg_match( '/^\s*namespace\s+([^;]+);/m', $contents, $namespace_match ) ) {
						continue;
					}

					$namespace = trim( $namespace_match[1] );
					if ( $namespace !== 'Rondo' && strpos( $namespace, 'Rondo\\' ) !== 0 ) {
						continue;
					}

					if ( preg_match_all( '/^\s*(?:final\s+|abstract\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/m', $contents, $class_matches ) ) {
						foreach ( $class_matches[1] as $short_name ) {
							$classmap[ $namespace . '\\' . $short_name ] = $file;
						}
					}

					if ( preg_match_all( '/^\s*interface\s+([A-Za-z_][A-Za-z0-9_]*)/m', $contents, $interface_matches ) ) {
						foreach ( $interface_matches[1] as $short_name ) {
							$classmap[ $namespace . '\\' . $short_name ] = $file;
						}
					}

					if ( preg_match_all( '/^\s*trait\s+([A-Za-z_][A-Za-z0-9_]*)/m', $contents, $trait_matches ) ) {
						foreach ( $trait_matches[1] as $short_name ) {
							$classmap[ $namespace . '\\' . $short_name ] = $file;
						}
					}

					if ( preg_match_all( '/^\s*enum\s+([A-Za-z_][A-Za-z0-9_]*)/m', $contents, $enum_matches ) ) {
						foreach ( $enum_matches[1] as $short_name ) {
							$classmap[ $namespace . '\\' . $short_name ] = $file;
						}
					}
				}
			}

			if ( isset( $classmap[ $class ] ) && file_exists( $classmap[ $class ] ) ) {
				require_once $classmap[ $class ];
			}
		}
	);
}
rondo_register_fallback_autoloader();

// PSR-4 namespaced class imports
use Rondo\Core\PostTypes;
use Rondo\Core\Taxonomies;
use Rondo\Core\AutoTitle;
use Rondo\Core\PhoneNormalizer;
use Rondo\Core\VolunteerStatus;
use Rondo\Core\AccessControl;
use Rondo\Core\UserRoles;
use Rondo\REST\Api;
use Rondo\REST\People;
use Rondo\REST\Teams;
use Rondo\REST\Commissies;
use Rondo\REST\Todos;
use Rondo\REST\Feedback as RESTFeedback;
use Rondo\REST\Invoices as RESTInvoices;
use Rondo\REST\MembershipPasses as RESTMembershipPasses;
use Rondo\REST\Clothing as RESTClothing;
use Rondo\Calendar\Matcher;
use Rondo\Notifications\EmailChannel;
use Rondo\Notifications\LettermintMailer;
use Rondo\Notifications\LettermintWebhook;
use Rondo\Collaboration\CommentTypes;
use Rondo\Collaboration\MentionNotifications;
use Rondo\Collaboration\Reminders;
use Rondo\Export\VCard as VCardExport;
use Rondo\Data\InverseRelationships;
use Rondo\Data\PersonDeletionGuard;
use Rondo\Data\TodoMigration;
use Rondo\CustomFields\Manager as CustomFieldsManager;
use Rondo\CustomFields\Validation as CustomFieldsValidation;
use Rondo\REST\CustomFields as RESTCustomFields;
use Rondo\REST\UserSettings as RESTUserSettings;
use Rondo\REST\Users as RESTUsers;
use Rondo\REST\Reminders as RESTReminders;
use Rondo\REST\Vog as RESTVog;
use Rondo\REST\Volunteer as RESTVolunteer;
use Rondo\REST\MemberShifts as RESTMemberShifts;
use Rondo\REST\Fees as RESTFees;
use Rondo\REST\Lettermint as RESTLettermint;
use Rondo\REST\Capabilities as RESTCapabilities;
use Rondo\REST\FinanceSettings as RESTFinanceSettings;
use Rondo\VOG\VOGEmail;
use Rondo\Fees\FeeCacheInvalidator;
use Rondo\Config\ClubConfig;
use Rondo\Config\FinanceConfig;
use Rondo\Finance\InvoiceNumbering;
use Rondo\Finance\InvoicePdfGenerator;
use Rondo\Finance\InvoiceEmailSender;
use Rondo\Finance\RabobankOAuth;
use Rondo\Finance\RabobankPayment;
use Rondo\Finance\MolliePayment;
use Rondo\Finance\MollieWebhook;
use Rondo\Finance\InstallmentPaymentService;
use Rondo\Finance\InstallmentEmailSender;
use Rondo\Finance\InstallmentScheduler;
use Rondo\Finance\InvoiceReminderScheduler;
use Rondo\Finance\InvoiceScheduledSendScheduler;
use Rondo\Finance\PublicPaymentPage;
use Rondo\Finance\BulkInvoiceCreator;
use Rondo\Finance\QrCodeGenerator;
use Rondo\Demo\DemoExport;
use Rondo\Demo\DemoAnonymizer;
use Rondo\Demo\DemoImport;
use Rondo\Demo\DemoProtection;
use Rondo\Passes\PublicMembershipPassPage;
use Rondo\Volunteer\PublicTaakuitlegPage;

define( 'RONDO_THEME_DIR', get_template_directory() );
define( 'RONDO_THEME_URL', get_template_directory_uri() );
define( 'RONDO_THEME_VERSION', wp_get_theme()->get( 'Version' ) );

// Plugin constants (now part of theme)
define( 'RONDO_VERSION', RONDO_THEME_VERSION );
define( 'RONDO_PLUGIN_DIR', RONDO_THEME_DIR . '/includes' );
define( 'RONDO_PLUGIN_URL', RONDO_THEME_URL . '/includes' );

/**
 * Check for required dependencies
 */
function rondo_check_dependencies() {
	$missing = [];

	// Check for ACF Pro
	if ( ! class_exists( 'ACF' ) ) {
		$missing[] = 'Advanced Custom Fields Pro';
	}

	if ( ! empty( $missing ) ) {
		add_action(
			'admin_notices',
			function () use ( $missing ) {
				$message = sprintf(
					// translators: %s is a comma-separated list of plugin names.
					__( 'Rondo Club requires the following plugins: %s', 'rondo' ),
					implode( ', ', $missing )
				);
				echo '<div class="notice notice-error"><p>' . esc_html( $message ) . '</p></div>';
			}
		);
		return false;
	}

	return true;
}

/**
 * Backward compatibility class aliases.
 *
 * Only aliases that are still referenced in production code or tests are kept.
 * Unused aliases were removed in v32.6.0.
 */
// Core classes — used in tests and production code
class_alias( PostTypes::class, 'RONDO_Post_Types' );
class_alias( Taxonomies::class, 'RONDO_Taxonomies' );
class_alias( AccessControl::class, 'RONDO_Access_Control' );
class_alias( UserRoles::class, 'RONDO_User_Roles' );

// REST classes — used in tests
class_alias( Api::class, 'RONDO_REST_API' );
class_alias( People::class, 'RONDO_REST_People' );
class_alias( Teams::class, 'RONDO_REST_Teams' );
class_alias( Todos::class, 'RONDO_REST_Todos' );

// Calendar — used in class-calendar-matcher.php
class_alias( Matcher::class, 'RONDO_Calendar_Matcher' );

// Notifications — used in class-reminders.php
class_alias( EmailChannel::class, 'RONDO_Email_Channel' );
class_alias( MentionNotifications::class, 'RONDO_Mention_Notifications' );
class_alias( Reminders::class, 'RONDO_Reminders' );

// Collaboration — used in class-comment-types.php
class_alias( \Rondo\Collaboration\Mentions::class, 'RONDO_Mentions' );

/**
 * Check if current request is a REST API request
 */
function rondo_is_rest_request() {
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return true;
	}

	// Check for REST API URL pattern before REST_REQUEST is defined
	$rest_prefix = rest_get_url_prefix();
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';

	return strpos( $request_uri, '/' . $rest_prefix . '/' ) !== false;
}

/**
 * Initialize the CRM functionality with conditional class loading
 */
function rondo_init() {
	// Prevent double initialization
	static $initialized = false;
	if ( $initialized ) {
		return;
	}

	if ( ! rondo_check_dependencies() ) {
		return;
	}

	// Core classes - always needed for WordPress integration
	new PostTypes();
	new Taxonomies();
	new AccessControl();
	new PersonDeletionGuard();
	new UserRoles();
	new LettermintMailer();
	new DemoProtection();
	// Must load on every request: core password-reset mail is addressed to user_email,
	// which for a household member is an undeliverable placeholder.
	new \Rondo\Users\ContactEmailRouter();
	// Lets members sign in with their KNVB-ID or real email instead of a generated username.
	new \Rondo\Users\LoginResolver();

	// Skip loading heavy classes for non-relevant requests
	$is_admin = is_admin();
	$is_rest  = rondo_is_rest_request();
	$is_cron  = defined( 'DOING_CRON' ) && DOING_CRON;

	// Classes needed for content creation/editing (admin, REST, or cron)
	if ( $is_admin || $is_rest || $is_cron ) {
		new AutoTitle();
		new PhoneNormalizer();
		new VolunteerStatus();
		new InverseRelationships();
		new CommentTypes();
		new MentionNotifications();

		// Initialize custom field validation (unique constraint).
		new CustomFieldsValidation();

		// Initialize fee cache invalidation hooks
		new FeeCacheInvalidator();

		// Seed volunteer-policy fixtures (dienst_types + pool commissies).
		new \Rondo\Volunteer\VolunteerSeeder();

		// Hourly shift-completion cron + no-show → boete wiring.
		new \Rondo\Volunteer\ShiftScheduler();

		// Hourly member reminders and post-shift survey emails.
		new \Rondo\Volunteer\ShiftEmailScheduler();

		// Enforce the audited, mail-aware cancellation flow for assigned shifts.
		new \Rondo\Volunteer\ShiftCancellationService();

		// Daily template-expander cron (rolling three-month window).
		new \Rondo\Volunteer\ShiftTemplateExpander();

		// Invalidate eligibility + relationship cache on person mutations.
		new \Rondo\Volunteer\VolunteerCacheInvalidator();
	}

	// REST API classes - only for REST requests
	if ( $is_rest ) {
		new Api();
		new People();
		new Teams();
		new Commissies();
		new Todos();
		new RESTCustomFields();
		new RESTFeedback();
		new RESTInvoices();
		new RESTMembershipPasses();
		new RESTClothing();
		new RESTUserSettings();
		new RESTUsers();
		new RESTReminders();
		new RESTVog();
		new RESTVolunteer();
		new RESTMemberShifts();
		new RESTFees();
		new RESTLettermint();
		new RESTCapabilities();
		new RESTFinanceSettings();
		new RabobankOAuth();
		new RabobankPayment();
		new MollieWebhook();
		new LettermintWebhook();

		// Log REST API errors to debug.log
		add_filter(
			'rest_request_after_callbacks',
			function ( $response, $handler, $request ) {
				if ( is_wp_error( $response ) ) {
					error_log(
						sprintf(
							'REST API error: %s %s — %s (code: %s)',
							$request->get_method(),
							$request->get_route(),
							$response->get_error_message(),
							$response->get_error_code()
						)
					);
				}
				return $response;
			},
			10,
			3
		);
	}

	// Reminders - only for admin or cron
	if ( $is_admin || $is_cron ) {
		new Reminders();
	}

	// Bulk invoice creator — registers WP-Cron hook for batch processing
	new BulkInvoiceCreator();

	// Public payment page - register rewrite rules and template_redirect handler
	new PublicPaymentPage();

	// Public membership pass landing page - /lidpas/{token}
	new PublicMembershipPassPage();

	// Public self-service account activation - /activeren
	new \Rondo\Users\ActivationPage();
	add_action( 'rondo_retry_guardian_notification', [ \Rondo\Users\GuardianAccountService::class, 'retry_notification' ] );

	// Public taakuitleg landing page - /uitleg/{slug} (QR target, no auth)
	new PublicTaakuitlegPage();

	// Installment scheduler — daily cron sweeper for installment emails and reminders
	new InstallmentScheduler();

	// Invoice reminder scheduler — daily cron sweeper for membership and discipline invoice reminders
	new InvoiceReminderScheduler();

	// Invoice scheduled-send sweeper — daily cron that sends drafts queued for a future date
	new InvoiceScheduledSendScheduler();

	$initialized = true;
}
// Initialize early for REST API requests, but also check on plugins_loaded
// in case ACF Pro isn't loaded yet (plugins load after themes)
add_action( 'after_setup_theme', 'rondo_init', 5 );
add_action( 'plugins_loaded', 'rondo_init', 5 );

/**
 * Track when an authenticated user was last active.
 *
 * Stores a UTC datetime and throttles writes to once per 5 minutes per user
 * to avoid excessive database writes on frequent requests.
 *
 * @return void
 */
function rondo_track_user_last_active() {
	if ( ! is_user_logged_in() ) {
		return;
	}

	if ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return;
	}

	$user_id = get_current_user_id();
	if ( $user_id <= 0 ) {
		return;
	}

	$now_ts       = time();
	$last_seen_ts = (int) get_user_meta( $user_id, 'rondo_last_active_ts', true );

	// Update at most once every 5 minutes per user.
	if ( $last_seen_ts > 0 && ( $now_ts - $last_seen_ts ) < 300 ) {
		return;
	}

	update_user_meta( $user_id, 'rondo_last_active', gmdate( 'Y-m-d H:i:s', $now_ts ) );
	update_user_meta( $user_id, 'rondo_last_active_ts', $now_ts );
}
add_action( 'init', 'rondo_track_user_last_active', 20 );

/**
 * Allow membership pass config uploads (.p12 and .json) for financial users.
 *
 * @param array $mimes Existing mime map.
 * @return array
 */
function rondo_membership_pass_upload_mimes( $mimes ) {
	if ( current_user_can( 'financieel' ) || current_user_can( 'manage_options' ) ) {
		$mimes['p12']  = 'application/x-pkcs12';
		$mimes['json'] = 'application/json';
	}

	return $mimes;
}
add_filter( 'upload_mimes', 'rondo_membership_pass_upload_mimes' );



/**
 * Migrate WordPress options from stadion_ prefix to rondo_ prefix.
 *
 * Runs once after the v1.1 rebrand. Copies old option values to new option names
 * (only if the new option doesn't already have a value), then deletes the old options.
 */
function rondo_migrate_options() {
	if ( get_option( 'rondo_options_migrated' ) ) {
		return;
	}

	$option_map = [
		'stadion_vog_from_email'        => 'rondo_vog_from_email',
		'stadion_vog_from_name'         => 'rondo_vog_from_name',
		'stadion_vog_template_new'      => 'rondo_vog_template_new',
		'stadion_vog_template_renewal'  => 'rondo_vog_template_renewal',
		'stadion_vog_exempt_commissies' => 'rondo_vog_exempt_commissies',
		'stadion_club_name'             => 'rondo_club_name',
		'stadion_freescout_url'         => 'rondo_freescout_url',
	];

	foreach ( $option_map as $old_key => $new_key ) {
		$old_value = get_option( $old_key );
		if ( $old_value !== false ) {
			// Only copy if the new option doesn't already have a value.
			$new_value = get_option( $new_key );
			if ( $new_value === false || $new_value === '' ) {
				update_option( $new_key, $old_value );
			}
			delete_option( $old_key );
		}
	}

	// Migrate user meta keys from stadion_ to rondo_.
	$user_meta_keys = [
		'stadion_linked_person_id'          => 'rondo_linked_person_id',
		'stadion_notification_channels'     => 'rondo_notification_channels',
		'stadion_notification_time'         => 'rondo_notification_time',
		'stadion_mention_notifications'     => 'rondo_mention_notifications',
		'stadion_color_scheme'              => 'rondo_color_scheme',
		'stadion_dashboard_visible_cards'   => 'rondo_dashboard_visible_cards',
		'stadion_dashboard_card_order'      => 'rondo_dashboard_card_order',
		'stadion_people_list_preferences'   => 'rondo_people_list_preferences',
		'stadion_people_list_column_order'  => 'rondo_people_list_column_order',
		'stadion_people_list_column_widths' => 'rondo_people_list_column_widths',
	];

	$user_ids = get_users( [ 'fields' => 'ID' ] );
	foreach ( $user_ids as $uid ) {
		foreach ( $user_meta_keys as $old_meta => $new_meta ) {
			$old_value = get_user_meta( $uid, $old_meta, true );
			if ( $old_value !== '' && $old_value !== false ) {
				$new_value = get_user_meta( $uid, $new_meta, true );
				if ( $new_value === '' || $new_value === false ) {
					update_user_meta( $uid, $new_meta, $old_value );
				}
				delete_user_meta( $uid, $old_meta );
			}
		}
	}

	update_option( 'rondo_options_migrated', '1' );
}
add_action( 'admin_init', 'rondo_migrate_options' );
add_action( 'rest_api_init', 'rondo_migrate_options' );

// Load WP-CLI commands if WP-CLI is available
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once RONDO_PLUGIN_DIR . '/class-wp-cli.php';
	new TodoMigration();

	// Class alias for backward compatibility
	if ( ! class_exists( 'RONDO_Demo_Export' ) ) {
		class_alias( DemoExport::class, 'RONDO_Demo_Export' );
	}
	if ( ! class_exists( 'RONDO_Demo_Anonymizer' ) ) {
		class_alias( DemoAnonymizer::class, 'RONDO_Demo_Anonymizer' );
	}
	if ( ! class_exists( 'RONDO_Demo_Import' ) ) {
		class_alias( DemoImport::class, 'RONDO_Demo_Import' );
	}
}

/**
 * Theme setup
 */
function rondo_theme_setup() {
	// Add theme support
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support(
		'html5',
		[
			'search-form',
			'comment-form',
			'comment-list',
			'gallery',
			'caption',
			'style',
			'script',
		]
	);

	// Register nav menus (optional, React handles navigation)
	register_nav_menus(
		[
			'primary' => __( 'Primary Menu', 'rondo' ),
		]
	);
}
add_action( 'after_setup_theme', 'rondo_theme_setup' );

/**
 * Set default page title for SPA (React will update it dynamically)
 */
function rondo_theme_document_title_parts( $title ) {
	// Only modify title on frontend (not admin)
	if ( is_admin() ) {
		return $title;
	}

	// Set a default title - React will update it when routes change
	$club_name      = \Rondo\Config\ClubConfig::get_club_name();
	$title['title'] = ! empty( $club_name ) ? $club_name : 'Rondo Club';
	$title['site']  = '';

	return $title;
}
add_filter( 'document_title_parts', 'rondo_theme_document_title_parts', 20 );

/**
 * Enqueue scripts and styles
 */
function rondo_theme_enqueue_assets() {
	$dist_dir = RONDO_THEME_DIR . '/dist';
	$dist_url = RONDO_THEME_URL . '/dist';

	// Check if we have built assets (Vite puts manifest in .vite subdirectory)
	$manifest_path = $dist_dir . '/.vite/manifest.json';
	if ( ! file_exists( $manifest_path ) ) {
		// Fallback to root dist directory
		$manifest_path = $dist_dir . '/manifest.json';
	}

	if ( file_exists( $manifest_path ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$manifest = json_decode( file_get_contents( $manifest_path ), true );

		// Enqueue the main JS file
		if ( isset( $manifest['src/main.jsx'] ) ) {
			$main_js = $manifest['src/main.jsx'];

			if ( isset( $main_js['css'] ) ) {
				foreach ( $main_js['css'] as $css_file ) {
					wp_enqueue_style(
						'prm-theme-style',
						$dist_url . '/' . $css_file,
						[],
						RONDO_THEME_VERSION
					);
				}
			}

			if ( isset( $main_js['file'] ) ) {
				wp_enqueue_script(
					'prm-theme-script',
					$dist_url . '/' . $main_js['file'],
					[],
					null, // Don't add version query string - Vite hashes filenames for cache busting. Query strings break ES module deduplication.
					true
				);

				// Localize script with WordPress data
				wp_localize_script( 'prm-theme-script', 'rondoConfig', rondo_get_js_config() );
			}
		}
	} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		// Development mode - load from Vite dev server.
		add_action(
			'wp_head',
			function () {
				// Vite HMR requires direct script tags, cannot use wp_enqueue_script.
				// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<script type="module" src="http://localhost:5173/@vite/client"></script>';
				// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<script type="module" src="http://localhost:5173/src/main.jsx"></script>';
			}
		);
	} else {
		// Production mode but no build found - show error.
		add_action(
			'wp_head',
			function () {
				// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript, WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<script>console.error("Rondo Club: Build files not found. Please run npm run build.");</script>';
			}
		);
	}
}
add_action( 'wp_enqueue_scripts', 'rondo_theme_enqueue_assets' );

/**
 * Get JavaScript configuration
 */
function rondo_get_js_config() {
	$user    = wp_get_current_user();
	$user_id = get_current_user_id();

	// Get build time from manifest file modification time.
	// This ensures every build produces a unique timestamp.
	$build_time    = null;
	$manifest_path = RONDO_THEME_DIR . '/dist/.vite/manifest.json';
	if ( file_exists( $manifest_path ) ) {
		$build_time = gmdate( 'c', filemtime( $manifest_path ) );
	} else {
		// Fallback to current time for dev mode (Vite dev server).
		$build_time = gmdate( 'c' );
	}

	// Get user's linked person ID (for filtering current user from attendee lists)
	$linked_person_id = $user_id ? (int) get_user_meta( $user_id, 'rondo_linked_person_id', true ) : null;

	// Club configuration
	$club_settings = \Rondo\Config\ClubConfig::get_all_settings();

	return [
		'apiUrl'              => rest_url(),
		'nonce'               => wp_create_nonce( 'wp_rest' ),
		'siteUrl'             => home_url(),
		'siteName'            => get_bloginfo( 'name' ),
		'userId'              => $user_id,
		'userLogin'           => $user ? $user->user_login : '',
		'isLoggedIn'          => is_user_logged_in(),
		'isAdmin'             => current_user_can( 'manage_options' ),
		'loginUrl'            => wp_login_url(),
		'logoutUrl'           => wp_logout_url( home_url() ),
		'adminUrl'            => admin_url(),
		'themeUrl'            => RONDO_THEME_URL,
		'version'             => wp_get_theme()->get( 'Version' ),
		'buildTime'           => $build_time,
		'currentUserPersonId' => $linked_person_id ?: null,
		'clubName'            => $club_settings['club_name'],
		'volunteerSignupInfo' => $club_settings['volunteer_signup_info'],
		'freescoutUrl'        => $club_settings['freescout_url'],
		'isDemo'              => (bool) get_option( 'rondo_is_demo_site', false ),
		'isDemoUser'          => (bool) get_option( 'rondo_is_demo_site', false ) && $user && $user->user_login === 'demo',
	];
}

/**
 * Add config to head for initial page load
 */
function rondo_theme_add_config_to_head() {
	$config = rondo_get_js_config();
	echo '<script>window.rondoConfig = ' . wp_json_encode( $config ) . ';</script>';
}
add_action( 'wp_head', 'rondo_theme_add_config_to_head', 0 );

/**
 * Preload the dashboard API endpoint so the browser starts fetching before JS boots.
 *
 * Fires an authenticated fetch (with X-WP-Nonce) inline in <head> and stashes the
 * promise on window.__dashboardPreload for the React app to consume.
 */
function rondo_preload_dashboard_api() {
	if ( ! is_user_logged_in() ) {
		return;
	}

	// WordPress renders the SPA shell for every client-side route. Avoid an
	// expensive dashboard request when the visitor opened another route
	// directly (for example /people/123 or /vrijwilligers).
	$request_path = isset( $_SERVER['REQUEST_URI'] ) ? wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
	$home_path    = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	$request_path = '/' . trim( (string) $request_path, '/' );
	$home_path    = '/' . trim( (string) $home_path, '/' );

	if ( $request_path !== $home_path ) {
		return;
	}

	$dashboard_url = rest_url( 'rondo/v1/dashboard' );
	$nonce         = wp_create_nonce( 'wp_rest' );
	echo '<script>window.__dashboardPreload = fetch(' . wp_json_encode( $dashboard_url ) . ', { headers: { "X-WP-Nonce": ' . wp_json_encode( $nonce ) . ' } });</script>' . "\n";
}
add_action( 'wp_head', 'rondo_preload_dashboard_api', 1 );

/**
 * Output PWA meta tags for iOS and Android support
 *
 * vite-plugin-pwa handles manifest generation, but we need to manually
 * inject meta tags since WordPress uses PHP templates, not index.html.
 */
function rondo_pwa_meta_tags() {
	$theme_url = RONDO_THEME_URL;

	// Fixed brand color (electric-cyan from Phase 162)
	$brand_color_light = '#0891b2';
	$brand_color_dark  = '#06b6d4';
	?>
	<!-- PWA Meta Tags -->
	<meta name="mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="default">
	<meta name="apple-mobile-web-app-title" content="Rondo Club">

	<!-- Apple Touch Icon -->
	<link rel="apple-touch-icon" href="<?php echo esc_url( $theme_url . '/public/icons/apple-touch-icon-180x180.png' ); ?>">

	<!-- Manifest -->
	<link rel="manifest" href="<?php echo esc_url( $theme_url . '/dist/manifest.webmanifest' ); ?>">

	<!-- Theme Color (fixed brand colors) -->
	<meta name="theme-color" media="(prefers-color-scheme: light)" content="<?php echo $brand_color_light; ?>">
	<meta name="theme-color" media="(prefers-color-scheme: dark)" content="<?php echo $brand_color_dark; ?>">
	<?php
}
add_action( 'wp_head', 'rondo_pwa_meta_tags', 2 );

/**
 * Add Rondo favicon to frontend pages.
 */
function rondo_site_favicon() {
	$icon_url = RONDO_THEME_URL . '/public/icons/rondo-logo.png';
	echo '<link rel="icon" type="image/png" sizes="192x192" href="' . esc_url( $icon_url ) . '">' . "\n";
	echo '<link rel="icon" type="image/svg+xml" href="' . esc_url( RONDO_THEME_URL . '/favicon.svg' ) . '">' . "\n";
}
add_action( 'wp_head', 'rondo_site_favicon', 3 );

/**
 * Hide admin bar on frontend - it interferes with the SPA interface
 */
function rondo_theme_remove_admin_bar() {
	if ( ! is_admin() ) {
		show_admin_bar( false );
	}
}
add_action( 'after_setup_theme', 'rondo_theme_remove_admin_bar' );

/**
 * Block non-admin users from accessing wp-admin.
 *
 * Redirects users without manage_options capability to the app home page.
 * Exempts AJAX requests, WP-CLI, and cron to avoid breaking functionality.
 */
function rondo_block_wp_admin() {
	if ( wp_doing_ajax() ) {
		return;
	}

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		return;
	}

	if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
		return;
	}

	if ( current_user_can( 'manage_options' ) ) {
		return;
	}

	wp_safe_redirect( home_url( '/' ) );
	exit;
}
add_action( 'admin_init', 'rondo_block_wp_admin' );

/**
 * Redirect WordPress backend URLs to SPA frontend routes
 *
 * Handles URLs like ?post_type=person&p=123 → /people/123
 */
function rondo_redirect_backend_urls() {
	// Don't redirect admin, login, or API requests.
	if ( is_admin() || $GLOBALS['pagenow'] === 'wp-login.php' ) {
		return;
	}

	// Check for post_type and p query parameters (WordPress backend URL format)
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$post_id = isset( $_GET['p'] ) ? absint( $_GET['p'] ) : 0;

	if ( ! $post_type || ! $post_id ) {
		return;
	}

	// Map post types to frontend routes
	$route_map = [
		'person'    => 'people',
		'team'      => 'teams',
		'commissie' => 'commissies',
	];

	if ( ! isset( $route_map[ $post_type ] ) ) {
		return;
	}

	// Build the SPA URL
	$spa_path     = '/' . $route_map[ $post_type ] . '/' . $post_id;
	$redirect_url = home_url( $spa_path );

	// Perform the redirect (301 permanent)
	wp_redirect( $redirect_url, 301 );
	exit;
}
add_action( 'template_redirect', 'rondo_redirect_backend_urls', 0 ); // Priority 0 to run before other redirects

/**
 * Redirect all frontend requests to index.php (SPA)
 */
function rondo_theme_template_redirect() {
	// Don't redirect admin, login, or API requests.
	if ( is_admin() || $GLOBALS['pagenow'] === 'wp-login.php' ) {
		return;
	}

	// Don't redirect REST API requests
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return;
	}

	// Don't redirect AJAX requests
	if ( wp_doing_ajax() ) {
		return;
	}

	// Get the request URI
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) : '';

	// Remove query string for matching
	$path = trim( $request_uri, '/' );

	// If this is a request for our app routes, serve index.php
	// This includes: /, /people, /people/:id, /teams, /teams/:id, /dates, /dates/:id, /settings, /login
	$app_routes = [
		'people',
		'teams',
		'commissies',
		'dates',
		'settings',
		'login',
	];

	$is_app_route = false;

	// Check if it's the root path.
	if ( empty( $path ) || $path === '/' ) {
		$is_app_route = true;
	}

	// Check if it starts with any of our app route prefixes.
	foreach ( $app_routes as $route ) {
		if ( $route === $path || strpos( $path, $route . '/' ) === 0 ) {
			$is_app_route = true;
			break;
		}
	}

	// Also handle 404s on frontend (WordPress might return 404 for our routes)
	if ( $is_app_route || ( is_404() && ! is_admin() ) ) {
		// Set status to 200 so React Router can handle it
		status_header( 200 );
		// Clear any 404 query flags
		global $wp_query;
		$wp_query->is_404 = false;
		// Load index.php for React Router
		include get_template_directory() . '/index.php';
		exit;
	}
}
add_action( 'template_redirect', 'rondo_theme_template_redirect', 1 );

/**
 * Handle client-side routing - return index.php for all routes
 */
function rondo_theme_rewrite_rules() {
	add_rewrite_rule( '^app/?', 'index.php', 'top' );
	add_rewrite_rule( '^app/(.+)/?', 'index.php', 'top' );
}
add_action( 'init', 'rondo_theme_rewrite_rules' );

/**
 * Flush rewrite rules once after a deploy that adds new public routes.
 *
 * Rewrite rules are normally only flushed on theme activation. A plain rsync
 * deploy (the usual path) does not re-activate the theme, so new rewrite rules
 * — like the /uitleg/{slug} taakuitleg page — would 404 until someone visits
 * Settings → Permalinks. Bumping this version flushes exactly once on the first
 * request after deploy, mirroring rondo_maybe_add_postmeta_indexes().
 *
 * Runs late on `init` so every add_rewrite_rule() call has already registered.
 */
function rondo_maybe_flush_rewrite_rules() {
	$rewrite_version = '2'; // Bump when adding/changing a rewrite rule.
	if ( get_option( 'rondo_rewrite_rules_version' ) === $rewrite_version ) {
		return;
	}

	flush_rewrite_rules();
	update_option( 'rondo_rewrite_rules_version', $rewrite_version, true );
}
add_action( 'init', 'rondo_maybe_flush_rewrite_rules', 99 );
add_action( 'init', 'rondo_maybe_add_postmeta_indexes' );

/**
 * Theme activation - includes CRM initialization
 */
function rondo_theme_activation() {
	// Trigger post type registration (Composer autoloader handles class loading)
	$post_types = new PostTypes();
	$post_types->register_post_types();

	$taxonomies = new Taxonomies();
	$taxonomies->register_taxonomies();

	// Register custom user role (class constructor handles registration via hook)
	new UserRoles();

	// Flush rewrite rules
	flush_rewrite_rules();

	// Schedule per-user reminder cron jobs
	$reminders = new Reminders();
	$reminders->schedule_all_user_reminders();

	// Also handle theme-specific rewrite rules
	rondo_theme_rewrite_rules();

	// Register public payment page rewrite rules
	$payment_page = new PublicPaymentPage();
	$payment_page->register_rewrite_rules();

	// Add custom postmeta indexes for dashboard performance.
	rondo_maybe_add_postmeta_indexes();
}
add_action( 'after_switch_theme', 'rondo_theme_activation' );

/**
 * Add composite indexes to wp_postmeta for frequently queried meta keys.
 *
 * WordPress only has (meta_id, post_id, meta_key) indexes on postmeta. Dashboard
 * queries that filter by meta_key + meta_value benefit from a composite index.
 * Runs once per version bump via an option check, and on theme activation.
 */
function rondo_maybe_add_postmeta_indexes() {
	$index_version = '1';
	if ( get_option( 'rondo_postmeta_index_version' ) === $index_version ) {
		return;
	}

	global $wpdb;

	$indexes = [
		'rondo_meta_key_value' => "ALTER TABLE {$wpdb->postmeta} ADD INDEX rondo_meta_key_value (meta_key(40), meta_value(20))",
	];

	foreach ( $indexes as $name => $sql ) {
		// Check if index already exists.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = %s AND index_name = %s',
				$wpdb->postmeta,
				$name
			)
		);

		if ( ! $existing ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $sql );
		}
	}

	update_option( 'rondo_postmeta_index_version', $index_version, true );
}

/**
 * Theme deactivation - cleanup CRM functionality
 */
function rondo_theme_deactivation() {
	// Clear all per-user reminder cron jobs
	wp_clear_scheduled_hook( 'rondo_user_reminder' );

	// Clear legacy scheduled hook (for backward compatibility)
	wp_clear_scheduled_hook( 'rondo_daily_reminder_check' );

	// Clear volunteer shift lifecycle hooks.
	\Rondo\Volunteer\ShiftScheduler::unregister_cron();
	\Rondo\Volunteer\ShiftEmailScheduler::unregister_cron();
	\Rondo\Volunteer\ShiftTemplateExpander::unregister_cron();

	// Remove custom user role (must call directly since switch_theme hook already fired)
	$user_roles = new UserRoles();
	$user_roles->remove_role();

	// Flush rewrite rules
	flush_rewrite_rules();
}
add_action( 'switch_theme', 'rondo_theme_deactivation' );

/**
 * Unschedule user reminder cron when user is deleted
 */
function rondo_user_deleted( $user_id ) {
	$reminders = new Reminders();
	$reminders->unschedule_user_reminder( $user_id );
}
add_action( 'delete_user', 'rondo_user_deleted' );

/**
 * Add type="module" to script tags
 *
 * @param string $tag    Script tag HTML.
 * @param string $handle Script handle.
 * @param string $src    Script source URL.
 * @return string Modified script tag.
 */
function rondo_theme_script_type( $tag, $handle, $src ) {
	if ( $handle === 'prm-theme-script' ) {
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Modifying enqueued script tag.
		return '<script type="module" src="' . esc_url( $src ) . '"></script>';
	}
	return $tag;
}
add_filter( 'script_loader_tag', 'rondo_theme_script_type', 10, 3 );

/**
 * Disable WordPress emojis (performance)
 */
function rondo_theme_disable_emojis() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
}
add_action( 'init', 'rondo_theme_disable_emojis' );

/**
 * Clean up WordPress head
 */
function rondo_theme_cleanup_head() {
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
}
add_action( 'init', 'rondo_theme_cleanup_head' );

/**
 * Load ACF JSON from theme directory
 */
function rondo_acf_json_load_point( $paths ) {
	$paths[] = RONDO_THEME_DIR . '/acf-json';
	return $paths;
}
add_filter( 'acf/settings/load_json', 'rondo_acf_json_load_point' );

/**
 * Save ACF JSON to theme directory (for development)
 */
function rondo_acf_json_save_point( $path ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		$path = RONDO_THEME_DIR . '/acf-json';
	}
	return $path;
}
add_filter( 'acf/settings/save_json', 'rondo_acf_json_save_point' );

/**
 * Invalidate email lookup cache when a person is saved
 *
 * This ensures that the contact matching cache stays in sync
 * when contact info (emails) are updated on person records.
 */
function rondo_invalidate_email_lookup_on_person_save( $post_id ) {
	if ( get_post_type( $post_id ) === 'person' ) {
		$user_id = get_post_field( 'post_author', $post_id );
		if ( $user_id ) {
			Matcher::invalidate_cache( $user_id );
		}
	}
}
add_action( 'acf/save_post', 'rondo_invalidate_email_lookup_on_person_save', 20 );

/**
 * Reset VOG tracking fields when datum-vog is updated
 *
 * When a new VOG date is set, clear the email sent and Justis submitted
 * tracking fields since the VOG request process is now complete.
 *
 * @param mixed  $value   The new value.
 * @param int    $post_id The post ID.
 * @param array  $field   The field array.
 * @param mixed  $original The original value.
 * @return mixed The value (unchanged).
 */
function rondo_reset_vog_tracking_on_datum_update( $value, $post_id, $field, $original ) {
	// Only process if datum-vog is actually changing to a new value
	if ( $value !== $original && ! empty( $value ) ) {
		delete_post_meta( $post_id, 'vog_email_sent_date' );
		delete_post_meta( $post_id, 'vog_justis_submitted_date' );
		delete_post_meta( $post_id, 'vog_reminder_sent_date' );
	}
	return $value;
}
add_filter( 'acf/update_value/name=datum-vog', 'rondo_reset_vog_tracking_on_datum_update', 10, 4 );

/**
 * Custom login page styling
 */
function rondo_login_styles() {
	$site_name = get_bloginfo( 'name' );

	// Get club name for display
	$club_name = \Rondo\Config\ClubConfig::get_club_name();

	// Fixed brand color (electric-cyan from Phase 162)
	$brand_color = '#0891b2';

	// Pre-calculated color variants
	$brand_color_dark     = '#0e7490';  // Darker shade
	$brand_color_darkest  = '#155e75';  // Darkest shade
	$brand_color_light    = '#67e8f9';  // Light tint
	$brand_color_lightest = '#cffafe'; // Very light tint for backgrounds
	$brand_color_border   = '#a5f3fc';  // Medium light tint for borders

	// Use club name if configured, otherwise site name
	$display_name = ! empty( $club_name ) ? $club_name : $site_name;

	// Rondo logo URL
	$logo_url = RONDO_THEME_URL . '/public/icons/rondo-logo.png';
	?>
	<style type="text/css">
		/* Background gradient */
		body.login {
			background: linear-gradient(135deg, <?php echo esc_attr( $brand_color_lightest ); ?> 0%, <?php echo esc_attr( $brand_color_light ); ?> 50%, <?php echo esc_attr( $brand_color_border ); ?> 100%);
			font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
		}

		/* Login form container */
		#login {
			padding: 8% 0 0;
		}

		/* Logo */
		#login h1 a {
			background-image: url('<?php echo esc_url( $logo_url ); ?>');
			background-size: contain;
			background-position: center center;
			background-repeat: no-repeat;
			width: 84px;
			height: 84px;
			margin-bottom: 20px;
		}

		/* App name below logo */
		#login h1::after {
			content: '<?php echo esc_js( $display_name ); ?>';
			display: block;
			text-align: center;
			font-size: 28px;
			font-weight: 600;
			color: <?php echo esc_attr( $brand_color_darkest ); ?>;
			margin-top: 10px;
			letter-spacing: -0.5px;
		}

		/* Form box */
		.login form {
			background: #ffffff;
			border: 1px solid <?php echo esc_attr( $brand_color_border ); ?>;
			border-radius: 12px;
			box-shadow: 0 10px 25px rgba(8, 145, 178, 0.1);
			padding: 26px 24px;
		}

		/* Input fields */
		.login input[type="text"],
		.login input[type="password"] {
			border: 1px solid <?php echo esc_attr( $brand_color_border ); ?>;
			border-radius: 8px;
			padding: 10px 14px;
			font-size: 15px;
			transition: border-color 0.2s, box-shadow 0.2s;
		}

		.login input[type="text"]:focus,
		.login input[type="password"]:focus {
			border-color: <?php echo esc_attr( $brand_color ); ?>;
			box-shadow: 0 0 0 3px rgba(8, 145, 178, 0.15);
			outline: none;
		}

		/* Labels */
		.login label {
			color: <?php echo esc_attr( $brand_color_darkest ); ?>;
			font-weight: 500;
			font-size: 14px;
		}

		/* Submit button container */
		.login .submit {
			margin-top: 20px 0 !important;
		}

		/* Submit button */
		.login .button-primary,
		.login #wp-submit,
		.wp-core-ui .button-primary {
			background: linear-gradient(135deg, <?php echo esc_attr( $brand_color_light ); ?> 0%, <?php echo esc_attr( $brand_color ); ?> 100%) !important;
			border: none !important;
			border-radius: 8px !important;
			box-shadow: 0 4px 12px rgba(8, 145, 178, 0.3) !important;
			color: #ffffff !important;
			font-weight: 600 !important;
			font-size: 15px !important;
			padding: 10px 20px !important;
			text-shadow: none !important;
			transition: all 0.2s !important;
			width: 100%;
			height: auto !important;
		}

		.login .button-primary:hover,
		.login .button-primary:focus,
		.login #wp-submit:hover,
		.login #wp-submit:focus,
		.wp-core-ui .button-primary:hover,
		.wp-core-ui .button-primary:focus {
			background: linear-gradient(135deg, <?php echo esc_attr( $brand_color ); ?> 0%, <?php echo esc_attr( $brand_color_dark ); ?> 100%) !important;
			box-shadow: 0 6px 16px rgba(14, 116, 144, 0.4) !important;
			border-color: transparent !important;
		}

		.login .button-primary:active,
		.login #wp-submit:active {
			transform: translateY(1px);
		}

		/* Remember me checkbox */
		.login .forgetmenot {
			margin-top: 10px;
		}

		.login input[type="checkbox"] {
			accent-color: <?php echo esc_attr( $brand_color ); ?>;
		}

		/* Links */
		.login #nav a,
		.login #backtoblog a {
			color: <?php echo esc_attr( $brand_color_dark ); ?>;
			text-decoration: none;
			font-size: 13px;
			transition: color 0.2s;
		}

		.login #nav a:hover,
		.login #backtoblog a:hover {
			color: <?php echo esc_attr( $brand_color_darkest ); ?>;
			text-decoration: underline;
		}

		/* Error/message boxes */
		.login .message,
		.login .success {
			border-left-color: <?php echo esc_attr( $brand_color ); ?>;
			background: <?php echo esc_attr( $brand_color_lightest ); ?>;
		}

		/* Notice info message styling */
		.login .notice.notice-info.message {
			border-left-color: <?php echo esc_attr( $brand_color ); ?> !important;
			margin-top: 20px !important;
		}

		.login #login_error {
			border-left-color: #dc2626;
		}

		/* Privacy policy link */
		.login .privacy-policy-page-link a {
			color: <?php echo esc_attr( $brand_color_dark ); ?>;
		}

		/* Hide "Go to site" for cleaner look */
		#backtoblog {
			display: none;
		}
	</style>
	<?php
}
add_action( 'login_enqueue_scripts', 'rondo_login_styles' );

/**
 * Add favicon to login page
 */
function rondo_login_favicon() {
	$icon_url = RONDO_THEME_URL . '/public/icons/rondo-logo.png';
	echo '<link rel="icon" type="image/png" sizes="192x192" href="' . esc_url( $icon_url ) . '">';
}
add_action( 'login_head', 'rondo_login_favicon' );

/**
 * Change login logo URL to homepage
 */
function rondo_login_logo_url() {
	return home_url( '/' );
}
add_filter( 'login_headerurl', 'rondo_login_logo_url' );

/**
 * Change login logo title
 */
function rondo_login_logo_title() {
	return get_bloginfo( 'name' );
}
add_filter( 'login_headertext', 'rondo_login_logo_title' );

/**
 * Redirect users to homepage after login
 */
function rondo_login_redirect( $redirect_to, $request, $user ) {
	// Only redirect if no specific redirect was requested
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( isset( $_GET['redirect_to'] ) && ! empty( $_GET['redirect_to'] ) ) {
		return $redirect_to;
	}

	// Redirect all users to the homepage
	return home_url( '/' );
}
add_filter( 'login_redirect', 'rondo_login_redirect', 10, 3 );

/**
 * Disable admin color scheme picker for all users
 */
remove_action( 'admin_color_scheme_picker', 'admin_color_scheme_picker' );

/**
 * Modify registration confirmation message to include approval notice
 * Uses gettext filter to intercept WordPress core translation string
 *
 * @param string $translated_text Translated text.
 * @param string $text            Original text.
 * @param string $domain          Text domain.
 * @return string Modified text.
 */
function rondo_registration_message_filter( $translated_text, $text, $domain ) {
	// Only filter on login/registration pages.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking URL context only.
	$is_register = isset( $_GET['action'] ) && $_GET['action'] === 'register';
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking URL context only.
	$is_confirmed = isset( $_GET['checkemail'] ) && $_GET['checkemail'] === 'registered';

	if ( ! is_admin() && ( $is_register || $is_confirmed ) ) {
		if ( $text === 'Registration confirmation will be emailed to you.' ) {
			return 'Registration confirmation will be emailed to you. Your account is then subject to approval.';
		}
	}
	return $translated_text;
}
add_filter( 'gettext', 'rondo_registration_message_filter', 20, 3 );

/**
 * Change "Register For This Site" to "Register for Rondo Club"
 *
 * @param string $translated_text Translated text.
 * @param string $text            Original text.
 * @param string $domain          Text domain.
 * @return string Modified text.
 */
function rondo_change_register_notice_text( $translated_text, $text, $domain ) {
	if ( $text === 'Register For This Site' ) {
		return 'Register for Rondo Club';
	}
	return $translated_text;
}
add_filter( 'gettext', 'rondo_change_register_notice_text', 20, 3 );

/**
 * Change login page titles
 */
function rondo_login_page_titles() {
	?>
	<script>
		(function() {
			// Change page titles based on action
			var urlParams = new URLSearchParams(window.location.search);
			var action = urlParams.get('action');
			
			// Get the main heading (h1 or form title)
			var heading = document.querySelector('#login h1, .login form h1, .login h1');
			if (!heading) {
				// Try to find any heading in the login form
				heading = document.querySelector('.login form label, .login form .title');
			}
			
			// Change title based on page
			if (action === 'register') {
				// Registration page
				var title = document.querySelector('.login form h1, .login h1, #login h1');
				if (title) {
					title.textContent = 'Register for Rondo Club';
				}
				// Also check for any other title elements
				var formTitle = document.querySelector('.login form .title, .login .title');
				if (formTitle) {
					formTitle.textContent = 'Register for Rondo Club';
				}
			} else if (action === 'lostpassword' || action === 'retrievepassword') {
				// Lost password page
				var title = document.querySelector('.login form h1, .login h1, #login h1');
				if (title) {
					title.textContent = 'Lost your password for Rondo Club?';
				}
				var formTitle = document.querySelector('.login form .title, .login .title');
				if (formTitle) {
					formTitle.textContent = 'Lost your password for Rondo Club?';
				}
			} else {
				// Login page (default)
				var title = document.querySelector('.login form h1, .login h1, #login h1');
				if (title && !title.textContent.includes('Register') && !title.textContent.includes('Lost')) {
					title.textContent = 'Log in to Rondo Club';
				}
				var formTitle = document.querySelector('.login form .title, .login .title');
				if (formTitle && !formTitle.textContent.includes('Register') && !formTitle.textContent.includes('Lost')) {
					formTitle.textContent = 'Log in to Rondo Club';
				}
			}

			// Also check for page title in document title
			if (action === 'register') {
				document.title = 'Register for Rondo Club';
			} else if (action === 'lostpassword' || action === 'retrievepassword') {
				document.title = 'Lost your password for Rondo Club?';
			} else {
				document.title = 'Log in to Rondo Club';
			}
		})();
	</script>
	<?php
}
add_action( 'login_footer', 'rondo_login_page_titles' );

/**
 * Debug log rotation - runs daily via WP-Cron
 */
add_action( 'rondo_rotate_debug_log', 'rondo_rotate_debug_log' );
function rondo_rotate_debug_log() {
	$log_dir  = WP_CONTENT_DIR;
	$log_file = $log_dir . '/debug.log';
	$date     = date( 'Y-m-d' );

	// Only rotate if log exists and has content
	if ( file_exists( $log_file ) && filesize( $log_file ) > 0 ) {
		// Rotate current log
		$rotated = $log_dir . '/debug-' . $date . '.log';
		rename( $log_file, $rotated );

		// Create fresh empty log
		touch( $log_file );
		chmod( $log_file, 0644 );

		// Delete logs older than 7 days
		$files = glob( $log_dir . '/debug-*.log' );
		$now   = time();
		foreach ( $files as $file ) {
			if ( $now - filemtime( $file ) > 7 * DAY_IN_SECONDS ) {
				unlink( $file );
			}
		}
	}
}

// Schedule daily rotation if not already scheduled
if ( ! wp_next_scheduled( 'rondo_rotate_debug_log' ) ) {
	wp_schedule_event( strtotime( 'tomorrow midnight' ), 'daily', 'rondo_rotate_debug_log' );
}

/**
 * Warm the anniversary data cache daily at 00:01.
 *
 * Rebuilds the shared transient so the first dashboard load of the day
 * doesn't have to compute anniversary data on the fly.
 */
function rondo_warm_anniversary_cache() {
	$reminders_rest = new \Rondo\REST\Reminders();
	$data           = $reminders_rest->get_upcoming_anniversaries_data( 365, 20 );
	set_transient( 'rondo_anniversaries_365', $data, DAY_IN_SECONDS );
}
add_action( 'rondo_warm_anniversary_cache', 'rondo_warm_anniversary_cache' );

if ( ! wp_next_scheduled( 'rondo_warm_anniversary_cache' ) ) {
	// Schedule for 00:01 local time tomorrow.
	$rondo_tomorrow = new DateTimeImmutable( 'tomorrow 00:01', wp_timezone() );
	wp_schedule_event( $rondo_tomorrow->getTimestamp(), 'daily', 'rondo_warm_anniversary_cache' );
}
