<?php
/**
 * Email notifications for room-booking changes.
 *
 * @package Rondo\Rooms
 */

namespace Rondo\Rooms;

use Rondo\Notifications\EmailTemplate;
use Rondo\Users\UserProvisioning;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class BookingNotification {

	/** Notify a booking holder and return a UI-safe delivery result. */
	public static function send_to_holder( array $booking, string $action, int $actor_user_id ): array {
		$holder_user_id = (int) ( $booking['holder_user_id'] ?? 0 );
		if ( $holder_user_id <= 0 ) {
			return [ 'status' => 'not_applicable' ];
		}

		$email = UserProvisioning::contact_email( $holder_user_id );
		if ( ! is_email( $email ) ) {
			return [ 'status' => 'no_email' ];
		}

		$labels  = [
			'created'   => 'Ruimte gereserveerd',
			'edited'    => 'Ruimtereservering gewijzigd',
			'cancelled' => 'Ruimtereservering geannuleerd',
			'extended'  => 'Ruimtereservering verlengd',
		];
		$subject = $labels[ $action ] ?? 'Ruimtereservering bijgewerkt';
		$actor   = get_userdata( $actor_user_id );
		$details = sprintf(
			"Ruimte: %s\nGroep: %s\nTijd: %s tot %s%s",
			(string) ( $booking['room_name'] ?? '' ),
			(string) ( $booking['context_label'] ?? '' ),
			self::format_date( (string) ( $booking['start_datetime'] ?? '' ) ),
			self::format_date( (string) ( $booking['effective_end_datetime'] ?? $booking['end_datetime'] ?? '' ) ),
			$actor && $actor_user_id !== $holder_user_id ? "\nGewijzigd door: " . $actor->display_name : ''
		);
		$html    = EmailTemplate::render(
			[
				'preheader' => $subject,
				'eyebrow'   => 'Accommodatie',
				'heading'   => $subject,
				'body_html' => EmailTemplate::format_plain_text( $details ),
				'cta_url'   => home_url( '/rooms' ),
				'cta_label' => 'Bekijk reservering',
			]
		);

		return [
			'status' => wp_mail( $email, $subject, $html, [ 'Content-Type: text/html; charset=UTF-8' ] ) ? 'sent' : 'send_failed',
		];
	}

	/** Notify newly authorized presenters. */
	public static function send_to_presenters( array $booking, array $presenter_user_ids ): void {
		foreach ( array_unique( array_map( 'absint', $presenter_user_ids ) ) as $user_id ) {
			if ( $user_id <= 0 || $user_id === (int) ( $booking['holder_user_id'] ?? 0 ) ) {
				continue;
			}
			$email = UserProvisioning::contact_email( $user_id );
			if ( ! is_email( $email ) ) {
				continue;
			}
			$subject = 'Je mag presenteren bij een ruimtereservering';
			$body    = sprintf(
				"Ruimte: %s\nGroep: %s\nTijd: %s tot %s",
				(string) ( $booking['room_name'] ?? '' ),
				(string) ( $booking['context_label'] ?? '' ),
				self::format_date( (string) ( $booking['start_datetime'] ?? '' ) ),
				self::format_date( (string) ( $booking['effective_end_datetime'] ?? $booking['end_datetime'] ?? '' ) )
			);
			$html    = EmailTemplate::render(
				[
					'preheader' => $subject,
					'eyebrow'   => 'Accommodatie',
					'heading'   => $subject,
					'body_html' => EmailTemplate::format_plain_text( $body ),
					'cta_url'   => home_url( '/rooms' ),
					'cta_label' => 'Bekijk reservering',
				]
			);
			wp_mail( $email, $subject, $html, [ 'Content-Type: text/html; charset=UTF-8' ] );
		}
	}

	private static function format_date( string $value ): string {
		try {
			return ( new \DateTimeImmutable( $value ) )->setTimezone( wp_timezone() )->format( 'd-m-Y H:i' );
		} catch ( \Exception $error ) {
			return $value;
		}
	}
}
