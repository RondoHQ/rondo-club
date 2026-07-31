<?php
/**
 * VolunteerCacheInvalidator
 *
 * Wipes the eligibility + relationship-quality transients whenever a person
 * record changes. Keeps the Vrijwilligers-dashboard data fresh without
 * making the dashboard pay the full re-compute cost on every load.
 *
 * @package Rondo\Volunteer
 */

namespace Rondo\Volunteer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VolunteerCacheInvalidator {

	public function __construct() {
		// Person CRUD via admin / REST / sync.
		add_action( 'save_post_person', [ $this, 'invalidate' ] );
		add_action( 'deleted_post', [ $this, 'maybe_invalidate_on_delete' ], 10, 2 );
		add_action( 'rest_after_insert_person', [ $this, 'invalidate_rest' ], 10, 2 );
		add_action( 'rest_after_update_person', [ $this, 'invalidate_rest' ], 10, 2 );

		add_action( 'rondo_fields_saved_post', [ $this, 'invalidate_on_field_save' ], 30 );
	}

	public function invalidate() {
		VolunteerEligibilityService::invalidate_cache();
		RelationshipQualityChecker::invalidate_cache();
	}

	public function invalidate_rest( $post, $request ) {
		$this->invalidate();
	}

	public function maybe_invalidate_on_delete( $post_id, $post = null ) {
		if ( ! $post && function_exists( 'get_post' ) ) {
			$post = get_post( $post_id );
		}
		if ( $post && isset( $post->post_type ) && $post->post_type === 'person' ) {
			$this->invalidate();
		}
	}

	public function invalidate_on_field_save( $post_id ) {
		if ( get_post_type( (int) $post_id ) !== 'person' ) {
			return;
		}
		$this->invalidate();
	}
}
