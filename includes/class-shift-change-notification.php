<?php
/** Notifications for changes to an existing, upcoming volunteer shift. */

namespace Rondo\Volunteer;

use Rondo\Core\PostTitle;
use Rondo\Fields\Fields;
use Rondo\Notifications\EmailTemplate;
use Rondo\People\CommunicationPolicy;
use Rondo\Users\GuardianAccountService;

final class ShiftChangeNotification {
	public const HOOK         = 'rondo_send_shift_change_notification';
	private const META_PREFIX = '_shift_change_notice_';
	private const TRACKED     = [ 'dienst_type_id', 'start_datetime', 'end_datetime', 'notes' ];

	public function __construct() {
		add_action( 'rondo_fields_saved_post', [ $this, 'queue' ], 35, 2 );
		add_action( self::HOOK, [ $this, 'send' ], 10, 2 );
	}

	/** Queue only after all fields have been validated and persisted. */
	public function queue( int $shift_id, array $changes ): void {
		if ( get_post_type( $shift_id ) !== 'dienst_shift' ) {
			return;
		}
		$after    = $this->snapshot( $shift_id );
		$before   = $after;
		$assigned = ShiftAssignments::person_ids( $shift_id );
		foreach ( $changes as [ $definition, $old_value ] ) {
			$name = $definition['canonical_name'];
			if ( in_array( $name, self::TRACKED, true ) ) {
				$before[ $name ] = $old_value;
			}
			if ( $name === 'assigned_persons' ) {
				$assigned = array_intersect( $assigned, array_map( 'intval', (array) $old_value ) );
			}
			if ( $name === 'status' && in_array( $old_value, [ 'geannuleerd', 'voltooid' ], true ) ) {
				return;
			}
		}
		if ( $before === $after || ! $assigned || ! $this->upcoming( $shift_id, $before ) || ! $this->upcoming( $shift_id, $after ) ) {
			return;
		}
		$event = wp_generate_uuid4();
		update_post_meta(
			$shift_id,
			self::META_PREFIX . $event,
			[
				'before'   => $before,
				'after'    => $after,
				'pending'  => array_values( $assigned ),
				'sent'     => [],
				'no_email' => [],
			]
		);
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::HOOK, [ $shift_id, $event ] );
	}

	/** Retry failed recipients only; superseded or cancelled changes never send stale mail. */
	public function send( int $shift_id, string $event ): void {
		$key     = self::META_PREFIX . $event;
		$lock    = 'rondo_shift_notice_lock_' . $event;
		$expires = (int) get_option( $lock, 0 );
		if ( $expires > 0 && $expires < time() ) {
			delete_option( $lock );
		}
		if ( ! add_option( $lock, time() + 5 * MINUTE_IN_SECONDS, '', false ) ) {
			return;
		}
		try {
			if ( get_post_type( $shift_id ) !== 'dienst_shift' ) {
				return;
			}
			$notice = get_post_meta( $shift_id, $key, true );
			if ( ! is_array( $notice ) || empty( $notice['pending'] ) ) {
				return;
			}
			$after = $this->snapshot( $shift_id );
			if ( $after !== $notice['after'] || ! $this->upcoming( $shift_id, $after ) ) {
				$notice['pending'] = [];
				$notice['skipped'] = current_time( 'mysql' );
				update_post_meta( $shift_id, $key, $notice );
				return;
			}
			$assigned = ShiftAssignments::person_ids( $shift_id );
			foreach ( $notice['pending'] as $person_id ) {
				$email = in_array( $person_id, $assigned, true ) ? CommunicationPolicy::primary_email( $person_id ) : null;
				if ( ! $email ) {
					$notice['no_email'][] = $person_id;
				} else {
					$subject = 'Je inschrijftaak is gewijzigd: ' . PostTitle::plain( (int) $after['dienst_type_id'] );
					$body    = '<p>Hoi ' . esc_html( GuardianAccountService::greeting_name_for_person( $person_id ) ) . ',</p>';
					$body   .= '<p>Je inschrijftaak is gewijzigd. Je blijft ingedeeld. Hieronder staan de oude en nieuwe gegevens.</p>';
					$body   .= $this->details_html( 'Was', $notice['before'] ) . $this->details_html( 'Wordt', $after );
					$body   .= '<p>Controleer ook je eigen agenda. Kun je op het nieuwe moment niet? Neem contact op met de accommodatiemanager.</p>';
					$html    = EmailTemplate::render(
						[
							'eyebrow'      => 'Gewijzigde inschrijftaak',
							'heading'      => $subject,
							'preheader'    => $subject,
							'body_html'    => $body,
							'cta_url'      => home_url( '/vrijwillig?tab=mine' ),
							'cta_label'    => 'Bekijk mijn inschrijftaken',
							'accent_color' => EmailTemplate::accent_color(),
						]
					);
					try {
						$sent = wp_mail( $email, $subject, $html, [ 'Content-Type: text/html; charset=UTF-8' ] );
					} catch ( \Throwable $error ) {
						$sent = false;
					}
					if ( ! $sent ) {
						continue;
					}
					$notice['sent'][ $person_id ] = current_time( 'mysql' );
				}
				$notice['pending'] = array_values( array_diff( $notice['pending'], [ $person_id ] ) );
				update_post_meta( $shift_id, $key, $notice );
			}
			if ( $notice['pending'] && ! wp_next_scheduled( self::HOOK, [ $shift_id, $event ] ) ) {
				wp_schedule_single_event( time() + 15 * MINUTE_IN_SECONDS, self::HOOK, [ $shift_id, $event ] );
			}
		} finally {
			delete_option( $lock );
		}
	}

	private function snapshot( int $shift_id ): array {
		$result = [];
		foreach ( self::TRACKED as $field ) {
			$result[ $field ] = Fields::get_for_post( $shift_id, $field );
		}
		return $result;
	}

	private function upcoming( int $shift_id, array $snapshot ): bool {
		if ( get_post_status( $shift_id ) !== 'publish' || ! in_array( Fields::get_for_post( $shift_id, 'status' ), [ 'open', 'vol' ], true ) || empty( $snapshot['dienst_type_id'] ) || empty( $snapshot['start_datetime'] ) || empty( $snapshot['end_datetime'] ) ) {
			return false;
		}
		try {
			$start = new \DateTimeImmutable( $snapshot['start_datetime'], wp_timezone() );
			$end   = new \DateTimeImmutable( $snapshot['end_datetime'], wp_timezone() );
			return $start > current_datetime() && $end > $start;
		} catch ( \Exception $error ) {
			return false;
		}
	}

	private function details_html( string $heading, array $snapshot ): string {
		$start = new \DateTimeImmutable( $snapshot['start_datetime'], wp_timezone() );
		$end   = new \DateTimeImmutable( $snapshot['end_datetime'], wp_timezone() );
		$text  = PostTitle::plain( (int) $snapshot['dienst_type_id'] ) . "\n";
		$text .= wp_date( 'd-m-Y H:i', $start->getTimestamp(), wp_timezone() ) . ' tot ' . wp_date( 'd-m-Y H:i', $end->getTimestamp(), wp_timezone() );
		if ( ! empty( $snapshot['notes'] ) ) {
			$text .= "\n\nToelichting: " . $snapshot['notes'];
		}
		return '<h2>' . esc_html( $heading ) . '</h2>' . EmailTemplate::format_plain_text( $text );
	}
}
