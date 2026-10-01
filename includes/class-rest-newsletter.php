<?php
/** REST endpoints for newsletter preparation and Laposta draft export. */
namespace Rondo\REST;

use Rondo\Communication\Newsletter;
use Rondo\Communication\NewsletterExport;
use Rondo\Config\ClubConfig;
use Rondo\Core\UserRoles;
use Rondo\Data\CredentialEncryption;
use Rondo\Fields\Fields;
use Rondo\Integrations\LapostaClient;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NewsletterController extends Base {
	public function __construct() {
		parent::__construct();
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		foreach ( [
			[ '/newsletter', 'GET', 'metadata', 'can_access' ],
			[ '/newsletter/settings', 'GET', 'settings', 'can_admin' ],
			[ '/newsletter/settings', 'PUT', 'save_settings', 'can_admin' ],
			[ '/newsletter/profiles/(?P<user_id>\d+)', 'PUT', 'save_profile', 'can_admin' ],
			[ '/newsletter/lists', 'GET', 'lists', 'can_access' ],
			[ '/newsletter/lists/(?P<list_id>[a-zA-Z0-9]+)/segments', 'GET', 'segments', 'can_access' ],
			[ '/communications/(?P<id>\d+)/newsletter', 'GET', 'show', 'can_item' ],
			[ '/communications/(?P<id>\d+)/newsletter', 'PUT', 'save', 'can_item' ],
			[ '/communications/(?P<id>\d+)/newsletter/preview', 'POST', 'preview', 'can_item' ],
			[ '/communications/(?P<id>\d+)/newsletter/review', 'POST', 'review', 'can_item' ],
			[ '/communications/(?P<id>\d+)/newsletter/export', 'POST', 'export', 'can_item' ],
		] as [ $route, $method, $callback, $permission ] ) {
			register_rest_route(
				'rondo/v1',
				$route,
				[
					'methods'             => $method,
					'callback'            => [ $this, $callback ],
					'permission_callback' => [ $this, $permission ],
				]
				);
		}
	}

	public function can_access() {
		return $this->check_user_approved() && UserRoles::can_access_section( 'communicatie' );
	}

	public function can_admin() {
		return $this->can_access() && current_user_can( 'manage_options' );
	}

	public function can_item( $request ) {
		$id = (int) $request['id'];
		if ( ! $this->can_access() || get_post_type( $id ) !== 'rondo_comm_item' || get_post_status( $id ) !== 'publish' ) {
			return false;
		}
		$channel     = Newsletter::config()['channel_id'];
		$definitions = array_column( ClubConfig::get_communication_channels(), null, 'id' );
		$rows        = Fields::get_for_post( $id, 'channels' ) ?: [];
		$ids         = array_column( $rows, 'channel_id' );
		if ( ! $ids ) {
			$ids = [ Fields::get_for_post( $id, 'channel' ) ];
		}
		return ! empty( $definitions[ $channel ]['active'] ) && in_array( $channel, $ids, true );
	}

	public function metadata() {
		$users = array_values( array_filter( get_users( [ 'orderby' => 'display_name' ] ), static fn( $u ) => UserRoles::can_access_section( 'communicatie', $u->ID ) ) );
		return rest_ensure_response(
			[
				'configured' => CredentialEncryption::get_secret_option( LapostaClient::KEY_OPTION ) !== '' && Newsletter::config()['template'] !== '',
				'channel_id' => Newsletter::config()['channel_id'],
				'users'      => array_map(
				static fn( $u ) => [
					'id'      => $u->ID,
					'name'    => $u->display_name,
					'profile' => Newsletter::profile( $u->ID ),
					'ready'   => Newsletter::profile_ready( Newsletter::profile( $u->ID ) ),
				],
				$users
				),
			]
			);
	}

	public function settings() {
		return rest_ensure_response(
			[
				'config'   => Newsletter::config(),
				'has_key'  => CredentialEncryption::get_secret_option( LapostaClient::KEY_OPTION ) !== '',
				'channels' => ClubConfig::get_communication_channels(),
			]
			);
	}

	public function save_settings( $request ) {
		$data = $request->get_json_params();
		if ( ! is_array( $data ) || array_diff( array_keys( $data ), [ 'template', 'salutation', 'channel_id', 'api_key' ] ) ) {
			return new \WP_Error( 'newsletter_settings', 'Ongeldige instellingen.', [ 'status' => 400 ] );
		}
		$config = Newsletter::config();
		foreach ( [ 'template', 'salutation', 'channel_id' ] as $key ) {
			if ( isset( $data[ $key ] ) ) {
				if ( ! is_string( $data[ $key ] ) ) {
					return new \WP_Error( 'newsletter_settings', 'Controleer de instellingen.', [ 'status' => 400 ] );
				}
				$config[ $key ] = $key === 'template' ? trim( $data[ $key ] ) : sanitize_text_field( $data[ $key ] );
			}
		}
		if ( ! in_array( $config['channel_id'], array_column( array_filter( ClubConfig::get_communication_channels(), static fn( $c ) => $c['active'] ), 'id' ), true ) || strlen( $config['salutation'] ) > 250 ) {
			return new \WP_Error( 'newsletter_settings', 'Kies een actief kanaal en een korte aanhef.', [ 'status' => 400 ] );
		}
		$html = $config['template'];
		if ( $html !== '' ) {
			if ( strlen( $html ) > 500000 || preg_match( '/<(script|iframe|object|embed|form|base)\b|\bon[a-z]+\s*=|javascript\s*:|http-equiv\s*=\s*["\']?refresh/i', $html ) ) {
				return new \WP_Error( 'newsletter_template', 'Gebruik een HTML-mailtemplate zonder scripts, formulieren of doorverwijzingen.', [ 'status' => 400 ] );
			}
			preg_match_all( '/%%([A-Z_]+)%%/', $html, $placeholders );
			if ( array_diff( $placeholders[1], [ 'DOCUMENT_TITLE', 'HEADING', 'PREHEADER', 'SALUTATION', 'BODY_HTML', 'SIGNER_NAME', 'SIGNER_ROLE', 'SIGNATURE_URL', 'SIGNATURE_HEIGHT' ] ) ) {
				return new \WP_Error( 'newsletter_template', 'De template bevat onbekende invulvelden.', [ 'status' => 400 ] );
			}
			foreach ( [ '<head', '<body', '<unsubscribe>', '<webversion>', '%%BODY_HTML%%', '%%HEADING%%', '%%PREHEADER%%', '%%SALUTATION%%', '%%SIGNER_NAME%%', '%%SIGNER_ROLE%%' ] as $required ) {
				if ( stripos( $html, $required ) === false ) {
					return new \WP_Error( 'newsletter_template', 'De template mist een verplicht onderdeel: ' . $required, [ 'status' => 400 ] );
				}
			}
		}
		if ( isset( $data['api_key'] ) && ( ! is_string( $data['api_key'] ) || strlen( $data['api_key'] ) > 200 || preg_match( '/[\s:]/', $data['api_key'] ) ) ) {
			return new \WP_Error( 'newsletter_key', 'De API-sleutel heeft een ongeldig formaat.', [ 'status' => 400 ] );
		}
		if ( ! empty( $data['api_key'] ) ) {
			CredentialEncryption::update_secret_option( LapostaClient::KEY_OPTION, $data['api_key'] );
			delete_transient( 'rondo_newsletter_lists' );
		}
		update_option( Newsletter::CONFIG, $config, false );
		return $this->settings();
	}

	public function save_profile( $request ) {
		$id      = (int) $request['user_id'];
		$data    = $request->get_json_params();
		$profile = Newsletter::profile( $id );
		if ( ! UserRoles::can_access_section( 'communicatie', $id ) || ! is_array( $data ) || array_diff( array_keys( $data ), array_keys( $profile ) ) ) {
			return new \WP_Error( 'newsletter_profile', 'Kies een gebruiker met communicatierecht en controleer de profielvelden.', [ 'status' => 400 ] );
		}
		foreach ( $data as $key => $value ) {
			if ( $key === 'active' ) {
				if ( ! is_bool( $value ) ) {
					return new \WP_Error( 'newsletter_profile', 'Actief moet ja of nee zijn.', [ 'status' => 400 ] );
				}
			} elseif ( $key === 'signature_height' ) {
				if ( ! is_int( $value ) || $value < 1 || $value > 600 ) {
					return new \WP_Error( 'newsletter_profile', 'De afbeeldingshoogte moet tussen 1 en 600 pixels liggen.', [ 'status' => 400 ] );
				}
			} elseif ( ! is_string( $value ) || strlen( $value ) > ( $key === 'signature_url' ? 2000 : 250 ) ) {
				return new \WP_Error( 'newsletter_profile', 'Controleer de profielvelden.', [ 'status' => 400 ] );
			} else {
				$value = sanitize_text_field( $value );
				if ( in_array( $key, [ 'from_email', 'reply_to' ], true ) && $value !== '' && ! is_email( $value ) ) {
					return new \WP_Error( 'newsletter_profile', 'Vul geldige e-mailadressen in.', [ 'status' => 400 ] );
				}
				if ( $key === 'signature_url' && $value !== '' && ( ! wp_http_validate_url( $value ) || wp_parse_url( $value, PHP_URL_SCHEME ) !== 'https' ) ) {
					return new \WP_Error( 'newsletter_profile', 'Gebruik een publiek bereikbare HTTPS-afbeelding.', [ 'status' => 400 ] );
				}
			}
			$profile[ $key ] = $value;
		}
		if ( $profile['active'] && ! Newsletter::profile_ready( $profile ) ) {
			return new \WP_Error( 'newsletter_profile', 'Vul naam, functie, afzendernaam en beide e-mailadressen in voordat je het profiel activeert.', [ 'status' => 400 ] );
		}
		update_user_meta( $id, Newsletter::PROFILE, wp_slash( $profile ) );
		return rest_ensure_response( $profile );
	}

	public function lists() {
		$lists = get_transient( 'rondo_newsletter_lists' );
		if ( $lists === false ) {
			$result = ( new LapostaClient() )->request( 'GET', '/list' );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$lists = array_values(
				array_map(
				static fn( $l ) => [
					'id'   => $l['list_id'],
					'name' => $l['name'],
				],
				array_filter( array_column( $result['data'] ?? [], 'list' ), static fn( $l ) => ( $l['state'] ?? '' ) === 'active' )
				)
				);
			set_transient( 'rondo_newsletter_lists', $lists, 60 );
		}
		return rest_ensure_response( $lists );
	}

	public function segments( $request ) {
		$result = ( new LapostaClient() )->request( 'GET', '/segment', [ 'list_id' => $request['list_id'] ] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response(
			array_values(
			array_map(
			static fn( $s ) => [
				'id'   => $s['segment_id'],
				'name' => $s['name'],
			],
			array_filter( array_column( $result['data'] ?? [], 'segment' ), static fn( $s ) => ( $s['list_id'] ?? '' ) === $request['list_id'] && ( $s['state'] ?? '' ) !== 'deleted' )
			)
			)
			);
	}

	public function show( $request ) {
		$id       = (int) $request['id'];
		$export   = get_post_meta( $id, Newsletter::EXPORT, true );
		$export   = is_array( $export ) ? $export : [];
		$status   = empty( $export ) ? 'local' : ( ( $export['phase'] ?? '' ) === 'verified' ? ( ( $export['fingerprint'] ?? '' ) === Newsletter::fingerprint( $id ) ? 'exported' : 'changed' ) : 'attention' );
		$campaign = $export['campaign_id'] ?? '';
		return rest_ensure_response(
			[
				'id'           => $id,
				'title'        => get_the_title( $id ),
				'fields'       => Newsletter::draft( $id ),
				'revision'     => Newsletter::revision( $id ),
				'status'       => $status,
				'campaign_id'  => $campaign,
				'campaign_url' => $campaign ? 'https://app.laposta.nl/c.campaign/s.browse/edit/confirm/?campaign=' . rawurlencode( $campaign ) : '',
				'verified_at'  => $export['verified_at'] ?? null,
			]
			);
	}

	private function locked( $request, callable $callback ) {
		$key = 'rondo_comm_edit_' . (int) $request['id'];
		if ( ! add_option( $key, time(), '', false ) ) {
			return new \WP_Error( 'newsletter_busy', 'Dit communicatie-item wordt al bijgewerkt. Probeer het opnieuw.', [ 'status' => 409 ] );
		}
		try {
			if ( ! $this->can_item( $request ) ) {
				return new \WP_Error( 'newsletter_item_changed', 'Het nieuwsbriefkanaal is niet meer beschikbaar voor dit item.', [ 'status' => 409 ] );
			}
			return $callback();
		} finally {
			delete_option( $key );
		}
	}

	public function save( $request ) {
		return $this->locked(
			$request,
			function () use ( $request ) {
				$id = (int) $request['id'];
				if ( $request->get_param( 'revision' ) !== Newsletter::revision( $id ) ) {
					return new \WP_Error( 'newsletter_stale', 'Dit concept is door iemand anders gewijzigd. Herlaad de pagina voordat je verdergaat.', [ 'status' => 409 ] );
				}
				$data = $request->get_param( 'fields' );
				if ( ! is_array( $data ) ) {
					return new \WP_Error( 'newsletter_fields', 'Verstuur de gewijzigde velden onder fields.', [ 'status' => 400 ] );
				}
				$draft = Newsletter::sanitize_draft( array_merge( Newsletter::draft( $id ), $data ) );
				if ( is_wp_error( $draft ) ) {
					return $draft;
				}
				if ( ! empty( $draft['assignee_id'] ) || Fields::get_for_post( $id, 'status' ) === 'concept' ) {
					$result = Fields::update_many_for_post( $id, wp_slash( $draft ) );
				} else {
					return new \WP_Error( 'newsletter_assignee', 'Een item in voorbereiding moet een verantwoordelijke houden.', [ 'status' => 400 ] );
				}
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				wp_update_post( [ 'ID' => $id ] );
				Newsletter::audit( $id, 'newsletter_saved' );
				return $this->show( $request );
			}
			);
	}

	public function preview( $request ) {
		$data  = $request->get_param( 'fields' );
		$draft = Newsletter::sanitize_draft( array_merge( Newsletter::draft( (int) $request['id'] ), is_array( $data ) ? $data : [] ) );
		if ( is_wp_error( $draft ) ) {
			return $draft;
		}
		$html = Newsletter::render( $draft );
		return is_wp_error( $html ) ? $html : rest_ensure_response( [ 'html' => Newsletter::preview_html( $html ) ] );
	}

	public function review( $request ) {
		return $this->locked(
			$request,
			function () use ( $request ) {
				$id    = (int) $request['id'];
				$draft = Newsletter::draft( $id );
				if ( $request->get_param( 'revision' ) !== Newsletter::revision( $id ) ) {
					return new \WP_Error( 'newsletter_stale', 'Sla het huidige concept eerst op.', [ 'status' => 409 ] );
				}
				foreach ( [ 'newsletter_subject', 'newsletter_heading', 'newsletter_body' ] as $key ) {
					if ( trim( wp_strip_all_tags( $draft[ $key ] ) ) === '' ) {
						return new \WP_Error( 'newsletter_required', 'Vul onderwerp, titel en bericht in.', [ 'status' => 400 ] );
					}
				}
				$fingerprint = Newsletter::fingerprint( $id );
				$html        = Newsletter::render( $draft );
				if ( is_wp_error( $html ) ) {
					return $html;
				}
				$audiences = ( new NewsletterExport() )->audiences( $draft['newsletter_audiences'] );
				if ( is_wp_error( $audiences ) ) {
					return $audiences;
				}
				$token = wp_generate_password( 48, false, false );
				set_transient(
				'rondo_newsletter_review_' . hash( 'sha256', $token ),
				[
					'id'          => $id,
					'user'        => get_current_user_id(),
					'fingerprint' => $fingerprint,
					'audiences'   => $audiences,
				],
				10 * MINUTE_IN_SECONDS
				);
				return rest_ensure_response(
				[
					'token'      => $token,
					'html'       => Newsletter::preview_html( $html ),
					'audiences'  => array_map( static fn( $a ) => array_diff_key( $a, [ 'definition' => true ] ), $audiences ),
					'profile'    => Newsletter::profile( $draft['assignee_id'] ),
					'checked_at' => gmdate( 'c' ),
				]
				);
			}
			);
	}

	public function export( $request ) {
		return $this->locked(
			$request,
			function () use ( $request ) {
				$id     = (int) $request['id'];
				$token  = $request->get_param( 'token' );
				$review = is_string( $token ) ? get_transient( 'rondo_newsletter_review_' . hash( 'sha256', $token ) ) : false;
				if ( ! $review || $review['id'] !== $id || $review['user'] !== get_current_user_id() || $review['fingerprint'] !== Newsletter::fingerprint( $id ) ) {
					return new \WP_Error( 'newsletter_review_expired', 'De controle is verlopen of het concept is gewijzigd. Controleer opnieuw.', [ 'status' => 409 ] );
				}
				$service   = new NewsletterExport();
				$audiences = $service->audiences( Newsletter::draft( $id )['newsletter_audiences'] );
				if ( is_wp_error( $audiences ) ) {
					return $audiences;
				}
				if ( $audiences !== $review['audiences'] ) {
					return new \WP_Error( 'newsletter_audience_changed', 'De lijst of segmentdefinitie is gewijzigd. Controleer de doelgroep opnieuw.', [ 'status' => 409 ] );
				}
				$result = $service->export( $id, $audiences, $review['fingerprint'] );
				return is_wp_error( $result ) ? $result : $this->show( $request );
			}
			);
	}
}
