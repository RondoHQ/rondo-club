<?php
/**
 * CardDAV Server Handler
 * 
 * Initializes and routes requests to the Sabre/DAV CardDAV server.
 * 
 * @package Caelis
 */

if (!defined('ABSPATH')) {
    exit;
}

class PRM_CardDAV_Server {
    
    /**
     * Base URI for CardDAV server
     */
    const BASE_URI = '/carddav/';
    
    /**
     * Initialize hooks
     */
    public function __construct() {
        add_action('init', [$this, 'register_rewrite_rules']);
        add_action('template_redirect', [$this, 'handle_request'], 0);
        add_filter('query_vars', [$this, 'add_query_vars']);
    }
    
    /**
     * Register rewrite rules for CardDAV endpoint
     */
    public function register_rewrite_rules() {
        // Match /carddav and /carddav/* 
        add_rewrite_rule(
            '^carddav/?(.*)$',
            'index.php?carddav_request=$matches[1]',
            'top'
        );
    }
    
    /**
     * Add query vars
     *
     * @param array $vars Query vars
     * @return array Modified query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'carddav_request';
        return $vars;
    }
    
    /**
     * Handle CardDAV requests
     */
    public function handle_request() {
        // Check if this is a CardDAV request
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        
        if (strpos($request_uri, '/carddav') !== 0) {
            return;
        }
        
        $method = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
        error_log("CardDAV Server: {$method} request to {$request_uri}");
        
        // Check if Composer autoloader is available
        if (!class_exists('Sabre\DAV\Server')) {
            error_log("CardDAV Server: Sabre\DAV\Server not available");
            http_response_code(500);
            echo 'CardDAV server not available. Please run composer install.';
            exit;
        }
        
        // Include backend classes
        require_once PRM_PLUGIN_DIR . '/carddav/class-auth-backend.php';
        require_once PRM_PLUGIN_DIR . '/carddav/class-principal-backend.php';
        require_once PRM_PLUGIN_DIR . '/carddav/class-carddav-backend.php';
        
        try {
            // Create backends
            $authBackend = new \Caelis\CardDAV\AuthBackend();
            $principalBackend = new \Caelis\CardDAV\PrincipalBackend();
            $carddavBackend = new \Caelis\CardDAV\CardDAVBackend();
            
            // Create directory tree
            $tree = [
                new \Sabre\DAVACL\PrincipalCollection($principalBackend),
                new \Sabre\CardDAV\AddressBookRoot($principalBackend, $carddavBackend),
            ];
            
            // Create server
            $server = new \Sabre\DAV\Server($tree);
            $server->setBaseUri(self::BASE_URI);
            
            // Add plugins
            $server->addPlugin(new \Sabre\DAV\Auth\Plugin($authBackend, 'Caelis'));
            $server->addPlugin(new \Sabre\DAV\Browser\Plugin());
            $server->addPlugin(new \Sabre\CardDAV\Plugin());
            $server->addPlugin(new \Sabre\DAVACL\Plugin());
            $server->addPlugin(new \Sabre\DAV\Sync\Plugin());
            $server->addPlugin(new \Sabre\CardDAV\VCFExportPlugin());
            
            // Add event listener to log responses
            $server->on('afterMethod:*', function($request, $response) {
                $status = $response->getStatus();
                $uri = $request->getPath();
                $method = $request->getMethod();
                error_log("CardDAV Server Response: {$method} {$uri} -> HTTP {$status}");
                
                // Log response body for PROPFIND (truncated)
                if ($method === 'PROPFIND' && $status >= 200 && $status < 300) {
                    $body = $response->getBodyAsString();
                    if (strlen($body) > 500) {
                        error_log("CardDAV Response body (truncated): " . substr($body, 0, 500) . "...");
                    } else {
                        error_log("CardDAV Response body: " . $body);
                    }
                }
            });
            
            // Run the server
            $server->exec();
        } catch (\Exception $e) {
            error_log("CardDAV Server Exception: " . $e->getMessage());
            error_log("CardDAV Server Stack trace: " . $e->getTraceAsString());
            http_response_code(500);
            echo 'CardDAV server error: ' . $e->getMessage();
        }
        exit;
    }
    
    /**
     * Flush rewrite rules on activation
     */
    public static function activate() {
        $instance = new self();
        $instance->register_rewrite_rules();
        flush_rewrite_rules();
    }
    
    /**
     * Clean up on deactivation
     */
    public static function deactivate() {
        flush_rewrite_rules();
    }
    
    /**
     * Get CardDAV URL for a user
     *
     * @param int $user_id User ID
     * @return array URLs for CardDAV
     */
    public static function get_urls($user_id) {
        $user = get_user_by('ID', $user_id);
        
        if (!$user) {
            return [];
        }
        
        $base_url = home_url(self::BASE_URI);
        
        return [
            'server' => $base_url,
            'principal' => $base_url . 'principals/' . $user->user_login . '/',
            'addressbook' => $base_url . 'addressbooks/' . $user->user_login . '/contacts/',
        ];
    }
}

