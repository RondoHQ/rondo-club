<?php
/**
 * Caelis Theme Functions
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PRM_THEME_VERSION', '1.0.0');
define('PRM_THEME_DIR', get_template_directory());
define('PRM_THEME_URL', get_template_directory_uri());

// Plugin constants (now part of theme)
define('PRM_VERSION', '1.0.0');
define('PRM_PLUGIN_DIR', PRM_THEME_DIR . '/includes');
define('PRM_PLUGIN_URL', PRM_THEME_URL . '/includes');

/**
 * Check for required dependencies
 */
function prm_check_dependencies() {
    $missing = [];
    
    // Check for ACF Pro
    if (!class_exists('ACF')) {
        $missing[] = 'Advanced Custom Fields Pro';
    }
    
    if (!empty($missing)) {
        add_action('admin_notices', function() use ($missing) {
            $message = sprintf(
                __('Caelis requires the following plugins: %s', 'personal-crm'),
                implode(', ', $missing)
            );
            echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
        });
        return false;
    }
    
    return true;
}

/**
 * Initialize the CRM functionality
 */
function prm_init() {
    // Prevent double initialization
    static $initialized = false;
    if ($initialized) {
        return;
    }
    
    if (!prm_check_dependencies()) {
        return;
    }
    
    // Load includes
    require_once PRM_PLUGIN_DIR . '/class-post-types.php';
    require_once PRM_PLUGIN_DIR . '/class-taxonomies.php';
    require_once PRM_PLUGIN_DIR . '/class-auto-title.php';
    require_once PRM_PLUGIN_DIR . '/class-access-control.php';
    require_once PRM_PLUGIN_DIR . '/class-comment-types.php';
    require_once PRM_PLUGIN_DIR . '/class-rest-api.php';
    require_once PRM_PLUGIN_DIR . '/class-reminders.php';
    require_once PRM_PLUGIN_DIR . '/class-monica-import.php';
    require_once PRM_PLUGIN_DIR . '/class-inverse-relationships.php';
    require_once PRM_PLUGIN_DIR . '/class-ical-feed.php';

    // Initialize classes
    new PRM_Post_Types();
    new PRM_Taxonomies();
    new PRM_Auto_Title();
    new PRM_Access_Control();
    new PRM_Comment_Types();
    new PRM_REST_API();
    new PRM_Reminders();
    new PRM_Monica_Import();
    new PRM_Inverse_Relationships();
    new PRM_ICal_Feed();
    
    $initialized = true;
}
// Initialize early for REST API requests, but also check on plugins_loaded
// in case ACF Pro isn't loaded yet (plugins load after themes)
add_action('after_setup_theme', 'prm_init', 5);
add_action('plugins_loaded', 'prm_init', 5);

/**
 * Theme setup
 */
function prm_theme_setup() {
    // Add theme support
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ]);
    
    // Register nav menus (optional, React handles navigation)
    register_nav_menus([
        'primary' => __('Primary Menu', 'personal-crm-theme'),
    ]);
}
add_action('after_setup_theme', 'prm_theme_setup');

/**
 * Set default page title for SPA (React will update it dynamically)
 */
function prm_theme_document_title_parts($title) {
    // Only modify title on frontend (not admin)
    if (is_admin()) {
        return $title;
    }
    
    // Set a default title - React will update it when routes change
    $title['title'] = 'Caelis';
    $title['site'] = '';
    
    return $title;
}
add_filter('document_title_parts', 'prm_theme_document_title_parts', 20);

/**
 * Enqueue scripts and styles
 */
function prm_theme_enqueue_assets() {
    $dist_dir = PRM_THEME_DIR . '/dist';
    $dist_url = PRM_THEME_URL . '/dist';
    
    // Check if we have built assets (Vite puts manifest in .vite subdirectory)
    $manifest_path = $dist_dir . '/.vite/manifest.json';
    if (!file_exists($manifest_path)) {
        // Fallback to root dist directory
        $manifest_path = $dist_dir . '/manifest.json';
    }
    
    if (file_exists($manifest_path)) {
        $manifest = json_decode(file_get_contents($manifest_path), true);
        
        // Enqueue the main JS file
        if (isset($manifest['src/main.jsx'])) {
            $main_js = $manifest['src/main.jsx'];
            
            if (isset($main_js['css'])) {
                foreach ($main_js['css'] as $css_file) {
                    wp_enqueue_style(
                        'prm-theme-style',
                        $dist_url . '/' . $css_file,
                        [],
                        PRM_THEME_VERSION
                    );
                }
            }
            
            if (isset($main_js['file'])) {
                wp_enqueue_script(
                    'prm-theme-script',
                    $dist_url . '/' . $main_js['file'],
                    [],
                    PRM_THEME_VERSION,
                    true
                );
                
                // Localize script with WordPress data
                wp_localize_script('prm-theme-script', 'prmConfig', prm_get_js_config());
            }
        }
    } else {
        // Development mode - load from Vite dev server
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('wp_head', function() {
                echo '<script type="module" src="http://localhost:5173/@vite/client"></script>';
                echo '<script type="module" src="http://localhost:5173/src/main.jsx"></script>';
            });
        } else {
            // Production mode but no build found - show error
            add_action('wp_head', function() {
                echo '<script>console.error("Caelis: Build files not found. Please run npm run build.");</script>';
            });
        }
    }
}
add_action('wp_enqueue_scripts', 'prm_theme_enqueue_assets');

/**
 * Get JavaScript configuration
 */
function prm_get_js_config() {
    return [
        'apiUrl'      => rest_url(),
        'nonce'       => wp_create_nonce('wp_rest'),
        'siteUrl'     => home_url(),
        'siteName'    => get_bloginfo('name'),
        'userId'      => get_current_user_id(),
        'isLoggedIn'  => is_user_logged_in(),
        'loginUrl'    => wp_login_url(),
        'logoutUrl'   => wp_logout_url(home_url()),
        'adminUrl'    => admin_url(),
        'themeUrl'    => PRM_THEME_URL,
    ];
}

/**
 * Add config to head for initial page load
 */
function prm_theme_add_config_to_head() {
    $config = prm_get_js_config();
    echo '<script>window.prmConfig = ' . wp_json_encode($config) . ';</script>';
}
add_action('wp_head', 'prm_theme_add_config_to_head', 0);

/**
 * Add favicon to head
 */
function prm_theme_add_favicon() {
    $favicon_url = PRM_THEME_URL . '/favicon.svg';
    echo '<link rel="icon" type="image/svg+xml" href="' . esc_url($favicon_url) . '">';
    echo '<link rel="alternate icon" href="' . esc_url($favicon_url) . '">';
}
add_action('wp_head', 'prm_theme_add_favicon', 1);

/**
 * Hide admin bar on frontend - it interferes with the SPA interface
 */
function prm_theme_remove_admin_bar() {
    if (!is_admin()) {
        show_admin_bar(false);
    }
}
add_action('after_setup_theme', 'prm_theme_remove_admin_bar');

/**
 * Redirect all frontend requests to index.php (SPA)
 */
function prm_theme_template_redirect() {
    // Don't redirect admin, login, or API requests
    if (is_admin() || $GLOBALS['pagenow'] === 'wp-login.php') {
        return;
    }
    
    // Don't redirect REST API requests
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }
    
    // Don't redirect AJAX requests
    if (wp_doing_ajax()) {
        return;
    }
    
    // Get the request URI
    $request_uri = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
    
    // Remove query string for matching
    $path = trim($request_uri, '/');
    
    // If this is a request for our app routes, serve index.php
    // This includes: /, /people, /people/:id, /companies, /companies/:id, /dates, /dates/:id, /settings, /login
    $app_routes = [
        'people',
        'companies', 
        'dates',
        'settings',
        'login'
    ];
    
    $is_app_route = false;
    
    // Check if it's the root path
    if (empty($path) || $path === '/') {
        $is_app_route = true;
    }
    
    // Check if it starts with any of our app route prefixes
    foreach ($app_routes as $route) {
        if ($path === $route || strpos($path, $route . '/') === 0) {
            $is_app_route = true;
            break;
        }
    }
    
    // Also handle 404s on frontend (WordPress might return 404 for our routes)
    if ($is_app_route || (is_404() && !is_admin())) {
        // Set status to 200 so React Router can handle it
        status_header(200);
        // Clear any 404 query flags
        global $wp_query;
        $wp_query->is_404 = false;
        // Load index.php for React Router
        include(get_template_directory() . '/index.php');
        exit;
    }
}
add_action('template_redirect', 'prm_theme_template_redirect', 1);

/**
 * Handle client-side routing - return index.php for all routes
 */
function prm_theme_rewrite_rules() {
    add_rewrite_rule('^app/?', 'index.php', 'top');
    add_rewrite_rule('^app/(.+)/?', 'index.php', 'top');
}
add_action('init', 'prm_theme_rewrite_rules');

/**
 * Theme activation - includes CRM initialization
 */
function prm_theme_activation() {
    // Trigger post type registration
    require_once PRM_PLUGIN_DIR . '/class-post-types.php';
    require_once PRM_PLUGIN_DIR . '/class-taxonomies.php';
    
    $post_types = new PRM_Post_Types();
    $post_types->register_post_types();
    
    $taxonomies = new PRM_Taxonomies();
    $taxonomies->register_taxonomies();
    
    // Flush rewrite rules
    flush_rewrite_rules();
    
    // Schedule reminder cron job
    if (!wp_next_scheduled('prm_daily_reminder_check')) {
        wp_schedule_event(time(), 'daily', 'prm_daily_reminder_check');
    }
    
    // Also handle theme-specific rewrite rules
    prm_theme_rewrite_rules();
}
add_action('after_switch_theme', 'prm_theme_activation');

/**
 * Theme deactivation - cleanup CRM functionality
 */
function prm_theme_deactivation() {
    // Clear scheduled hooks
    wp_clear_scheduled_hook('prm_daily_reminder_check');
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
add_action('switch_theme', 'prm_theme_deactivation');

/**
 * Add type="module" to script tags
 */
function prm_theme_script_type($tag, $handle, $src) {
    if ($handle === 'prm-theme-script') {
        return '<script type="module" src="' . esc_url($src) . '"></script>';
    }
    return $tag;
}
add_filter('script_loader_tag', 'prm_theme_script_type', 10, 3);

/**
 * Disable WordPress emojis (performance)
 */
function prm_theme_disable_emojis() {
    remove_action('wp_head', 'print_emoji_detection_script', 7);
    remove_action('admin_print_scripts', 'print_emoji_detection_script');
    remove_action('wp_print_styles', 'print_emoji_styles');
    remove_action('admin_print_styles', 'print_emoji_styles');
}
add_action('init', 'prm_theme_disable_emojis');

/**
 * Clean up WordPress head
 */
function prm_theme_cleanup_head() {
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'wp_shortlink_wp_head');
}
add_action('init', 'prm_theme_cleanup_head');

/**
 * Load ACF JSON from theme directory
 */
function prm_acf_json_load_point($paths) {
    $paths[] = PRM_THEME_DIR . '/acf-json';
    return $paths;
}
add_filter('acf/settings/load_json', 'prm_acf_json_load_point');

/**
 * Save ACF JSON to theme directory (for development)
 */
function prm_acf_json_save_point($path) {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $path = PRM_THEME_DIR . '/acf-json';
    }
    return $path;
}
add_filter('acf/settings/save_json', 'prm_acf_json_save_point');

/**
 * Custom login page styling
 */
function prm_login_styles() {
    $favicon_url = PRM_THEME_URL . '/favicon.svg';
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
            background-image: url('<?php echo esc_url($favicon_url); ?>');
            background-size: contain;
            background-position: center center;
            background-repeat: no-repeat;
            width: 84px;
            height: 84px;
            margin-bottom: 20px;
        }
        
        /* App name below logo */
        #login h1::after {
            content: 'Caelis';
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
            margin-top: 20px;
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
add_action('login_enqueue_scripts', 'prm_login_styles');

/**
 * Change login logo URL to homepage
 */
function prm_login_logo_url() {
    return home_url('/');
}
add_filter('login_headerurl', 'prm_login_logo_url');

/**
 * Change login logo title
 */
function prm_login_logo_title() {
    return 'Caelis';
}
add_filter('login_headertext', 'prm_login_logo_title');

/**
 * Redirect users to homepage after login
 */
function prm_login_redirect($redirect_to, $request, $user) {
    // Only redirect if no specific redirect was requested
    if (isset($_GET['redirect_to']) && !empty($_GET['redirect_to'])) {
        return $redirect_to;
    }
    
    // Redirect all users to the homepage
    return home_url('/');
}
add_filter('login_redirect', 'prm_login_redirect', 10, 3);
