<?php
/** Recoverable draft export. Never sends or schedules campaigns. */
namespace Rondo\Communication;

use Rondo\Integrations\LapostaClient;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NewsletterExport {
	private LapostaClient $client;

	public function __construct() {
		$this->client = new LapostaClient();
	}

	public function audiences( array $rows ) {
		if ( ! $rows ) {
			return new \WP_Error( 'newsletter_audience_required', 'Selecteer minstens één doelgroep.', [ 'status' => 400 ] );
		}
		$result = [];
		foreach ( $rows as $row ) {
			if ( empty( $row['list_id'] ) || ! in_array( $row['scope'], [ 'all', 'segment' ], true ) || ( $row['scope'] === 'segment' && empty( $row['segment_id'] ) ) ) {
				return new \WP_Error( 'newsletter_audience_required', 'Kies per lijst expliciet een segment of de hele lijst.', [ 'status' => 400 ] );
			}
			$list = $this->client->request( 'GET', '/list/' . $row['list_id'] );
			if ( is_wp_error( $list ) ) {
				return $list;
			}
			if ( ( $list['list']['state'] ?? '' ) !== 'active' || ( $list['list']['list_id'] ?? '' ) !== $row['list_id'] ) {
				return new \WP_Error( 'newsletter_list_gone', 'Een gekozen lijst bestaat niet meer. Kies de doelgroep opnieuw.', [ 'status' => 409 ] );
			}
			$entry = [
				'list_id'      => $row['list_id'],
				'list_name'    => $list['list']['name'],
				'scope'        => $row['scope'],
				'segment_id'   => $row['segment_id'],
				'segment_name' => '',
				'definition'   => null,
			];
			if ( $row['scope'] === 'segment' ) {
				$segment = $this->client->request( 'GET', '/segment/' . $row['segment_id'], [ 'list_id' => $row['list_id'] ] );
				if ( is_wp_error( $segment ) ) {
					return $segment;
				}
				$s = $segment['segment'] ?? [];
				if ( ( $s['list_id'] ?? '' ) !== $row['list_id'] || ( $s['segment_id'] ?? '' ) !== $row['segment_id'] || ( $s['state'] ?? 'active' ) === 'deleted' || ! isset( $s['definition'] ) ) {
					return new \WP_Error( 'newsletter_segment_gone', 'Een segment is niet meer geldig voor deze lijst. Kies de doelgroep opnieuw.', [ 'status' => 409 ] );
				}
				$entry['segment_name'] = $s['name'];
				$entry['definition']   = $s['definition'];
			}
			$result[] = $entry;
		}
		return $result;
	}

	private static function settings( array $campaign ): array {
		$lists = $campaign['list_ids'] ?? [];
		foreach ( $lists as &$segments ) {
			$segments = array_values( (array) $segments );
			sort( $segments );
		}
		unset( $segments );
		ksort( $lists );
		return [
			'name'     => $campaign['name'] ?? '',
			'subject'  => $campaign['subject'] ?? '',
			'from'     => [
				'name'  => $campaign['from']['name'] ?? '',
				'email' => $campaign['from']['email'] ?? '',
			],
			'reply_to' => $campaign['reply_to'] ?? '',
			'list_ids' => $lists,
		];
	}

	private static function editable( array $campaign ): bool {
		return array_key_exists( 'delivery_requested', $campaign ) && array_key_exists( 'delivery_started', $campaign ) && array_key_exists( 'delivery_ended', $campaign )
			&& empty( $campaign['delivery_requested'] ) && empty( $campaign['delivery_started'] ) && empty( $campaign['delivery_ended'] ) && ( $campaign['state'] ?? '' ) !== 'deleted'
			&& ! empty( $campaign['campaign_id'] );
	}

	private function remote( string $campaign_id ) {
		$campaign = $this->client->request( 'GET', '/campaign/' . $campaign_id );
		if ( is_wp_error( $campaign ) ) {
			return $campaign;
		}
		if ( ! self::editable( $campaign['campaign'] ?? [] ) ) {
			return new \WP_Error( 'newsletter_remote_locked', 'Deze campagne is ingepland, verzonden of niet meer beschikbaar. Rondo overschrijft die niet.', [ 'status' => 409 ] );
		}
		$content = $this->client->request( 'GET', '/campaign/' . $campaign_id . '/content' );
		if ( is_wp_error( $content ) ) {
			return $content;
		}
		if ( ! isset( $content['campaign']['html'] ) || ! is_string( $content['campaign']['html'] ) ) {
			return new \WP_Error( 'newsletter_content_unknown', 'De inhoud in Laposta kon niet worden gecontroleerd.', [ 'status' => 409 ] );
		}
		return [
			'settings' => self::settings( $campaign['campaign'] ),
			'html'     => rtrim( $content['campaign']['html'] ),
		];
	}

	private static function conflict() {
		return new \WP_Error( 'newsletter_remote_conflict', 'De campagne is in Laposta gewijzigd of de vorige export is onzeker. Controleer het concept in Laposta; er is niets overschreven.', [ 'status' => 409 ] );
	}

	/** Caller holds the shared communication-item lock for the complete transaction. */
	public function export( int $id, array $audiences, string $expected_fingerprint ) {
		if ( Newsletter::fingerprint( $id ) !== $expected_fingerprint ) {
			return new \WP_Error( 'newsletter_review_expired', 'Het profiel of de template is gewijzigd. Controleer opnieuw.', [ 'status' => 409 ] );
		}
		$draft = Newsletter::draft( $id );
		$html  = Newsletter::render( $draft );
		if ( is_wp_error( $html ) ) {
			return $html;
		}
		$fingerprint = Newsletter::fingerprint( $id );
		$profile     = Newsletter::profile( $draft['assignee_id'] );
		$state       = get_post_meta( $id, Newsletter::EXPORT, true );
		$state       = is_array( $state ) ? $state : [];
		$name        = $state['reference'] ?? ( 'Rondo ' . $id . ' [' . wp_generate_uuid4() . ']' );
		$map         = [];
		$wire_lists  = [];
		foreach ( $draft['newsletter_audiences'] as $row ) {
			$map[ $row['list_id'] ] = $row['scope'] === 'all' ? [] : [ $row['segment_id'] ];
			// Empty arrays disappear in form encoding: whole lists use Laposta's numeric form.
			if ( $row['scope'] === 'all' ) {
				$wire_lists[] = $row['list_id'];
			} else {
				$wire_lists[ $row['list_id'] ] = [ $row['segment_id'] ];
			}
		}
		$target       = self::settings(
			[
				'name'     => $name,
				'subject'  => $draft['newsletter_subject'],
				'from'     => [
					'name'  => $profile['from_name'],
					'email' => $profile['from_email'],
				],
				'reply_to' => $profile['reply_to'],
				'list_ids' => $map,
			]
			);
		$wire         = array_merge( $target, [ 'list_ids' => $wire_lists ] );
		$just_created = false;
		if ( empty( $state['campaign_id'] ) && ! empty( $state['attempted'] ) ) {
			$all = $this->client->request( 'GET', '/campaign' );
			if ( is_wp_error( $all ) ) {
				return $all;
			}
			$matches = array_values( array_filter( array_column( $all['data'] ?? [], 'campaign' ), static fn( $c ) => ( $c['name'] ?? '' ) === $name ) );
			if ( count( $matches ) !== 1 || ! self::editable( $matches[0] ) || self::settings( $matches[0] ) !== ( $state['target'] ?? null ) ) {
				return self::conflict();
			}
			$state['campaign_id'] = $matches[0]['campaign_id'];
			update_post_meta( $id, Newsletter::EXPORT, wp_slash( $state ) );
		}
		if ( empty( $state['campaign_id'] ) ) {
			$state = [
				'reference'   => $name,
				'attempted'   => true,
				'target'      => $target,
				'target_html' => $html,
				'baseline'    => [
					'settings' => $target,
					'html'     => '',
				],
				'phase'       => 'creating',
			];
			update_post_meta( $id, Newsletter::EXPORT, wp_slash( $state ) );
			$result = $this->client->request( 'POST', '/campaign', array_merge( [ 'type' => 'regular' ], $wire ) );
			if ( is_wp_error( $result ) ) {
				$code = $result->get_error_data()['http_status'] ?? 0;
				if ( in_array( $code, [ 400, 401, 403, 429 ], true ) || $result->get_error_code() === 'laposta_rate_limit' || $result->get_error_code() === 'laposta_unconfigured' ) {
					$state['attempted'] = false;
					update_post_meta( $id, Newsletter::EXPORT, wp_slash( $state ) );
				}
				return $result;
			}
			$campaign = $result['campaign'] ?? [];
			if ( empty( $campaign['campaign_id'] ) || ! preg_match( '/^[a-zA-Z0-9]+$/D', $campaign['campaign_id'] ) ) {
				return self::conflict();
			}
			$state['campaign_id'] = $campaign['campaign_id'];
			$state['phase']       = 'created';
			update_post_meta( $id, Newsletter::EXPORT, wp_slash( $state ) );
			if ( ! self::editable( $campaign ) || self::settings( $campaign ) !== $target ) {
				return self::conflict();
			}
			$just_created = true;
		}
		$campaign_id = $state['campaign_id'];
		if ( ! $just_created ) {
			$remote = $this->remote( $campaign_id );
			if ( is_wp_error( $remote ) ) {
				return $remote;
			}
			$baseline = $state['baseline'] ?? [];
			// Accept only our last verified state or the exact interrupted transaction target.
			if ( ! in_array( $remote['settings'], [ $baseline['settings'] ?? null, $state['target'] ?? null ], true ) || ! in_array( $remote['html'], [ $baseline['html'] ?? null, $state['target_html'] ?? null ], true ) ) {
				return self::conflict();
			}
			if ( ( $state['fingerprint'] ?? '' ) === $fingerprint && $remote === ( $state['baseline'] ?? null ) && ( $state['phase'] ?? '' ) === 'verified' ) {
				return true;
			}
			$state['baseline'] = $remote;
		}
		$state['target']      = $target;
		$state['target_html'] = $html;
		$state['phase']       = 'updating';
		update_post_meta( $id, Newsletter::EXPORT, wp_slash( $state ) );
		if ( ! $just_created ) {
			$result = $this->client->request( 'POST', '/campaign/' . $campaign_id, $wire );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			if ( ! self::editable( $result['campaign'] ?? [] ) || self::settings( $result['campaign'] ?? [] ) !== $target ) {
				return self::conflict();
			}
		}
		$result = $this->client->request(
			'POST',
			'/campaign/' . $campaign_id . '/content',
			[
				'html'       => $html,
				'inline_css' => 'true',
			]
			);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( ! empty( $result['campaign']['report'] ) || ! empty( $result['report'] ) ) {
			return new \WP_Error( 'newsletter_import_warning', 'Laposta meldt een probleem met de geïmporteerde inhoud of afbeeldingen. Controleer het concept en de template vóór verder gebruik.', [ 'status' => 409 ] );
		}
		$remote = $this->remote( $campaign_id );
		if ( is_wp_error( $remote ) ) {
			return $remote;
		}
		if ( $remote['settings'] !== $target || $remote['html'] !== $html ) {
			return new \WP_Error( 'newsletter_verify_failed', 'De inhoud of doelgroep in Laposta wijkt af. Controleer het concept; de export is niet als geslaagd gemarkeerd.', [ 'status' => 409 ] );
		}
		$state = array_merge(
			$state,
			[
				'phase'         => 'verified',
				'baseline'      => $remote,
				'fingerprint'   => $fingerprint,
				'profile'       => $profile,
				'template_hash' => hash( 'sha256', Newsletter::config()['template'] ),
				'audiences'     => $audiences,
				'verified_at'   => gmdate( 'c' ),
			]
			);
		update_post_meta( $id, Newsletter::EXPORT, wp_slash( $state ) );
		Newsletter::audit( $id, 'newsletter_exported' );
		return true;
	}
}
