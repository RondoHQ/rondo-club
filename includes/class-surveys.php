<?php
/** Survey storage, immutable publications, invitations and submissions. */
namespace Rondo\Surveys;

use Rondo\People\CommunicationPolicy;
use Rondo\Notifications\EmailTemplate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Surveys {
	const TYPE     = 'rondo_survey';
	const VERSION  = 'rondo_survey_version';
	const INVITE   = 'rondo_survey_invite';
	const RESPONSE = 'rondo_survey_reply';
	const CRON     = 'rondo_survey_deliveries';

	public function __construct() {
		add_action( 'init', [ self::class, 'register' ] );
		add_filter( 'rondo_fields_validate_value', [ self::class, 'validate_survey_field' ], 10, 3 );
		add_action( self::CRON, [ self::class, 'deliver' ] );
		add_action(
			'init',
			static function () {
				if ( ! wp_next_scheduled( self::CRON ) ) {
					wp_schedule_event( time() + 300, 'hourly', self::CRON );
				}
			}
			);
	}

	public static function validate_survey_field( $value, int $post_id, array $definition ) {
		if ( is_wp_error( $value ) || ( $definition['context'] ?? '' ) !== 'dienst_type' || ( $definition['canonical_name'] ?? '' ) !== 'survey_id' ) {
			return $value;
		}
		if ( $value === null || $value === 0 || $value === '0' ) {
			return $value;
		}
		if ( ! is_numeric( $value ) || (float) $value !== (float) absint( $value ) || is_wp_error( self::read( (int) $value ) ) ) {
			return Schema::error( 'Selecteer een bestaande Rondo-enquête.' );
		}
		return $value;
	}

	public static function register(): void {
		foreach ( [ self::TYPE, self::VERSION, self::INVITE, self::RESPONSE ] as $type ) {
			register_post_type(
				$type,
				[
					'public'       => false,
					'show_ui'      => false,
					'show_in_rest' => false,
					'rewrite'      => false,
					'supports'     => [],
					'can_export'   => false,
					'map_meta_cap' => false,
					'capabilities' => array_fill_keys( [ 'read', 'read_post', 'edit_post', 'delete_post', 'edit_posts', 'create_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'edit_others_posts' ], 'do_not_allow' ),
				]
				);
		}
	}

	/** Native option uniqueness serializes publish/close/submit and invitation claims. */
	public static function locked( string $key, callable $callback ) {
		$name = 'rondo_survey_lock_' . hash( 'sha256', $key );
		if ( ! add_option( $name, 'locked', '', false ) ) {
			return Schema::error( 'Deze enquête wordt verwerkt. Probeer het opnieuw.', 409 );
		}
		try {
			return $callback();
		} finally {
			delete_option( $name );
		}
	}

	public static function read( int $id, string $type = self::TYPE ) {
		$post = get_post( $id );
		if ( ! $post || $post->post_type !== $type || $post->post_status !== 'private' ) {
			return Schema::error( 'Enquête niet gevonden.', 404 );
		}
		$data = json_decode( $post->post_content, true );
		if ( is_array( $data ) && $type === self::INVITE ) {
			$claim = 'rondo_survey_submit_' . hash( 'sha256', $post->post_parent . ':invite:' . $id );
			if ( (int) get_option( $claim, 0 ) > 0 ) {
				$data['completed'] = true;
			}
		}
		return is_array( $data ) ? [ 'id' => $id ] + $data : Schema::error( 'Enquêtegegevens zijn ongeldig.', 500 );
	}

	public static function write( string $type, array $data, int $id = 0, int $parent = 0 ) {
		unset( $data['id'] );
		return wp_insert_post(
			wp_slash(
			[
				'ID'           => $id,
				'post_type'    => $type,
				'post_parent'  => $parent,
				'post_status'  => 'private',
				'post_title'   => $data['title'] ?? 'Enquête',
				'post_content' => wp_json_encode( $data, JSON_UNESCAPED_UNICODE ),
			]
			),
			true
			);
	}

	public static function children( int $id, string $type ): array {
		return get_posts(
			[
				'post_type'        => $type,
				'post_status'      => 'private',
				'post_parent'      => $id,
				'numberposts'      => -1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
			]
			);
	}

	public static function create( array $input ) {
		$doc = Schema::validate( $input['document'] ?? [] );
		if ( is_wp_error( $doc ) ) {
			return $doc;
		}
		$title = sanitize_text_field( $input['title'] ?? 'Nieuwe enquête' );
		return self::write(
			self::TYPE,
			[
				'title'           => $title ?: 'Nieuwe enquête',
				'intro'           => '',
				'thanks'          => 'Bedankt voor het invullen!',
				'closes_at'       => '',
				'linked'          => false,
				'access'          => 'public',
				'reminder_days'   => 7,
				'status'          => 'draft',
				'revision'        => wp_generate_uuid4(),
				'current_version' => 0,
				'document'        => $doc,
			]
			);
	}

	public static function update( int $id, array $input ) {
		return self::locked(
			'survey:' . $id,
			static function () use ( $id, $input ) {
				$data = self::read( $id );
				if ( is_wp_error( $data ) ) {
					return $data;
				}
				if ( isset( $input['revision'] ) && $input['revision'] !== $data['revision'] ) {
					return Schema::error( 'Een andere beheerder heeft deze enquête gewijzigd. Vernieuw de pagina voordat je verder bewerkt.', 409 );
				}
				foreach ( [ 'title', 'intro', 'thanks' ] as $key ) {
					if ( isset( $input[ $key ] ) && ( ! is_string( $input[ $key ] ) || mb_strlen( $input[ $key ] ) > 10000 ) ) {
						return Schema::error( 'Tekstvelden mogen maximaal 10.000 tekens bevatten.' );
					}
					if ( isset( $input[ $key ] ) ) {
						$data[ $key ] = $key === 'title' ? sanitize_text_field( $input[ $key ] ) : sanitize_textarea_field( $input[ $key ] );
					}
				}
				if ( trim( $data['title'] ) === '' ) {
					return Schema::error( 'Geef de enquête een titel.' );
				}
				if ( isset( $input['document'] ) ) {
					$doc = Schema::validate( $input['document'] );
					if ( is_wp_error( $doc ) ) {
						return $doc;
					}
					$data['document'] = $doc;
				}
				if ( isset( $input['closes_at'] ) ) {
					if ( $input['closes_at'] !== '' && ( ! is_string( $input['closes_at'] ) || ! preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})$/D', $input['closes_at'] ) || ! strtotime( $input['closes_at'] ) ) ) {
						return Schema::error( 'Gebruik een geldige sluitingsdatum met tijdzone.' );
					}
					$data['closes_at'] = $input['closes_at'];
				}
				foreach ( [ 'linked', 'access' ] as $key ) {
					if ( array_key_exists( $key, $input ) ) {
						$value = $input[ $key ];
						if ( ( $key === 'linked' && ! is_bool( $value ) ) || ( $key === 'access' && ! in_array( $value, [ 'public', 'invitation' ], true ) ) ) {
							return Schema::error( 'Ongeldige toegangsinstelling.' );
						}
						if ( $data['current_version'] && $value !== $data[ $key ] ) {
							return Schema::error( 'Toegang en persoonskoppeling staan na de eerste publicatie vast. Dupliceer de enquête om dit te wijzigen.', 409 );
						}
						$data[ $key ] = $value;
					}
				}
				if ( isset( $input['reminder_days'] ) ) {
					if ( ! is_int( $input['reminder_days'] ) || $input['reminder_days'] < 0 || $input['reminder_days'] > 90 ) {
						return Schema::error( 'Herinnering: 0 (uit) tot 90 dagen.' );
					}
					$data['reminder_days'] = $input['reminder_days'];
				}
				$data['revision'] = wp_generate_uuid4();
				return self::write( self::TYPE, $data, $id );
			}
			);
	}

	public static function action( int $id, string $action ) {
		return self::locked(
			'survey:' . $id,
			static function () use ( $id, $action ) {
				$data = self::read( $id );
				if ( is_wp_error( $data ) ) {
					return $data;
				}
				if ( $action === 'duplicate' ) {
					$data['title']          .= ' (kopie)';
					$data['status']          = 'draft';
					$data['current_version'] = 0;
					$data['closes_at']       = '';
					return self::write( self::TYPE, $data );
				}
				if ( $action === 'publish' ) {
					$doc = Schema::validate( $data['document'] );
					if ( is_wp_error( $doc ) ) {
						return $doc;
					}
					if ( $data['closes_at'] && strtotime( $data['closes_at'] ) <= time() ) {
						return Schema::error( 'De sluitingsdatum moet in de toekomst liggen.' );
					}
					$version = self::write(
					self::VERSION,
					[
						'title'    => $data['title'],
						'intro'    => $data['intro'],
						'thanks'   => $data['thanks'],
						'document' => $doc,
					],
					0,
					$id
					);
					if ( is_wp_error( $version ) ) {
						return $version;
					}
					$data['current_version'] = $version;
					$data['status']          = 'published';
				} elseif ( $action === 'close' ) {
					$data['status'] = 'closed';
				} else {
					return Schema::error( 'Onbekende actie.' );
				}
				$result = self::write( self::TYPE, $data, $id );
				return is_wp_error( $result ) ? $result : $id;
			}
			);
	}

	public static function is_open( array $survey ): bool {
		return $survey['status'] === 'published' && ( ! $survey['closes_at'] || strtotime( $survey['closes_at'] ) > time() );
	}

	public static function url( int $id, string $token = '' ): string {
		return add_query_arg( $token ? [ 'uitnodiging' => $token ] : [], home_url( '/enquete/' . $id ) );
	}

	public static function invitation( int $id, string $token ) {
		if ( ! preg_match( '/^[a-f0-9]{64}$/D', $token ) ) {
			return Schema::error( 'Deze uitnodiging is ongeldig.', 404 );
		}
		$posts = get_posts(
			[
				'post_type'        => self::INVITE,
				'post_status'      => 'private',
				'post_parent'      => $id,
				'meta_key'         => '_survey_token_hash',
				'meta_value'       => hash( 'sha256', $token ),
				'numberposts'      => 1,
				'suppress_filters' => true,
			]
			);
		return $posts ? self::read( $posts[0]->ID, self::INVITE ) : Schema::error( 'Deze uitnodiging is ongeldig.', 404 );
	}

	/** Prepare only. Queueing is a separate, explicit send action. */
	public static function prepare( int $id, array $recipients, string $context = '' ) {
		return self::locked(
			'survey:' . $id,
			static function () use ( $id, $recipients, $context ) {
				$survey = self::read( $id );
				if ( is_wp_error( $survey ) ) {
					return $survey;
				}
				if ( ! self::is_open( $survey ) ) {
					return Schema::error( 'Publiceer de enquête voordat je uitnodigingen maakt.', 409 );
				}
				if ( count( $recipients ) > 2000 ) {
					return Schema::error( 'Voeg maximaal 2.000 ontvangers per keer toe.' );
				}
				$valid = [];
				foreach ( $recipients as $recipient ) {
					if ( ! is_array( $recipient ) || ! is_string( $recipient['email'] ?? null ) || ( isset( $recipient['name'] ) && ! is_string( $recipient['name'] ) ) || ! is_email( $recipient['email'] ) ) {
						return Schema::error( 'Controleer de e-mailadressen van alle ontvangers.' );
					}
					$email           = strtolower( sanitize_email( $recipient['email'] ) );
					$valid[ $email ] = [
						'email'     => $email,
						'name'      => sanitize_text_field( $recipient['name'] ?? '' ),
						'person_id' => absint( $recipient['person_id'] ?? 0 ),
					];
				}
				$existing = [];
				foreach ( self::children( $id, self::INVITE ) as $post ) {
					$invite = self::read( $post->ID, self::INVITE );
					if ( $invite['context'] === $context ) {
						$existing[ $invite['email'] ] = $invite['id'];
					}
				}
				$ids = [];
				foreach ( $valid as $email => $recipient ) {
					if ( isset( $existing[ $email ] ) ) {
						$ids[] = $existing[ $email ];
						continue;
					}
					$token     = bin2hex( random_bytes( 32 ) );
					$data      = $recipient + [
						'token'       => $token,
						'context'     => $context,
						'person_id'   => 0,
						'status'      => 'prepared',
						'completed'   => false,
						'sent_at'     => '',
						'reminded_at' => '',
						'created_at'  => gmdate( 'c' ),
						'version'     => $survey['current_version'],
					];
					$invite_id = self::write( self::INVITE, $data, 0, $id );
					if ( is_wp_error( $invite_id ) ) {
						return $invite_id;
					}
					update_post_meta( $invite_id, '_survey_token_hash', hash( 'sha256', $token ) );
					$ids[] = $invite_id;
				}
				return $ids;
			}
			);
	}

	public static function queue( int $id, bool $reminder = false ) {
		return self::locked(
			'survey:' . $id,
			static function () use ( $id, $reminder ) {
				$s = self::read( $id );
				if ( is_wp_error( $s ) || ! self::is_open( $s ) ) {
					return Schema::error( 'De enquête is niet open.', 409 );
				}
				$count = 0;
				foreach ( self::children( $id, self::INVITE ) as $post ) {
					$i = self::read( $post->ID, self::INVITE );
					if ( $i['completed'] ) {
						continue;
					}
					if ( ( ! $reminder && in_array( $i['status'], [ 'prepared', 'failed' ], true ) ) || ( $reminder && in_array( $i['status'], [ 'sent', 'reminder_failed' ], true ) && ! $i['reminded_at'] ) ) {
						$i['status'] = $reminder ? 'reminder_queued' : 'queued';
						$result      = self::write( self::INVITE, $i, $i['id'], $id );
						if ( is_wp_error( $result ) ) {
							return $result;
						}
						++$count;
					}
				}
				return [ 'queued' => $count ];
			}
			);
	}

	/** Small cron batches; uncertain deliveries remain 'sending' to avoid duplicates. */
	public static function deliver(): void {
		$budget  = 30;
		$surveys = get_posts(
			[
				'post_type'        => self::TYPE,
				'post_status'      => 'private',
				'numberposts'      => -1,
				'suppress_filters' => true,
			]
			);
		foreach ( $surveys as $post ) {
			if ( $budget <= 0 ) {
				break;
			}
			self::locked(
				'survey:' . $post->ID,
				static function () use ( $post, &$budget ) {
					$s = self::read( $post->ID );
					if ( is_wp_error( $s ) || ! self::is_open( $s ) ) {
						return;
					}
					foreach ( self::children( $s['id'], self::INVITE ) as $p ) {
						if ( $budget <= 0 ) {
							break;
						}
						$i = self::read( $p->ID, self::INVITE );
						if ( $i['completed'] || ( $i['person_id'] && ( get_post_type( $i['person_id'] ) !== 'person' || ! CommunicationPolicy::may_contact( $i['person_id'] ) ) ) ) {
							continue;
						}
						$auto = $s['reminder_days'] > 0 && $i['status'] === 'sent' && ! $i['reminded_at'] && strtotime( $i['sent_at'] ) + DAY_IN_SECONDS * $s['reminder_days'] <= time();
						if ( ! $auto && ! in_array( $i['status'], [ 'queued', 'reminder_queued' ], true ) ) {
							continue;
						}
						$reminder    = $auto || $i['status'] === 'reminder_queued';
						$i['status'] = 'sending';
						if ( is_wp_error( self::write( self::INVITE, $i, $i['id'], $s['id'] ) ) ) {
							continue;
						}
						--$budget;
						$html        = EmailTemplate::render(
						[
							'heading'      => $s['title'],
							'body_html'    => EmailTemplate::format_plain_text( ( $i['name'] ? 'Hoi ' . $i['name'] . ",\n\n" : '' ) . ( $reminder ? 'Heb je onze enquête al ingevuld? Je mening is welkom.' : 'We horen graag je mening. Vul onze enquête in via onderstaande link.' ) ),
							'cta_label'    => 'Vul de enquête in',
							'cta_url'      => self::url( $s['id'], $i['token'] ),
							'accent_color' => EmailTemplate::accent_color(),
						]
						);
						$sent        = wp_mail( $i['email'], ( $reminder ? 'Herinnering: ' : '' ) . $s['title'], $html, [ 'Content-Type: text/html; charset=UTF-8' ] );
						$i['status'] = $sent ? 'sent' : ( $reminder ? 'reminder_failed' : 'failed' );
						if ( $sent ) {
							$i[ $reminder ? 'reminded_at' : 'sent_at' ] = gmdate( 'c' );
						}
						self::write( self::INVITE, $i, $i['id'], $s['id'] );
					}
				}
				);
		}
	}

	/** A separate invitation for every shift/person context, using existing shift mail. */
	public static function shift_invitation( int $survey_id, int $shift_id, int $person_id, string $email ) {
		$ids = self::prepare(
			$survey_id,
			[
				[
					'email'     => $email,
					'name'      => \Rondo\Core\PostTitle::plain( $person_id ),
					'person_id' => $person_id,
				],
			],
			'dienst:' . $shift_id . ':persoon:' . $person_id
			);
		if ( is_wp_error( $ids ) ) {
			return $ids;
		}
		return self::read( $ids[0], self::INVITE );
	}

	public static function public_data( int $id, string $token = '' ) {
		$s = self::read( $id );
		if ( is_wp_error( $s ) || ! self::is_open( $s ) ) {
			return Schema::error( 'Deze enquête is gesloten of nog niet gepubliceerd.', 404 );
		}
		$i = null;
		if ( $token !== '' || $s['access'] === 'invitation' ) {
			$i = self::invitation( $id, $token );
			if ( is_wp_error( $i ) ) {
				return $i;
			}
			if ( $i['completed'] ) {
				return Schema::error( 'Deze enquête is al ingevuld. Bedankt!', 409 );
			}
		}
		$version = $i ? $i['version'] : $s['current_version'];
		$v       = self::read( $version, self::VERSION );
		if ( is_wp_error( $v ) ) {
			return $v;
		}
		$challenge = time() . '.' . bin2hex( random_bytes( 16 ) );
		return [
			'id'                  => $id,
			'version'             => $version,
			'title'               => $v['title'],
			'intro'               => $v['intro'],
			'document'            => $v['document'],
			'thanks'              => $v['thanks'],
			'closes_at'           => $s['closes_at'],
			'linked'              => $s['linked'],
			'personal_invitation' => (bool) $i,
			'challenge'           => $challenge . '.' . self::signature( $id, $version, $token, $challenge ),
		];
	}

	private static function signature( int $id, int $version, string $token, string $challenge ): string {
		return hash_hmac( 'sha256', $id . ':' . $version . ':' . $token . ':' . $challenge, wp_salt( 'nonce' ) );
	}

	public static function submit( int $id, array $input, string $token = '' ) {
		return self::locked(
			'survey:' . $id,
			static function () use ( $id, $input, $token ) {
				$s = self::read( $id );
				if ( is_wp_error( $s ) || ! self::is_open( $s ) ) {
					return Schema::error( 'Deze enquête is gesloten.', 409 );
				}
				$version = (int) ( $input['version'] ?? 0 );
				$parts   = explode( '.', (string) ( $input['challenge'] ?? '' ) );
				if ( count( $parts ) !== 3 || ! ctype_digit( $parts[0] ) || ! preg_match( '/^[a-f0-9]{32}$/D', $parts[1] ) || (int) $parts[0] > time() || (int) $parts[0] < time() - DAY_IN_SECONDS || ! hash_equals( self::signature( $id, $version, $token, $parts[0] . '.' . $parts[1] ), $parts[2] ) || ! empty( $input['website'] ) ) {
					return Schema::error( 'Open de enquête opnieuw en probeer het nogmaals.', 403 );
				}
				$i = null;
				if ( $token !== '' || $s['access'] === 'invitation' ) {
					$i = self::invitation( $id, $token );
					if ( is_wp_error( $i ) ) {
						return $i;
					}
					if ( $i['completed'] ) {
						return Schema::error( 'Deze uitnodiging is al gebruikt.', 409 );
					}
				}
				if ( $version !== (int) ( $i ? $i['version'] : $s['current_version'] ) ) {
					return Schema::error( 'De vragenlijst is bijgewerkt. Open de enquête opnieuw.', 409 );
				}
				$v = self::read( $version, self::VERSION );
				if ( is_wp_error( $v ) || (int) get_post_field( 'post_parent', $version ) !== $id ) {
					return Schema::error( 'Ongeldige vragenlijstversie.' );
				}
				$answers = Schema::answers( $v['document'], $input['answers'] ?? null );
				if ( is_wp_error( $answers ) ) {
					return $answers;
				}
				$key = 'rondo_survey_submit_' . hash( 'sha256', $id . ':' . ( $i ? 'invite:' . $i['id'] : $input['challenge'] ) );
				if ( ! add_option( $key, 'reserved', '', false ) ) {
					return Schema::error( 'Deze inzending is al ontvangen of wordt verwerkt.', 409 );
				}
				$response = [
					'version' => $version,
					'answers' => $answers,
				];
				if ( $s['linked'] && $i ) {
					$response['respondent'] = [
						'name'      => $i['name'],
						'email'     => $i['email'],
						'person_id' => $i['person_id'],
						'context'   => $i['context'],
					];
				}
				$reply = self::write( self::RESPONSE, $response, 0, $id );
				if ( is_wp_error( $reply ) ) {
					delete_option( $key );
					return $reply;
				}
				update_option( $key, $reply, false );
				if ( $i ) {
					$i['completed'] = true;
					self::write( self::INVITE, $i, $i['id'], $id );
				}
				return [
					'submitted' => true,
					'thanks'    => $v['thanks'],
				];
			}
			);
	}
}
