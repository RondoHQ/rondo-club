<?php

namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\People\PhotoSync;
use Rondo\REST\People;
use Tests\Support\RondoTestCase;

class PhotoSyncTest extends RondoTestCase {
	private function queued_person(): int {
		$id         = $this->createPerson(
			[],
			[
				'knvb_id'    => 'TEST123',
				'first_name' => 'Test',
			]
			);
		$attachment = self::factory()->post->create(
			[
				'post_type'      => 'attachment',
				'post_mime_type' => 'image/jpeg',
				'post_status'    => 'inherit',
			]
			);
		update_post_meta( $id, '_thumbnail_id', $attachment );
		PhotoSync::queue( $id, $attachment );
		return $id;
	}

	public function test_small_manual_image_is_saved_queued_and_exported_without_upscaling(): void {
		$id = $this->createPerson(
			[],
			[
				'knvb_id'    => 'TEST123',
				'first_name' => 'Test',
			]
			);
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$path  = wp_tempnam( 'photo.png' );
		$image = imagecreatetruecolor( 40, 40 );
		imagepng( $image, $path );
		$request = new \WP_REST_Request( 'POST', '/rondo/v1/people/' . $id . '/photo' );
		$request->set_param( 'source', 'manual' );
		$request->set_file_params(
			[
				'file' => [
					'name'     => 'photo.png',
					'type'     => 'image/png',
					'size'     => filesize( $path ),
					'tmp_name' => $path,
					'error'    => 0,
				],
			]
			);
		$server   = $this->bootRestControllers( [ People::class ] );
		$response = $server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'pending', get_post_meta( $id, PhotoSync::META, true )['state'] );
		$attachment_id = (int) get_post_thumbnail_id( $id );
		$this->assertGreaterThan( 0, $attachment_id );
		try {
			if ( PhotoSync::window()['open'] ) {
				$job = PhotoSync::job( $id, true );
				$this->assertIsArray( $job );
				$bytes = base64_decode( $job['file']['base64'] );
				$size  = getimagesizefromstring( $bytes );
				$this->assertSame( [ 40, 40 ], [ $size[0], $size[1] ] );
				$this->assertSame( 'image/jpeg', $size['mime'] );
				$this->assertSame( hash( 'sha256', $bytes ), $job['file']['sha256'] );
				$claim = PhotoSync::transition(
					$id,
					[
						'action'   => 'claim',
						'revision' => $job['revision'],
						'knvb_id'  => 'TEST123',
					]
					);
				$this->assertSame( 'sending', $claim['state'] );
				$this->assertNotEmpty( $claim['claim_token'] );
			} else {
				$this->assertWPError( PhotoSync::job( $id, true ) );
			}
		} finally {
			wp_delete_attachment( $attachment_id, true );
		}
	}

	public function test_dutch_calendar_boundaries_are_inclusive(): void {
		foreach ( [
			'2026-06-30T21:59:59Z' => false,
			'2026-06-30T22:00:00Z' => true,
			'2026-10-31T22:59:59Z' => true,
			'2026-10-31T23:00:00Z' => false,
			'2027-01-01T00:00:00Z' => false,
		] as $date => $expected ) {
			$this->assertSame( $expected, PhotoSync::window( new \DateTimeImmutable( $date ) )['open'], $date );
		}
		$this->assertSame( '2027-07-01', PhotoSync::window( new \DateTimeImmutable( '2026-11-01' ) )['next_start'] );
	}

	public function test_import_cannot_replace_a_manual_photo_or_create_a_second_job(): void {
		$id = $this->queued_person();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$before  = get_post_meta( $id, PhotoSync::META, true );
		$server  = $this->bootRestControllers( [ People::class ] );
		$request = new \WP_REST_Request( 'POST', '/rondo/v1/people/' . $id . '/photo' );
		$request->set_param( 'source', 'sportlink' );
		$response = $server->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['skipped'] );
		$this->assertSame( $before, get_post_meta( $id, PhotoSync::META, true ) );
		$this->assertSame( $before['attachment_id'], (int) get_post_thumbnail_id( $id ) );
	}

	public function test_stale_revision_and_wrong_identity_are_rejected(): void {
		$id     = $this->queued_person();
		$job    = PhotoSync::job( $id );
		$result = PhotoSync::transition(
			$id,
			[
				'action'   => 'claim',
				'revision' => 'stale',
				'knvb_id'  => 'TEST123',
			]
			);
		$this->assertSame( 'rondo_photo_stale', $result->get_error_code() );
		Fields::update_for_post( $id, 'knvb_id', 'OTHER12' );
		$this->assertWPError( PhotoSync::job( $id ) );
		$this->assertSame( $job['revision'], get_post_meta( $id, PhotoSync::META, true )['revision'] );
	}

	public function test_former_members_and_contacts_cannot_be_exported(): void {
		$id = $this->queued_person();
		Fields::update_for_post( $id, 'former_member', true );
		$this->assertWPError( PhotoSync::job( $id, true ) );
		Fields::update_for_post( $id, 'former_member', false );
		Fields::update_for_post( $id, 'person_type', 'contact' );
		$this->assertWPError( PhotoSync::job( $id, true ) );
	}

	public function test_claim_and_completion_cannot_be_replayed(): void {
		$id    = $this->queued_person();
		$job   = PhotoSync::job( $id );
		$input = [
			'action'   => 'claim',
			'revision' => $job['revision'],
			'knvb_id'  => 'TEST123',
		];
		// Supply a sending job directly so this test is independent of today's season.
		$job['state']       = 'sending';
		$job['claim_token'] = 'test-claim';
		update_post_meta( $id, PhotoSync::META, $job );
		$this->assertWPError( PhotoSync::transition( $id, $input ) );
		$input['action']      = 'complete';
		$input['claim_token'] = 'wrong';
		$this->assertWPError( PhotoSync::transition( $id, $input ) );
		$input['claim_token'] = 'test-claim';
		$this->assertWPError( PhotoSync::transition( $id, $input ) );
		$input['verified_sha256']      = str_repeat( 'a', 64 );
		$input['sportlink_photo_date'] = '2026-09-12';
		$this->assertSame( 'synced', PhotoSync::transition( $id, $input )['state'] );
		$this->assertWPError( PhotoSync::transition( $id, $input ) );
		$this->assertTrue( PhotoSync::protects_photo( $id ) );
	}

	public function test_job_endpoint_requires_an_administrator(): void {
		$id     = $this->queued_person();
		$server = $this->bootRestControllers( [ People::class ] );
		wp_set_current_user( 0 );
		foreach ( [ 'GET', 'POST' ] as $method ) {
			$response = $server->dispatch( new \WP_REST_Request( $method, '/rondo/v1/people/' . $id . '/photo-sync-job' ) );
			$this->assertSame( 401, $response->get_status() );
		}
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$response = $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/people/' . $id . '/photo-sync-job' ) );
		$this->assertSame( 403, $response->get_status() );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$response = $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/people/' . $id . '/photo-sync-job' ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertArrayNotHasKey( 'claim_token', $response->get_data() );
	}

	public function test_concurrent_upload_lock_fails_closed_and_is_released(): void {
		$id     = $this->queued_person();
		$result = PhotoSync::locked( $id, static fn() => PhotoSync::locked( $id, static fn() => true ) );
		$this->assertWPError( $result );
		$this->assertTrue( PhotoSync::locked( $id, static fn() => true ) );
	}

	public function test_pending_queue_is_paginated_and_excludes_stale_and_completed_jobs(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$first = $this->queued_person();
		$stale = $this->queued_person();
		$last  = $this->queued_person();
		Fields::update_for_post( $stale, 'former_member', true );
		$this->assertSame( 'pending', get_post_meta( $first, PhotoSync::STATE_META, true ) );
		$page = PhotoSync::pending( 1, 2 );
		$this->assertSame( [ $first ], array_column( $page['jobs'], 'person_id' ) );
		$this->assertSame( 2, $page['next_page'] );
		$this->assertSame( [ $last ], array_column( PhotoSync::pending( 2, 2 )['jobs'], 'person_id' ) );
		$job                = get_post_meta( $first, PhotoSync::META, true );
		$job['state']       = 'sending';
		$job['claim_token'] = 'queue-test';
		update_post_meta( $first, PhotoSync::META, $job );
		PhotoSync::transition(
			$first,
			[
				'action'               => 'complete',
				'revision'             => $job['revision'],
				'knvb_id'              => $job['knvb_id'],
				'claim_token'          => 'queue-test',
				'verified_sha256'      => str_repeat( 'a', 64 ),
				'sportlink_photo_date' => '2026-09-12',
			]
			);
		$this->assertSame( 'synced', get_post_meta( $first, PhotoSync::STATE_META, true ) );
		$this->assertSame( [ $last ], array_column( PhotoSync::pending()['jobs'], 'person_id' ) );
	}

	public function test_queue_endpoint_requires_admin_and_does_not_export_files(): void {
		$this->queued_person();
		$server = $this->bootRestControllers( [ People::class ] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$this->assertSame( 403, $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/photo-sync-jobs' ) )->get_status() );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$response = $server->dispatch( new \WP_REST_Request( 'GET', '/rondo/v1/photo-sync-jobs' ) );
		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $response->get_data()['jobs'] );
		$this->assertArrayNotHasKey( 'claim_token', $response->get_data()['jobs'][0] );
		$this->assertArrayNotHasKey( 'file', $response->get_data()['jobs'][0] );
	}
}
