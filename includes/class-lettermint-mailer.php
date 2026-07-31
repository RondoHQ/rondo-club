<?php
/**
 * Lettermint mail transport for wp_mail().
 *
 * Intercepts wp_mail() and sends email through Lettermint while preserving
 * existing call sites and wp_mail semantics.
 *
 * @package Rondo\Notifications
 */

namespace Rondo\Notifications;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LettermintMailer {

	/**
	 * Default email tag used when no custom tag is supplied.
	 */
	const DEFAULT_TAG = 'rondo-wp-mail';

	/**
	 * Cached Lettermint client instance.
	 *
	 * @var \Lettermint\Lettermint|null
	 */
	private ?\Lettermint\Lettermint $client = null;

	public function __construct() {
		add_filter( 'pre_wp_mail', [ $this, 'send_via_lettermint' ], 10, 2 );
	}

	/**
	 * pre_wp_mail filter callback.
	 *
	 * Returns:
	 * - `null` when Lettermint is not configured, so WordPress default mail flow runs
	 * - `true` on successful Lettermint send
	 * - `false` when Lettermint send fails
	 *
	 * @param null|bool $pre  Short-circuit value from earlier filters.
	 * @param array     $atts wp_mail() atts.
	 * @return null|bool
	 */
	public function send_via_lettermint( $pre, array $atts ) {
		if ( $pre !== null ) {
			return $pre;
		}

		if ( ! class_exists( '\Lettermint\Lettermint' ) ) {
			return null;
		}

		$client = $this->get_client();
		if ( ! $client ) {
			return null;
		}

		try {
			$headers      = $this->parse_headers( $atts['headers'] ?? [] );
			$recipients   = $this->normalize_address_list( $atts['to'] ?? [] );
			$subject      = (string) ( $atts['subject'] ?? '' );
			$message      = (string) ( $atts['message'] ?? '' );
			$attachments  = $this->normalize_attachment_list( $atts['attachments'] ?? [] );
			$from_details = $this->resolve_from( $headers );

			if ( empty( $recipients ) ) {
				return false;
			}

			$email = $client->email
				->from( $from_details['formatted'] )
				->to( ...$recipients )
				->subject( $subject );

			if ( ! empty( $headers['cc'] ) ) {
				$email->cc( ...$headers['cc'] );
			}
			if ( ! empty( $headers['bcc'] ) ) {
				$email->bcc( ...$headers['bcc'] );
			}
			if ( ! empty( $headers['reply_to'] ) ) {
				$email->replyTo( ...$headers['reply_to'] );
			}

			$route_override = sanitize_text_field( (string) ( $headers['x_lettermint_route'] ?? '' ) );
			if ( $route_override !== '' ) {
				$email->route( $route_override );
			}

			$tag = $headers['x_rondo_email_tag'] ?? self::DEFAULT_TAG;
			$email->tag( sanitize_title( (string) $tag ) );

			$content_type = strtolower( trim( (string) ( $headers['content_type'] ?? '' ) ) );
			if ( $content_type === '' ) {
				$content_type = 'text/plain';
			}
			if ( str_starts_with( $content_type, 'text/html' ) ) {
				$email->html( $message );
				$email->text( wp_strip_all_tags( $message ) );
			} else {
				$email->text( $message );
			}

			$custom_headers = $headers['custom'] ?? [];
			if ( ! empty( $custom_headers ) ) {
				$email->headers( $custom_headers );
			}

			foreach ( $attachments as $attachment_path ) {
				$path = is_string( $attachment_path ) ? trim( $attachment_path ) : '';
				if ( $path === '' || ! is_readable( $path ) ) {
					continue;
				}

				$filename = wp_basename( $path );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$content = file_get_contents( $path );
				if ( $content === false ) {
					continue;
				}

				$email->attach( $filename, base64_encode( $content ) );
			}

			$email->metadata(
				array_merge(
					[
						'site_host'    => (string) wp_parse_url( home_url(), PHP_URL_HOST ),
						'mail_hash'    => md5( implode( '|', $recipients ) . '|' . $subject . '|' . $message ),
						'content_type' => $content_type,
					],
					is_array( $headers['x_rondo_metadata'] ?? null ) ? $headers['x_rondo_metadata'] : []
				)
			);

			$email->send();

			return true;
		} catch ( \Throwable $e ) {
			error_log( 'Lettermint wp_mail send failed: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Get Lettermint client if configured.
	 *
	 * @return \Lettermint\Lettermint|null
	 */
	private function get_client(): ?\Lettermint\Lettermint {
		if ( $this->client instanceof \Lettermint\Lettermint ) {
			return $this->client;
		}

		$token = LettermintConfig::get_api_token();
		if ( $token === '' ) {
			return null;
		}

		$this->client = new \Lettermint\Lettermint( $token );

		return $this->client;
	}

	/**
	 * Parse wp_mail headers.
	 *
	 * @param string|array $headers Raw wp_mail headers.
	 * @return array
	 */
	private function parse_headers( $headers ): array {
		$parsed = [
			'from'               => '',
			'cc'                 => [],
			'bcc'                => [],
			'reply_to'           => [],
			'content_type'       => 'text/plain',
			'x_rondo_email_tag'  => '',
			'x_rondo_metadata'   => [],
			'x_lettermint_route' => '',
			'custom'             => [],
		];

		$header_lines = [];

		if ( is_string( $headers ) ) {
			$header_lines = preg_split( "/\r\n|\r|\n/", $headers ) ?: [];
		} elseif ( is_array( $headers ) ) {
			foreach ( $headers as $key => $value ) {
				if ( is_int( $key ) ) {
					$parts = preg_split( "/\r\n|\r|\n/", (string) $value ) ?: [];
					foreach ( $parts as $part ) {
						$header_lines[] = $part;
					}
				} else {
					$header_lines[] = $key . ': ' . $value;
				}
			}
		}

		foreach ( $header_lines as $line ) {
			if ( ! is_string( $line ) ) {
				continue;
			}

			$line = trim( $line );
			if ( $line === '' || strpos( $line, ':' ) === false ) {
				continue;
			}

			[ $name, $value ] = explode( ':', $line, 2 );
			$name             = strtolower( trim( $name ) );
			$value            = trim( $value );

			switch ( $name ) {
				case 'from':
					$parsed['from'] = $value;
					break;
				case 'cc':
					$parsed['cc'] = array_merge( $parsed['cc'], $this->split_address_header( $value ) );
					break;
				case 'bcc':
					$parsed['bcc'] = array_merge( $parsed['bcc'], $this->split_address_header( $value ) );
					break;
				case 'reply-to':
					$parsed['reply_to'] = array_merge( $parsed['reply_to'], $this->split_address_header( $value ) );
					break;
				case 'content-type':
					$content_type = strtolower( trim( explode( ';', $value )[0] ) );
					if ( $content_type !== '' ) {
						$parsed['content_type'] = $content_type;
					}
					break;
				case 'x-rondo-email-tag':
					$parsed['x_rondo_email_tag'] = $value;
					break;
				case 'x-rondo-metadata':
					$decoded = json_decode( $value, true );
					if ( is_array( $decoded ) ) {
						$parsed['x_rondo_metadata'] = $this->sanitize_metadata( $decoded );
					}
					break;
				case 'x-lettermint-route':
				case 'x-lm-route':
					$parsed['x_lettermint_route'] = $value;
					break;
				default:
					$parsed['custom'][ $name ] = $value;
			}
		}

		$parsed['cc']       = array_values( array_unique( $parsed['cc'] ) );
		$parsed['bcc']      = array_values( array_unique( $parsed['bcc'] ) );
		$parsed['reply_to'] = array_values( array_unique( $parsed['reply_to'] ) );

		return $parsed;
	}

	/**
	 * Resolve the final "from" address and name (including WordPress filters).
	 *
	 * @param array $headers Parsed headers.
	 * @return array{email: string, name: string, formatted: string}
	 */
	private function resolve_from( array $headers ): array {
		$default_email = (string) get_option( 'admin_email' );
		$default_name  = 'WordPress';

		$config_email = LettermintConfig::get_from_email();
		if ( $config_email !== '' ) {
			$default_email = $config_email;
		}

		$config_name = LettermintConfig::get_from_name();
		if ( trim( $config_name ) !== '' ) {
			$default_name = $config_name;
		}

		$email = $default_email;
		$name  = $default_name;

		if ( ! empty( $headers['from'] ) ) {
			$from = $this->parse_single_address( $headers['from'] );
			if ( $from['email'] !== '' ) {
				$email = $from['email'];
			}
			if ( $from['name'] !== '' ) {
				$name = $from['name'];
			}
		}

		$email = (string) apply_filters( 'wp_mail_from', $email );
		$name  = (string) apply_filters( 'wp_mail_from_name', $name );

		if ( ! is_email( $email ) ) {
			$email = $default_email;
		}

		$formatted = trim( $name ) !== ''
			? sprintf( '%s <%s>', trim( $name ), $email )
			: $email;

		return [
			'email'     => $email,
			'name'      => $name,
			'formatted' => $formatted,
		];
	}

	/**
	 * Normalize wp_mail recipient list into an array of addresses.
	 *
	 * @param string|array $addresses Recipient list.
	 * @return array
	 */
	private function normalize_address_list( $addresses ): array {
		$normalized = [];

		if ( is_string( $addresses ) ) {
			$normalized = $this->split_address_header( $addresses );
		} elseif ( is_array( $addresses ) ) {
			foreach ( $addresses as $address ) {
				if ( ! is_string( $address ) ) {
					continue;
				}
				$normalized = array_merge( $normalized, $this->split_address_header( $address ) );
			}
		}

		$normalized = array_map( 'trim', $normalized );
		$normalized = array_filter( $normalized, static fn( $value ) => $value !== '' );

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Split a comma-separated address header while preserving quoted parts.
	 *
	 * @param string $value Header value.
	 * @return array
	 */
	private function split_address_header( string $value ): array {
		$parts = str_getcsv( $value, ',', '"' );
		if ( ! is_array( $parts ) ) {
			return [];
		}

		return array_map( 'trim', $parts );
	}

	/**
	 * Parse a single address string (optionally with display name).
	 *
	 * @param string $value Address string.
	 * @return array{email: string, name: string}
	 */
	private function parse_single_address( string $value ): array {
		$value = trim( $value );
		$name  = '';
		$email = $value;

		if ( preg_match( '/^(.*)<([^>]+)>$/', $value, $matches ) ) {
			$name  = trim( trim( $matches[1] ), "\"' " );
			$email = trim( $matches[2] );
		}

		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			$email = '';
		}

		return [
			'email' => $email,
			'name'  => $name,
		];
	}

	/**
	 * Normalize wp_mail attachments to an array of paths.
	 *
	 * @param string|array $attachments Raw attachments value.
	 * @return array
	 */
	private function normalize_attachment_list( $attachments ): array {
		if ( is_array( $attachments ) ) {
			return $attachments;
		}

		if ( is_string( $attachments ) && trim( $attachments ) !== '' ) {
			return preg_split( "/\r\n|\r|\n/", $attachments ) ?: [];
		}

		return [];
	}

	/**
	 * Sanitize metadata values for Lettermint payload.
	 *
	 * @param array $metadata Raw metadata map.
	 * @return array<string, string>
	 */
	private function sanitize_metadata( array $metadata ): array {
		$sanitized = [];

		foreach ( $metadata as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( $key === '' ) {
				continue;
			}

			if ( is_bool( $value ) ) {
				$sanitized[ $key ] = $value ? '1' : '0';
				continue;
			}

			if ( is_int( $value ) || is_float( $value ) ) {
				$sanitized[ $key ] = (string) $value;
				continue;
			}

			if ( is_string( $value ) ) {
				$sanitized[ $key ] = sanitize_text_field( $value );
			}
		}

		return $sanitized;
	}
}
