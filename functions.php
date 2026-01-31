<?php
/**
 * Stadion Theme Functions
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

// Load Composer autoloader for PSR-4 classes and CardDAV support
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// PSR-4 namespaced class imports
use Stadion\Core\PostTypes;
use Stadion\Core\Taxonomies;
use Stadion\Core\AutoTitle;
use Stadion\Core\VolunteerStatus;
use Stadion\Core\AccessControl;
use Stadion\Core\UserRoles;
use Stadion\REST\Api;
use Stadion\REST\People;
use Stadion\REST\Teams;
use Stadion\REST\Commissies;
use Stadion\REST\Todos;
use Stadion\REST\Slack;
use Stadion\REST\ImportExport;
use Stadion\REST\Calendar as RESTCalendar;
use Stadion\REST\GoogleContacts as RESTGoogleContacts;
use Stadion\REST\GoogleSheets as RESTGoogleSheets;
use Stadion\REST\Feedback as RESTFeedback;
use Stadion\Calendar\Connections;
use Stadion\Calendar\Matcher;
use Stadion\Calendar\Sync;
use Stadion\Calendar\GoogleProvider;
use Stadion\Calendar\CalDAVProvider;
use Stadion\Calendar\GoogleOAuth;
use Stadion\Notifications\EmailChannel;
use Stadion\Notifications\SlackChannel;
use Stadion\Collaboration\CommentTypes;
use Stadion\Collaboration\MentionNotifications;
use Stadion\Collaboration\Reminders;
use Stadion\Import\Monica;
use Stadion\Import\VCard as VCardImport;
use Stadion\Import\GoogleContacts;
use Stadion\Import\GoogleContactsAPI;
use Stadion\Export\VCard as VCardExport;
use Stadion\Export\ICalFeed;
use Stadion\Export\GoogleContactsExport;
use Stadion\Contacts\GoogleContactsSync;
use Stadion\Sheets\GoogleSheetsConnection;
use Stadion\CardDAV\Server as CardDAVServer;
use Stadion\Data\InverseRelationships;
use Stadion\Data\TodoMigration;
use Stadion\CustomFields\Manager as CustomFieldsManager;
use Stadion\CustomFields\Validation as CustomFieldsValidation;
use Stadion\REST\CustomFields as RESTCustomFields;
use Stadion\VOG\VOGEmail;
use Stadion\Fees\MembershipFees;

define( 'STADION_THEME_DIR', get_template_directory() );
define( 'STADION_THEME_URL', get_template_directory_uri() );
define( 'STADION_THEME_VERSION', wp_get_theme()->get( 'Version' ) );

// Plugin constants (now part of theme)
define( 'STADION_VERSION', STADION_THEME_VERSION );
define( 'STADION_PLUGIN_DIR', STADION_THEME_DIR . '/includes' );
define( 'STADION_PLUGIN_URL', STADION_THEME_URL . '/includes' );

/**
 * Check for required dependencies
 */
function stadion_check_dependencies() {
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
					__( 'Stadion requires the following plugins: %s', 'stadion' ),
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
 * These allow code using old STADION_* class names to continue working
 * while the codebase transitions to PSR-4 namespaced classes.
 */
// Core classes
if ( ! class_exists( 'STADION_Post_Types' ) ) {
	class_alias( PostTypes::class, 'STADION_Post_Types' );
}
if ( ! class_exists( 'STADION_Taxonomies' ) ) {
	class_alias( Taxonomies::class, 'STADION_Taxonomies' );
}
if ( ! class_exists( 'STADION_Auto_Title' ) ) {
	class_alias( AutoTitle::class, 'STADION_Auto_Title' );
}
if ( ! class_exists( 'STADION_Access_Control' ) ) {
	class_alias( AccessControl::class, 'STADION_Access_Control' );
}
if ( ! class_exists( 'STADION_User_Roles' ) ) {
	class_alias( UserRoles::class, 'STADION_User_Roles' );
}

// REST classes
if ( ! class_exists( 'STADION_REST_API' ) ) {
	class_alias( Api::class, 'STADION_REST_API' );
}
if ( ! class_exists( 'STADION_REST_Base' ) ) {
	class_alias( \Stadion\REST\Base::class, 'STADION_REST_Base' );
}
if ( ! class_exists( 'STADION_REST_People' ) ) {
	class_alias( People::class, 'STADION_REST_People' );
}
if ( ! class_exists( 'STADION_REST_Teams' ) ) {
	class_alias( Teams::class, 'STADION_REST_Teams' );
}
if ( ! class_exists( 'STADION_REST_Commissies' ) ) {
	class_alias( Commissies::class, 'STADION_REST_Commissies' );
}
if ( ! class_exists( 'STADION_REST_Todos' ) ) {
	class_alias( Todos::class, 'STADION_REST_Todos' );
}
if ( ! class_exists( 'STADION_REST_Slack' ) ) {
	class_alias( Slack::class, 'STADION_REST_Slack' );
}
if ( ! class_exists( 'STADION_REST_Import_Export' ) ) {
	class_alias( ImportExport::class, 'STADION_REST_Import_Export' );
}
if ( ! class_exists( 'STADION_REST_Calendar' ) ) {
	class_alias( RESTCalendar::class, 'STADION_REST_Calendar' );
}
if ( ! class_exists( 'STADION_REST_Feedback' ) ) {
	class_alias( RESTFeedback::class, 'STADION_REST_Feedback' );
}

// Calendar classes
if ( ! class_exists( 'STADION_Calendar_Connections' ) ) {
	class_alias( Connections::class, 'STADION_Calendar_Connections' );
}
if ( ! class_exists( 'STADION_Calendar_Matcher' ) ) {
	class_alias( Matcher::class, 'STADION_Calendar_Matcher' );
}
if ( ! class_exists( 'STADION_Calendar_Sync' ) ) {
	class_alias( Sync::class, 'STADION_Calendar_Sync' );
}
if ( ! class_exists( 'STADION_Google_Calendar_Provider' ) ) {
	class_alias( GoogleProvider::class, 'STADION_Google_Calendar_Provider' );
}
if ( ! class_exists( 'STADION_CalDAV_Provider' ) ) {
	class_alias( CalDAVProvider::class, 'STADION_CalDAV_Provider' );
}
if ( ! class_exists( 'STADION_Google_OAuth' ) ) {
	class_alias( GoogleOAuth::class, 'STADION_Google_OAuth' );
}

// Google Sheets class
if ( ! class_exists( 'STADION_Google_Sheets_Connection' ) ) {
	class_alias( GoogleSheetsConnection::class, 'STADION_Google_Sheets_Connection' );
}

// Notification classes
if ( ! class_exists( 'STADION_Notification_Channel' ) ) {
	class_alias( \Stadion\Notifications\Channel::class, 'STADION_Notification_Channel' );
}
if ( ! class_exists( 'STADION_Email_Channel' ) ) {
	class_alias( EmailChannel::class, 'STADION_Email_Channel' );
}
if ( ! class_exists( 'STADION_Slack_Channel' ) ) {
	class_alias( SlackChannel::class, 'STADION_Slack_Channel' );
}

// Collaboration classes
if ( ! class_exists( 'STADION_Comment_Types' ) ) {
	class_alias( CommentTypes::class, 'STADION_Comment_Types' );
}
if ( ! class_exists( 'STADION_Mentions' ) ) {
	class_alias( \Stadion\Collaboration\Mentions::class, 'STADION_Mentions' );
}
if ( ! class_exists( 'STADION_Mention_Notifications' ) ) {
	class_alias( MentionNotifications::class, 'STADION_Mention_Notifications' );
}
if ( ! class_exists( 'STADION_Reminders' ) ) {
	class_alias( Reminders::class, 'STADION_Reminders' );
}

// Import classes
if ( ! class_exists( 'STADION_Monica_Import' ) ) {
	class_alias( Monica::class, 'STADION_Monica_Import' );
}
if ( ! class_exists( 'STADION_VCard_Import' ) ) {
	class_alias( VCardImport::class, 'STADION_VCard_Import' );
}
if ( ! class_exists( 'STADION_Google_Contacts_Import' ) ) {
	class_alias( GoogleContacts::class, 'STADION_Google_Contacts_Import' );
}
if ( ! class_exists( 'STADION_Google_Contacts_API_Import' ) ) {
	class_alias( GoogleContactsAPI::class, 'STADION_Google_Contacts_API_Import' );
}

// Export classes
if ( ! class_exists( 'STADION_VCard_Export' ) ) {
	class_alias( VCardExport::class, 'STADION_VCard_Export' );
}
if ( ! class_exists( 'STADION_ICal_Feed' ) ) {
	class_alias( ICalFeed::class, 'STADION_ICal_Feed' );
}
if ( ! class_exists( 'STADION_Google_Contacts_Export' ) ) {
	class_alias( GoogleContactsExport::class, 'STADION_Google_Contacts_Export' );
}

// CardDAV class
if ( ! class_exists( 'STADION_CardDAV_Server' ) ) {
	class_alias( CardDAVServer::class, 'STADION_CardDAV_Server' );
}

// Data classes
if ( ! class_exists( 'STADION_Inverse_Relationships' ) ) {
	class_alias( InverseRelationships::class, 'STADION_Inverse_Relationships' );
}
if ( ! class_exists( 'STADION_Todo_Migration' ) ) {
	class_alias( TodoMigration::class, 'STADION_Todo_Migration' );
}
if ( ! class_exists( 'STADION_Credential_Encryption' ) ) {
	class_alias( \Stadion\Data\CredentialEncryption::class, 'STADION_Credential_Encryption' );
}

// CustomFields classes
if ( ! class_exists( 'STADION_Custom_Fields_Manager' ) ) {
	class_alias( CustomFieldsManager::class, 'STADION_Custom_Fields_Manager' );
}
if ( ! class_exists( 'STADION_Custom_Fields_Validation' ) ) {
	class_alias( CustomFieldsValidation::class, 'STADION_Custom_Fields_Validation' );
}
if ( ! class_exists( 'STADION_REST_Custom_Fields' ) ) {
	class_alias( RESTCustomFields::class, 'STADION_REST_Custom_Fields' );
}

// VOG classes
if ( ! class_exists( 'STADION_VOG_Email' ) ) {
	class_alias( VOGEmail::class, 'STADION_VOG_Email' );
}

/**
 * Check if current request is a CardDAV request
 */
function stadion_is_carddav_request() {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
	return strpos( $request_uri, '/carddav' ) === 0;
}

/**
 * Check if current request is a REST API request
 */
function stadion_is_rest_request() {
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return true;
	}

	// Check for REST API URL pattern before REST_REQUEST is defined
	$rest_prefix = rest_get_url_prefix();
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';

	return strpos( $request_uri, '/' . $rest_prefix . '/' ) !== false;
}

/**
 * Check if current request is for the iCal feed
 */
function stadion_is_ical_request() {
	$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
	return strpos( $request_uri, '/prm-ical/' ) !== false || strpos( $request_uri, 'prm-ical' ) !== false;
}

/**
 * Initialize the CRM functionality with conditional class loading
 */
function stadion_init() {
	// Prevent double initialization
	static $initialized = false;
	if ( $initialized ) {
		return;
	}

	if ( ! stadion_check_dependencies() ) {
		return;
	}

	// Core classes - always needed for WordPress integration
	new PostTypes();
	new Taxonomies();
	new AccessControl();
	new UserRoles();

	// iCal feed - only load for iCal requests
	if ( stadion_is_ical_request() ) {
		new ICalFeed();
		$initialized = true;
		return; // iCal requests don't need other functionality
	}

	// CardDAV server - only load for CardDAV requests
	if ( stadion_is_carddav_request() ) {
		new CardDAVServer();
		$initialized = true;
		return; // CardDAV requests don't need other functionality
	}

	// Skip loading heavy classes for non-relevant requests
	$is_admin = is_admin();
	$is_rest  = stadion_is_rest_request();
	$is_cron  = defined( 'DOING_CRON' ) && DOING_CRON;

	// Classes needed for content creation/editing (admin, REST, or cron)
	if ( $is_admin || $is_rest || $is_cron ) {
		new AutoTitle();
		new VolunteerStatus();
		new InverseRelationships();
		new CommentTypes();
		new MentionNotifications();

		// Initialize custom field validation (unique constraint).
		new CustomFieldsValidation();

		// Initialize Google Contacts export hooks (save_post triggers cron job)
		GoogleContactsExport::init();
	}

	// REST API classes - only for REST requests
	if ( $is_rest ) {
		new Api();
		new People();
		new Teams();
		new Commissies();
		new Todos();
		new Slack();
		new ImportExport();
		new RESTCalendar();
		new RESTGoogleContacts();
		new RESTGoogleSheets();
		new RESTCustomFields();
		new RESTFeedback();
		new Monica();
		new VCardImport();
		new GoogleContacts();
	}

	// Reminders - only for admin or cron
	if ( $is_admin || $is_cron ) {
		new Reminders();
	}

	// Calendar sync - needs hooks registered for cron schedule filter
	// Initialize on all requests to register cron_schedules filter
	new Sync();

	// Google Contacts sync - needs hooks registered for cron schedule filter
	new GoogleContactsSync();

	// iCal feed - also initialize on non-iCal requests for hook registration
	// but we check for its specific request above for early return optimization
	if ( ! stadion_is_ical_request() ) {
		new ICalFeed();
	}

	// CardDAV server - initialize for rewrite rule registration
	if ( ! stadion_is_carddav_request() ) {
		new CardDAVServer();
	}

	// Initialize CardDAV sync hooks to track changes made via web UI
	// This must run on all requests, not just CardDAV requests
	\Stadion\CardDAV\CardDAVBackend::init_hooks();

	$initialized = true;
}
// Initialize early for REST API requests, but also check on plugins_loaded
// in case ACF Pro isn't loaded yet (plugins load after themes)
add_action( 'after_setup_theme', 'stadion_init', 5 );
add_action( 'plugins_loaded', 'stadion_init', 5 );

// Load WP-CLI commands if WP-CLI is available
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once STADION_PLUGIN_DIR . '/class-wp-cli.php';
	new TodoMigration();
}

/**
 * Theme setup
 */
function stadion_theme_setup() {
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
			'primary' => __( 'Primary Menu', 'stadion' ),
		]
	);
}
add_action( 'after_setup_theme', 'stadion_theme_setup' );

/**
 * Set default page title for SPA (React will update it dynamically)
 */
function stadion_theme_document_title_parts( $title ) {
	// Only modify title on frontend (not admin)
	if ( is_admin() ) {
		return $title;
	}

	// Set a default title - React will update it when routes change
	$title['title'] = 'Stadion';
	$title['site']  = '';

	return $title;
}
add_filter( 'document_title_parts', 'stadion_theme_document_title_parts', 20 );

/**
 * Enqueue scripts and styles
 */
function stadion_theme_enqueue_assets() {
	$dist_dir = STADION_THEME_DIR . '/dist';
	$dist_url = STADION_THEME_URL . '/dist';

	// Check if we have built assets (Vite puts manifest in .vite subdirectory)
	$manifest_path = $dist_dir . '/.vite/manifest.json';
	if ( ! file_exists( $manifest_path ) ) {
		// Fallback to root dist directory
		$manifest_path = $dist_dir . '/manifest.json';
	}

	if ( file_exists( $manifest_path ) ) {
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
						STADION_THEME_VERSION
					);
				}
			}

			if ( isset( $main_js['file'] ) ) {
				wp_enqueue_script(
					'prm-theme-script',
					$dist_url . '/' . $main_js['file'],
					[],
					STADION_THEME_VERSION,
					true
				);

				// Localize script with WordPress data
				wp_localize_script( 'prm-theme-script', 'stadionConfig', stadion_get_js_config() );
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
				echo '<script>console.error("Stadion: Build files not found. Please run npm run build.");</script>';
			}
		);
	}
}
add_action( 'wp_enqueue_scripts', 'stadion_theme_enqueue_assets' );

/**
 * Get JavaScript configuration
 */
function stadion_get_js_config() {
	$user    = wp_get_current_user();
	$user_id = get_current_user_id();

	// Get build time from manifest file modification time.
	// This ensures every build produces a unique timestamp.
	$build_time    = null;
	$manifest_path = STADION_THEME_DIR . '/dist/.vite/manifest.json';
	if ( file_exists( $manifest_path ) ) {
		$build_time = gmdate( 'c', filemtime( $manifest_path ) );
	} else {
		// Fallback to current time for dev mode (Vite dev server).
		$build_time = gmdate( 'c' );
	}

	// Get user's linked person ID (for filtering current user from attendee lists)
	$linked_person_id = $user_id ? (int) get_user_meta( $user_id, 'stadion_linked_person_id', true ) : null;

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
		'themeUrl'            => STADION_THEME_URL,
		'version'             => wp_get_theme()->get( 'Version' ),
		'buildTime'           => $build_time,
		'currentUserPersonId' => $linked_person_id ?: null,
	];
}

/**
 * Add config to head for initial page load
 */
function stadion_theme_add_config_to_head() {
	$config = stadion_get_js_config();
	echo '<script>window.stadionConfig = ' . wp_json_encode( $config ) . ';</script>';
}
add_action( 'wp_head', 'stadion_theme_add_config_to_head', 0 );

/**
 * Output PWA meta tags for iOS and Android support
 *
 * vite-plugin-pwa handles manifest generation, but we need to manually
 * inject meta tags since WordPress uses PHP templates, not index.html.
 */
function stadion_pwa_meta_tags() {
	$theme_url = STADION_THEME_URL;
	?>
	<!-- PWA Meta Tags -->
	<meta name="mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="default">
	<meta name="apple-mobile-web-app-title" content="Stadion">

	<!-- Apple Touch Icon -->
	<link rel="apple-touch-icon" href="<?php echo esc_url( $theme_url . '/public/icons/apple-touch-icon-180x180.png' ); ?>">

	<!-- Manifest -->
	<link rel="manifest" href="<?php echo esc_url( $theme_url . '/dist/manifest.webmanifest' ); ?>">

	<!-- Theme Color (default orange, React will update dynamically) -->
	<meta name="theme-color" media="(prefers-color-scheme: light)" content="#f97316">
	<meta name="theme-color" media="(prefers-color-scheme: dark)" content="#ea580c">
	<?php
}
add_action( 'wp_head', 'stadion_pwa_meta_tags', 2 );

/**
 * Add favicon to head
 *
 * Note: Dynamic favicon is handled by React's useTheme hook (src/hooks/useTheme.js)
 * which generates an inline SVG with the current accent color. The static favicon
 * below is only used before React hydrates, then React takes over management.
 *
 * To support dynamic accent colors, we don't output a static favicon here.
 * React will create the favicon link element on mount with the user's accent color.
 */
// Removed static favicon output - React manages favicon dynamically via useTheme.js
// function stadion_theme_add_favicon() {
//     $favicon_url = STADION_THEME_URL . '/favicon.svg';
//     echo '<link rel="icon" type="image/svg+xml" href="' . esc_url($favicon_url) . '">';
//     echo '<link rel="alternate icon" href="' . esc_url($favicon_url) . '">';
// }
// add_action('wp_head', 'stadion_theme_add_favicon', 1);

/**
 * Hide admin bar on frontend - it interferes with the SPA interface
 */
function stadion_theme_remove_admin_bar() {
	if ( ! is_admin() ) {
		show_admin_bar( false );
	}
}
add_action( 'after_setup_theme', 'stadion_theme_remove_admin_bar' );

/**
 * Redirect WordPress backend URLs to SPA frontend routes
 *
 * Handles URLs like ?post_type=person&p=123 → /people/123
 */
function stadion_redirect_backend_urls() {
	// Don't redirect admin, login, or API requests.
	if ( is_admin() || 'wp-login.php' === $GLOBALS['pagenow'] ) {
		return;
	}

	// Check for post_type and p query parameters (WordPress backend URL format)
	$post_type = isset( $_GET['post_type'] ) ? sanitize_key( $_GET['post_type'] ) : '';
	$post_id   = isset( $_GET['p'] ) ? absint( $_GET['p'] ) : 0;

	if ( ! $post_type || ! $post_id ) {
		return;
	}

	// Map post types to frontend routes
	$route_map = [
		'person'         => 'people',
		'team'           => 'teams',
		'commissie'      => 'commissies',
		'important_date' => 'dates',
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
add_action( 'template_redirect', 'stadion_redirect_backend_urls', 0 ); // Priority 0 to run before other redirects

/**
 * Redirect all frontend requests to index.php (SPA)
 */
function stadion_theme_template_redirect() {
	// Don't redirect admin, login, or API requests.
	if ( is_admin() || 'wp-login.php' === $GLOBALS['pagenow'] ) {
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
	if ( empty( $path ) || '/' === $path ) {
		$is_app_route = true;
	}

	// Check if it starts with any of our app route prefixes.
	foreach ( $app_routes as $route ) {
		if ( $route === $path || 0 === strpos( $path, $route . '/' ) ) {
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
add_action( 'template_redirect', 'stadion_theme_template_redirect', 1 );

/**
 * Handle client-side routing - return index.php for all routes
 */
function stadion_theme_rewrite_rules() {
	add_rewrite_rule( '^app/?', 'index.php', 'top' );
	add_rewrite_rule( '^app/(.+)/?', 'index.php', 'top' );
}
add_action( 'init', 'stadion_theme_rewrite_rules' );

/**
 * Theme activation - includes CRM initialization
 */
function stadion_theme_activation() {
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

	// Schedule calendar background sync
	$calendar_sync = new Sync();
	$calendar_sync->schedule_sync();

	// Schedule Google Contacts background sync
	$contacts_sync = new GoogleContactsSync();
	$contacts_sync->schedule_sync();

	// Also handle theme-specific rewrite rules
	stadion_theme_rewrite_rules();

	// Initialize CardDAV server rewrite rules
	$carddav = new CardDAVServer();
	$carddav->register_rewrite_rules();
}
add_action( 'after_switch_theme', 'stadion_theme_activation' );

/**
 * Theme deactivation - cleanup CRM functionality
 */
function stadion_theme_deactivation() {
	// Clear all per-user reminder cron jobs
	wp_clear_scheduled_hook( 'stadion_user_reminder' );

	// Clear legacy scheduled hook (for backward compatibility)
	wp_clear_scheduled_hook( 'stadion_daily_reminder_check' );

	// Clear calendar sync cron job
	$calendar_sync = new Sync();
	$calendar_sync->unschedule_sync();

	// Clear Google Contacts sync cron job
	$contacts_sync = new GoogleContactsSync();
	$contacts_sync->unschedule_sync();

	// Remove custom user role (must call directly since switch_theme hook already fired)
	$user_roles = new UserRoles();
	$user_roles->remove_role();

	// Flush rewrite rules
	flush_rewrite_rules();
}
add_action( 'switch_theme', 'stadion_theme_deactivation' );

/**
 * Unschedule user reminder cron when user is deleted
 */
function stadion_user_deleted( $user_id ) {
	$reminders = new Reminders();
	$reminders->unschedule_user_reminder( $user_id );
}
add_action( 'delete_user', 'stadion_user_deleted' );

/**
 * Add type="module" to script tags
 *
 * @param string $tag    Script tag HTML.
 * @param string $handle Script handle.
 * @param string $src    Script source URL.
 * @return string Modified script tag.
 */
function stadion_theme_script_type( $tag, $handle, $src ) {
	if ( 'prm-theme-script' === $handle ) {
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Modifying enqueued script tag.
		return '<script type="module" src="' . esc_url( $src ) . '"></script>';
	}
	return $tag;
}
add_filter( 'script_loader_tag', 'stadion_theme_script_type', 10, 3 );

/**
 * Disable WordPress emojis (performance)
 */
function stadion_theme_disable_emojis() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
}
add_action( 'init', 'stadion_theme_disable_emojis' );

/**
 * Clean up WordPress head
 */
function stadion_theme_cleanup_head() {
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head' );
}
add_action( 'init', 'stadion_theme_cleanup_head' );

/**
 * Load ACF JSON from theme directory
 */
function stadion_acf_json_load_point( $paths ) {
	$paths[] = STADION_THEME_DIR . '/acf-json';
	return $paths;
}
add_filter( 'acf/settings/load_json', 'stadion_acf_json_load_point' );

/**
 * Save ACF JSON to theme directory (for development)
 */
function stadion_acf_json_save_point( $path ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		$path = STADION_THEME_DIR . '/acf-json';
	}
	return $path;
}
add_filter( 'acf/settings/save_json', 'stadion_acf_json_save_point' );

/**
 * Invalidate email lookup cache when a person is saved
 *
 * This ensures that the contact matching cache stays in sync
 * when contact info (emails) are updated on person records.
 */
function stadion_invalidate_email_lookup_on_person_save( $post_id ) {
	if ( get_post_type( $post_id ) === 'person' ) {
		$user_id = get_post_field( 'post_author', $post_id );
		if ( $user_id ) {
			Matcher::invalidate_cache( $user_id );
		}
	}
}
add_action( 'acf/save_post', 'stadion_invalidate_email_lookup_on_person_save', 20 );

/**
 * Custom login page styling
 */
function stadion_login_styles() {
	$favicon_url = STADION_THEME_URL . '/favicon.svg';
	?>
	<style type="text/css">
		/* Background */
		body.login {
			background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 50%, #fde68a 100%);
			font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
		}
		
		/* Login form container */
		#login {
			padding: 8% 0 0;
		}
		
		/* Logo */
		#login h1 a {
			background-image: url('<?php echo esc_url( $favicon_url ); ?>');
			background-size: contain;
			background-position: center center;
			background-repeat: no-repeat;
			width: 84px;
			height: 84px;
			margin-bottom: 20px;
		}
		
		/* App name below logo */
		#login h1::after {
			content: 'Stadion';
			display: block;
			text-align: center;
			font-size: 28px;
			font-weight: 600;
			color: #92400e;
			margin-top: 10px;
			letter-spacing: -0.5px;
		}
		
		/* Form box */
		.login form {
			background: #ffffff;
			border: 1px solid #fde68a;
			border-radius: 12px;
			box-shadow: 0 10px 25px rgba(180, 83, 9, 0.1);
			padding: 26px 24px;
		}
		
		/* Input fields */
		.login input[type="text"],
		.login input[type="password"] {
			border: 1px solid #fde68a;
			border-radius: 8px;
			padding: 10px 14px;
			font-size: 15px;
			transition: border-color 0.2s, box-shadow 0.2s;
		}
		
		.login input[type="text"]:focus,
		.login input[type="password"]:focus {
			border-color: #d97706;
			box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.15);
			outline: none;
		}
		
		/* Labels */
		.login label {
			color: #78350f;
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
			background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%) !important;
			border: none !important;
			border-radius: 8px !important;
			box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3) !important;
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
			background: linear-gradient(135deg, #d97706 0%, #b45309 100%) !important;
			box-shadow: 0 6px 16px rgba(180, 83, 9, 0.4) !important;
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
			accent-color: #d97706;
		}
		
		/* Links */
		.login #nav a,
		.login #backtoblog a {
			color: #b45309;
			text-decoration: none;
			font-size: 13px;
			transition: color 0.2s;
		}
		
		.login #nav a:hover,
		.login #backtoblog a:hover {
			color: #92400e;
			text-decoration: underline;
		}
		
		/* Error/message boxes */
		.login .message,
		.login .success {
			border-left-color: #d97706;
			background: #fffbeb;
		}
		
		/* Notice info message styling */
		.login .notice.notice-info.message {
			border-left-color: #d97706 !important;
			margin-top: 20px !important;
		}
		
		.login #login_error {
			border-left-color: #dc2626;
		}
		
		/* Privacy policy link */
		.login .privacy-policy-page-link a {
			color: #b45309;
		}
		
		/* Hide "Go to site" for cleaner look */
		#backtoblog {
			display: none;
		}
	</style>
	<?php
}
add_action( 'login_enqueue_scripts', 'stadion_login_styles' );

/**
 * Add favicon to login page
 */
function stadion_login_favicon() {
	$favicon_url = STADION_THEME_URL . '/favicon.svg';
	echo '<link rel="icon" type="image/svg+xml" href="' . esc_url( $favicon_url ) . '">';
	echo '<link rel="alternate icon" href="' . esc_url( $favicon_url ) . '">';
}
add_action( 'login_head', 'stadion_login_favicon' );

/**
 * Change login logo URL to homepage
 */
function stadion_login_logo_url() {
	return home_url( '/' );
}
add_filter( 'login_headerurl', 'stadion_login_logo_url' );

/**
 * Change login logo title
 */
function stadion_login_logo_title() {
	return 'Stadion';
}
add_filter( 'login_headertext', 'stadion_login_logo_title' );

/**
 * Redirect users to homepage after login
 */
function stadion_login_redirect( $redirect_to, $request, $user ) {
	// Only redirect if no specific redirect was requested
	if ( isset( $_GET['redirect_to'] ) && ! empty( $_GET['redirect_to'] ) ) {
		return $redirect_to;
	}

	// Redirect all users to the homepage
	return home_url( '/' );
}
add_filter( 'login_redirect', 'stadion_login_redirect', 10, 3 );

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
function stadion_registration_message_filter( $translated_text, $text, $domain ) {
	// Only filter on login/registration pages.
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking URL context only.
	$is_register  = isset( $_GET['action'] ) && 'register' === $_GET['action'];
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Checking URL context only.
	$is_confirmed = isset( $_GET['checkemail'] ) && 'registered' === $_GET['checkemail'];

	if ( ! is_admin() && ( $is_register || $is_confirmed ) ) {
		if ( 'Registration confirmation will be emailed to you.' === $text ) {
			return 'Registration confirmation will be emailed to you. Your account is then subject to approval.';
		}
	}
	return $translated_text;
}
add_filter( 'gettext', 'stadion_registration_message_filter', 20, 3 );

/**
 * Change "Register For This Site" to "Register for Stadion"
 *
 * @param string $translated_text Translated text.
 * @param string $text            Original text.
 * @param string $domain          Text domain.
 * @return string Modified text.
 */
function stadion_change_register_notice_text( $translated_text, $text, $domain ) {
	if ( 'Register For This Site' === $text ) {
		return 'Register for Stadion';
	}
	return $translated_text;
}
add_filter( 'gettext', 'stadion_change_register_notice_text', 20, 3 );

/**
 * Change login page titles
 */
function stadion_login_page_titles() {
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
					title.textContent = 'Register for Stadion';
				}
				// Also check for any other title elements
				var formTitle = document.querySelector('.login form .title, .login .title');
				if (formTitle) {
					formTitle.textContent = 'Register for Stadion';
				}
			} else if (action === 'lostpassword' || action === 'retrievepassword') {
				// Lost password page
				var title = document.querySelector('.login form h1, .login h1, #login h1');
				if (title) {
					title.textContent = 'Lost your password for Stadion?';
				}
				var formTitle = document.querySelector('.login form .title, .login .title');
				if (formTitle) {
					formTitle.textContent = 'Lost your password for Stadion?';
				}
			} else {
				// Login page (default)
				var title = document.querySelector('.login form h1, .login h1, #login h1');
				if (title && !title.textContent.includes('Register') && !title.textContent.includes('Lost')) {
					title.textContent = 'Log in to Stadion';
				}
				var formTitle = document.querySelector('.login form .title, .login .title');
				if (formTitle && !formTitle.textContent.includes('Register') && !formTitle.textContent.includes('Lost')) {
					formTitle.textContent = 'Log in to Stadion';
				}
			}
			
			// Also check for page title in document title
			if (action === 'register') {
				document.title = 'Register for Stadion';
			} else if (action === 'lostpassword' || action === 'retrievepassword') {
				document.title = 'Lost your password for Stadion?';
			} else {
				document.title = 'Log in to Stadion';
			}
		})();
	</script>
	<?php
}
add_action( 'login_footer', 'stadion_login_page_titles' );

/**
 * Debug log rotation - runs daily via WP-Cron
 */
add_action( 'stadion_rotate_debug_log', 'stadion_rotate_debug_log' );
function stadion_rotate_debug_log() {
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
if ( ! wp_next_scheduled( 'stadion_rotate_debug_log' ) ) {
    wp_schedule_event( strtotime( 'tomorrow midnight' ), 'daily', 'stadion_rotate_debug_log' );
}
