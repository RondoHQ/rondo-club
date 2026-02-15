<?php
/**
 * CardDAV Server Handler
 *
 * Initializes and routes requests to the Sabre/DAV CardDAV server.
 *
 * @package Rondo
 */

namespace Rondo\CardDAV;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Server {

	/**
	 * Base URI for CardDAV server
	 */
	const BASE_URI = '/carddav/';

	/**
	 * Initialize hooks
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_rewrite_rules' ] );
		// Handle .well-known early (before WordPress routing kicks in)
		add_action( 'init', [ $this, 'handle_well_known' ], 1 );
		add_action( 'template_redirect', [ $this, 'handle_request' ], 0 );
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

		// Match .well-known/carddav for auto-discovery
		add_rewrite_rule(
			'^\.well-known/carddav/?$',
			'index.php?well_known_carddav=1',
			'top'
		);
	}

	/**
	 * Handle .well-known/carddav redirect for auto-discovery
	 */
	public function handle_well_known() {
		if ( $this->is_well_known_request() ) {
			// Return 301 redirect to the CardDAV root
			header( 'Location: ' . home_url( self::BASE_URI ), true, 301 );
			exit;
		}
	}

	/**
	 * Handle CardDAV requests
	 */
	public function handle_request() {
		if ( ! $this->is_carddav_request() ) {
			return;
		}

		if ( ! class_exists( 'Sabre\DAV\Server' ) ) {
			$this->send_error( 500, 'CardDAV server not available. Please run composer install.' );
		}

		$this->load_backends();

		try {
			$server = $this->initialize_server();
			$server->exec();
		} catch ( \Exception $e ) {
			error_log( 'CardDAV Server Exception: ' . $e->getMessage() );
			error_log( 'CardDAV Server Stack trace: ' . $e->getTraceAsString() );
			$this->send_error( 500, 'CardDAV server error: ' . $e->getMessage() );
		}

		exit;
	}

	/**
	 * Check if current request is for .well-known/carddav
	 *
	 * @return bool
	 */
	private function is_well_known_request() {
		$request_uri = $_SERVER['REQUEST_URI'] ?? false;
		return $request_uri && strpos( $request_uri, '/.well-known/carddav' ) === 0;
	}

	/**
	 * Check if current request is for CardDAV endpoint
	 *
	 * @return bool
	 */
	private function is_carddav_request() {
		$request_uri = $_SERVER['REQUEST_URI'] ?? false;
		return $request_uri && strpos( $request_uri, '/carddav' ) === 0;
	}

	/**
	 * Send HTTP error response and exit
	 *
	 * @param int    $code    HTTP status code
	 * @param string $message Error message
	 */
	private function send_error( $code, $message ) {
		http_response_code( $code );
		echo $message;
		exit;
	}

	/**
	 * Load CardDAV backend classes
	 */
	private function load_backends() {
		require_once \RONDO_PLUGIN_DIR . '/carddav/class-auth-backend.php';
		require_once \RONDO_PLUGIN_DIR . '/carddav/class-principal-backend.php';
		require_once \RONDO_PLUGIN_DIR . '/carddav/class-carddav-backend.php';
	}

	/**
	 * Initialize and configure Sabre DAV server
	 *
	 * @return \Sabre\DAV\Server
	 */
	private function initialize_server() {
		// Create backends
		$authBackend      = new \Rondo\CardDAV\AuthBackend();
		$principalBackend = new \Rondo\CardDAV\PrincipalBackend();
		$carddavBackend   = new \Rondo\CardDAV\CardDAVBackend();

		// Create directory tree
		$tree = [
			new \Sabre\DAVACL\PrincipalCollection( $principalBackend ),
			new \Sabre\CardDAV\AddressBookRoot( $principalBackend, $carddavBackend ),
		];

		// Create server
		$server = new \Sabre\DAV\Server( $tree );
		$server->setBaseUri( self::BASE_URI );

		// Add plugins
		$server->addPlugin( new \Sabre\DAV\Auth\Plugin( $authBackend, 'Rondo' ) );
		$server->addPlugin( new \Sabre\DAV\Browser\Plugin() );
		$server->addPlugin( new \Sabre\CardDAV\Plugin() );
		$server->addPlugin( new \Sabre\DAVACL\Plugin() );
		$server->addPlugin( new \Sabre\DAV\Sync\Plugin() );
		$server->addPlugin( new \Sabre\CardDAV\VCFExportPlugin() );

		return $server;
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
	public static function get_urls( $user_id ) {
		$user = get_user_by( 'ID', $user_id );

		if ( ! $user ) {
			return [];
		}

		$base_url = home_url( self::BASE_URI );

		return [
			'server'      => $base_url,
			'principal'   => $base_url . 'principals/' . $user->user_login . '/',
			'addressbook' => $base_url . 'addressbooks/' . $user->user_login . '/contacts/',
		];
	}
}
