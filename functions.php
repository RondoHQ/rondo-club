<?php
/**
 * Personal CRM Theme Functions
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PRM_THEME_VERSION', '1.0.0');
define('PRM_THEME_DIR', get_template_directory());
define('PRM_THEME_URL', get_template_directory_uri());

/**
 * Check for required plugin
 */
function prm_theme_check_requirements() {
    if (!is_plugin_active('personal-crm/personal-crm.php') && !defined('PRM_VERSION')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('Personal CRM Theme requires the Personal CRM plugin to be installed and activated.', 'personal-crm-theme');
            echo '</p></div>';
        });
    }
}
add_action('admin_init', 'prm_theme_check_requirements');

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
 * Enqueue scripts and styles
 */
function prm_theme_enqueue_assets() {
    $dist_dir = PRM_THEME_DIR . '/dist';
    $dist_url = PRM_THEME_URL . '/dist';
    
    // Check if we have built assets
    if (file_exists($dist_dir . '/manifest.json')) {
        $manifest = json_decode(file_get_contents($dist_dir . '/manifest.json'), true);
        
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
            }
        }
    } else {
        // Development mode - load from Vite dev server
        if (defined('WP_DEBUG') && WP_DEBUG) {
            add_action('wp_head', function() {
                echo '<script type="module" src="http://localhost:5173/@vite/client"></script>';
                echo '<script type="module" src="http://localhost:5173/src/main.jsx"></script>';
            });
        }
    }
    
    // Localize script with WordPress data
    wp_localize_script('prm-theme-script', 'prmConfig', prm_get_js_config());
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
 * Remove admin bar for non-admins (optional)
 */
function prm_theme_remove_admin_bar() {
    if (!current_user_can('manage_options')) {
        // Uncomment to hide admin bar for non-admins
        // show_admin_bar(false);
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
}
add_action('template_redirect', 'prm_theme_template_redirect');

/**
 * Handle client-side routing - return index.php for all routes
 */
function prm_theme_rewrite_rules() {
    add_rewrite_rule('^app/?', 'index.php', 'top');
    add_rewrite_rule('^app/(.+)/?', 'index.php', 'top');
}
add_action('init', 'prm_theme_rewrite_rules');

/**
 * Flush rewrite rules on theme activation
 */
function prm_theme_activation() {
    prm_theme_rewrite_rules();
    flush_rewrite_rules();
}
add_action('after_switch_theme', 'prm_theme_activation');

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
