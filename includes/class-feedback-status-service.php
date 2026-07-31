<?php
/**
 * Applies feedback status transitions and their side effects.
 *
 * @package Rondo\Feedback
 */

namespace Rondo\Feedback;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StatusService {

	public const ALLOWED_STATUSES        = [ 'new', 'approved', 'in_progress', 'in_review', 'resolved', 'declined', 'needs_info' ];
	public const META_RESOLUTION_SUMMARY = '_feedback_resolution_summary';
	public const META_DECLINE_REASON     = '_feedback_decline_reason';

	/**
	 * Change a feedback item's status.
	 *
	 * @return array{changed: bool, previous_status: string, status: string, resolution_email?: array{status: string, recipient?: string}}|\WP_Error
	 */
	public function update( int $feedback_id, string $new_status, string $resolution_summary = '', string $decline_reason = '' ): array|\WP_Error {
		$feedback = get_post( $feedback_id );
		if ( ! $feedback || $feedback->post_type !== 'rondo_feedback' ) {
			return new \WP_Error( 'feedback_not_found', 'Feedback not found.' );
		}

		if ( ! in_array( $new_status, self::ALLOWED_STATUSES, true ) ) {
			return new \WP_Error( 'invalid_feedback_status', 'Invalid feedback status.' );
		}

		$current_status    = (string) ( get_field( 'status', $feedback_id ) ?: 'new' );
		$provided_summary  = trim( sanitize_textarea_field( $resolution_summary ) );
		$stored_summary    = trim( (string) get_post_meta( $feedback_id, self::META_RESOLUTION_SUMMARY, true ) );
		$effective_summary = $provided_summary !== '' ? $provided_summary : $stored_summary;

		$provided_reason  = trim( sanitize_textarea_field( $decline_reason ) );
		$stored_reason    = trim( (string) get_post_meta( $feedback_id, self::META_DECLINE_REASON, true ) );
		$effective_reason = $provided_reason !== '' ? $provided_reason : $stored_reason;

		if ( $current_status !== 'resolved' && $new_status === 'resolved' && $effective_summary === '' ) {
			return new \WP_Error(
				'feedback_resolution_summary_required',
				'Leg in het Nederlands uit hoe de feedback is opgelost.',
				[ 'status' => 400 ]
			);
		}

		if ( $current_status !== 'declined' && $new_status === 'declined' && $effective_reason === '' ) {
			return new \WP_Error(
				'feedback_decline_reason_required',
				'Leg in het Nederlands uit waarom de feedback wordt afgewezen.',
				[ 'status' => 400 ]
			);
		}

		if ( $provided_summary !== '' ) {
			update_post_meta( $feedback_id, self::META_RESOLUTION_SUMMARY, $provided_summary );
		}

		if ( $provided_reason !== '' ) {
			update_post_meta( $feedback_id, self::META_DECLINE_REASON, $provided_reason );
		}

		$result = [
			'changed'         => $current_status !== $new_status,
			'previous_status' => $current_status,
			'status'          => $new_status,
		];

		if ( ! $result['changed'] ) {
			return $result;
		}

		update_field( 'status', $new_status, $feedback_id );
		if ( $new_status === 'resolved' ) {
			update_post_meta( $feedback_id, '_feedback_resolved_at', current_time( 'mysql', true ) );
			$result['resolution_email'] = ( new ResolutionEmailSender() )->send( $feedback_id );
		} elseif ( $current_status === 'resolved' ) {
			delete_post_meta( $feedback_id, '_feedback_resolved_at' );
		}

		if ( $new_status === 'declined' ) {
			update_post_meta( $feedback_id, '_feedback_declined_at', current_time( 'mysql', true ) );
			$result['decline_email'] = ( new DeclineEmailSender() )->send( $feedback_id );
		} elseif ( $current_status === 'declined' ) {
			delete_post_meta( $feedback_id, '_feedback_declined_at' );
		}

		return $result;
	}
}
