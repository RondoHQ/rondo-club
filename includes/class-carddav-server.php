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
		add_filter( 'query_vars', [ $this, 'add_query_vars' ] );
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
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';

		if ( strpos( $request_uri, '/.well-known/carddav' ) === 0 ) {
			// Return 301 redirect to the CardDAV root
			header( 'Location: ' . home_url( self::BASE_URI ), true, 301 );
			exit;
		}
	}

	/**
	 * Add query vars
	 *
	 * @param array $vars Query vars
	 * @return array Modified query vars
	 */
	public function add_query_vars( $vars ) {
		$vars[] = 'carddav_request';
		$vars[] = 'well_known_carddav';
		return $vars;
	}

	/**
	 * Handle CardDAV requests
	 */
	public function handle_request() {
		// Check if this is a CardDAV request
		$request_uri = $_SERVER['REQUEST_URI'] ?? '';

		if ( strpos( $request_uri, '/carddav' ) !== 0 ) {
			return;
		}

		// Check if Composer autoloader is available
		if ( ! class_exists( 'Sabre\DAV\Server' ) ) {
			http_response_code( 500 );
			echo 'CardDAV server not available. Please run composer install.';
			exit;
		}

		// Include backend classes
		require_once \RONDO_PLUGIN_DIR . '/carddav/class-auth-backend.php';
		require_once \RONDO_PLUGIN_DIR . '/carddav/class-principal-backend.php';
		require_once \RONDO_PLUGIN_DIR . '/carddav/class-carddav-backend.php';

		try {
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

			// Run the server
			$server->exec();
		} catch ( \Exception $e ) {
			error_log( 'CardDAV Server Exception: ' . $e->getMessage() );
			error_log( 'CardDAV Server Stack trace: ' . $e->getTraceAsString() );
			http_response_code( 500 );
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
