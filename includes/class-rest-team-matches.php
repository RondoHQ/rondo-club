<?php
/** Team match overview and calendar subscription endpoints. */

namespace Rondo\REST;

use Rondo\Teams\TeamMatches as MatchService;

class TeamMatches extends Base {

	/** Register REST and raw calendar rendering hooks. */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_filter( 'rest_pre_serve_request', [ $this, 'serve_calendar' ], 10, 4 );
	}

	/** Register the authenticated overview and team-scoped subscription. */
	public function register_routes(): void {
		register_rest_route(
			'rondo/v1',
			'/teams/(?P<id>\d+)/matches',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_matches' ],
				'permission_callback' => [ $this, 'check_team_access' ],
			]
			);
		register_rest_route(
			'rondo/v1',
			'/teams/(?P<id>\d+)/matches.ics',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_calendar' ],
				'permission_callback' => [ $this, 'check_calendar_access' ],
				'args'                => [
					'token' => [
						'type'     => 'string',
						'required' => true,
					],
				],
			]
			);
	}

	/** Require an accessible, published team. */
	public function check_team_access( \WP_REST_Request $request ): bool {
		$post = get_post( (int) $request['id'] );
		return $post && $post->post_type === 'team' && $post->post_status === 'publish' && ( new \Rondo\Core\AccessControl() )->user_can_access_post( $post->ID );
	}

	/** Only the signed link grants anonymous access, and only to public fixture data. */
	public function check_calendar_access( \WP_REST_Request $request ): bool {
		$post = get_post( (int) $request['id'] );
		return $post && $post->post_type === 'team' && $post->post_status === 'publish' && hash_equals( MatchService::token( $post->ID ), (string) $request['token'] );
	}

	/** Return the season overview with its stable calendar link. */
	public function get_matches( \WP_REST_Request $request ) {
		$id   = (int) $request['id'];
		$feed = ( new MatchService() )->get_feed( $id );
		if ( is_wp_error( $feed ) ) {
			return $feed;
		}
		$feed['calendar_url'] = add_query_arg( 'token', MatchService::token( $id ), rest_url( 'rondo/v1/teams/' . $id . '/matches.ics' ) );
		return rest_ensure_response( $feed );
	}

	/** Build the calendar; upstream failures remain non-successful HTTP responses. */
	public function get_calendar( \WP_REST_Request $request ) {
		$id   = (int) $request['id'];
		$feed = ( new MatchService() )->get_feed( $id );
		if ( is_wp_error( $feed ) ) {
			return $feed;
		}
		return new \WP_REST_Response( MatchService::calendar( $id, $feed ), 200 );
	}

	/** Serve RFC 5545 text instead of a JSON string. */
	public function serve_calendar( $served, $result, $request, $server ): bool {
		if ( ! preg_match( '#^/rondo/v1/teams/\d+/matches\.ics$#', $request->get_route() ) || $result->get_status() !== 200 || ! is_string( $result->get_data() ) ) {
			return $served;
		}
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: inline; filename="team-' . (int) $request['id'] . '-wedstrijden.ics"' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: private, no-cache, max-age=0' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- RFC 5545 values escaped and folded by MatchService::calendar().
		echo $result->get_data();
		return true;
	}
}
