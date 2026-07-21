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

	public const ALLOWED_STATUSES = [ 'new', 'approved', 'in_progress', 'in_review', 'resolved', 'declined', 'needs_info' ];

	/**
	 * Change a feedback item's status.
	 *
	 * @return array{changed: bool, previous_status: string, status: string, resolution_email?: array{status: string, recipient?: string}}|\WP_Error
	 */
	public function update( int $feedback_id, string $new_status ): array|\WP_Error {
		$feedback = get_post( $feedback_id );
		if ( ! $feedback || $feedback->post_type !== 'rondo_feedback' ) {
			return new \WP_Error( 'feedback_not_found', 'Feedback not found.' );
		}

		if ( ! in_array( $new_status, self::ALLOWED_STATUSES, true ) ) {
			return new \WP_Error( 'invalid_feedback_status', 'Invalid feedback status.' );
		}

		$current_status = (string) ( get_field( 'status', $feedback_id ) ?: 'new' );
		$result         = [
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

		return $result;
	}
}
