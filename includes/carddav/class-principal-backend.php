<?php
/**
 * CardDAV Principal Backend
 *
 * Maps WordPress users to DAV principals.
 *
 * @package Caelis
 */

namespace Caelis\CardDAV;

use Sabre\DAVACL\PrincipalBackend\AbstractBackend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PrincipalBackend extends AbstractBackend {

	/**
	 * Get list of principals for a prefix
	 *
	 * @param string $prefixPath Principal prefix (e.g., 'principals')
	 * @return array Array of principal URIs
	 */
	public function getPrincipalsByPrefix( $prefixPath ) {
		$principals = array();

		if ( $prefixPath !== 'principals' ) {
			return $principals;
		}

		// Get all users who can use the CRM
		$users = get_users(
			array(
				'role__in' => array( 'administrator', 'caelis_user' ),
			)
		);

		foreach ( $users as $user ) {
			$principals[] = $this->getUserPrincipal( $user );
		}

		return $principals;
	}

	/**
	 * Get principal data by path
	 *
	 * @param string $path Principal path (e.g., 'principals/username')
	 * @return array|null Principal data or null if not found
	 */
	public function getPrincipalByPath( $path ) {
		$parts = explode( '/', $path );

		if ( count( $parts ) !== 2 || $parts[0] !== 'principals' ) {
			return null;
		}

		$username = $parts[1];
		$user     = get_user_by( 'login', $username );

		if ( ! $user ) {
			return null;
		}

		// Verify user has appropriate role
		if ( ! user_can( $user, 'manage_options' ) && ! in_array( 'caelis_user', $user->roles ) ) {
			return null;
		}

		return $this->getUserPrincipal( $user );
	}

	/**
	 * Update principal data
	 *
	 * @param string $path Principal path
	 * @param \Sabre\DAV\PropPatch $propPatch Property patch object
	 * @return void
	 */
	public function updatePrincipal( $path, \Sabre\DAV\PropPatch $propPatch ) {
		// We don't support updating principals through CardDAV
	}

	/**
	 * Search principals
	 *
	 * @param string $prefixPath Principal prefix
	 * @param array $searchProperties Properties to search
	 * @param string $test 'allof' or 'anyof'
	 * @return array Matching principals
	 */
	public function searchPrincipals( $prefixPath, array $searchProperties, $test = 'allof' ) {
		$results = array();

		if ( $prefixPath !== 'principals' ) {
			return $results;
		}

		// Get search criteria
		$email       = $searchProperties['{http://sabredav.org/ns}email-address'] ?? null;
		$displayName = $searchProperties['{DAV:}displayname'] ?? null;

		$args = array(
			'role__in' => array( 'administrator', 'caelis_user' ),
		);

		if ( $email ) {
			$args['search']         = $email;
			$args['search_columns'] = array( 'user_email' );
		}

		$users = get_users( $args );

		foreach ( $users as $user ) {
			// Filter by display name if specified
			if ( $displayName && stripos( $user->display_name, $displayName ) === false ) {
				continue;
			}

			$results[] = 'principals/' . $user->user_login;
		}

		return $results;
	}

	/**
	 * Find principal by URI
	 *
	 * @param string $uri URI to find (e.g., 'mailto:email@example.com')
	 * @param string $principalPrefix Principal prefix
	 * @return string|null Principal path or null
	 */
	public function findByUri( $uri, $principalPrefix ) {
		if ( strpos( $uri, 'mailto:' ) === 0 ) {
			$email = substr( $uri, 7 );
			$user  = get_user_by( 'email', $email );

			if ( $user && ( user_can( $user, 'manage_options' ) || in_array( 'caelis_user', $user->roles ) ) ) {
				return $principalPrefix . '/' . $user->user_login;
			}
		}

		return null;
	}

	/**
	 * Get group member set
	 *
	 * @param string $principal Principal path
	 * @return array Group members
	 */
	public function getGroupMemberSet( $principal ) {
		return array();
	}

	/**
	 * Get group membership
	 *
	 * @param string $principal Principal path
	 * @return array Groups this principal is a member of
	 */
	public function getGroupMembership( $principal ) {
		return array();
	}

	/**
	 * Set group member set
	 *
	 * @param string $principal Principal path
	 * @param array $members New member list
	 * @return void
	 */
	public function setGroupMemberSet( $principal, array $members ) {
		// Groups not supported
	}

	/**
	 * Convert WP_User to principal array
	 *
	 * @param \WP_User $user WordPress user
	 * @return array Principal data
	 */
	private function getUserPrincipal( \WP_User $user ) {
		return array(
			'uri'                                   => 'principals/' . $user->user_login,
			'{DAV:}displayname'                     => $user->display_name,
			'{http://sabredav.org/ns}email-address' => $user->user_email,
		);
	}
}
