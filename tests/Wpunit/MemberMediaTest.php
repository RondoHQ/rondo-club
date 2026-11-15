<?php
namespace Tests\Wpunit;

use Rondo\Media\MemberMedia;
use Rondo\Media\MemberMediaMigration;
use Rondo\REST\People;
use Tests\Support\RondoTestCase;

class MemberMediaTest extends RondoTestCase {
	private array $attachments = [];
	private array $files       = [];

	protected function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->bootRestControllers( [ People::class ] );
	}

	protected function tear_down(): void {
		foreach ( $this->attachments as $id ) {
			wp_delete_attachment( $id, true );
		}
		foreach ( $this->files as $file ) {
			if ( file_exists( $file ) || is_link( $file ) ) {
				unlink( $file );
			}
		}
		parent::tear_down();
	}

	private function photo( int $parent ): int {
		$uploads = wp_upload_dir();
		$file    = $uploads['path'] . '/synthetic-' . wp_generate_uuid4() . '.png';
		$image   = imagecreatetruecolor( 320, 240 );
		imagepng( $image, $file );
		$id   = wp_insert_attachment(
			[
				'post_title'     => 'Synthetic portrait',
				'post_mime_type' => 'image/png',
				'post_status'    => 'inherit',
				'post_parent'    => $parent,
			],
			$file
			);
		$size = str_replace( '.png', '-150x150.png', $file );
		copy( $file, $size );
		wp_update_attachment_metadata(
			$id,
			[
				'file'   => _wp_relative_upload_path( $file ),
				'width'  => 320,
				'height' => 240,
				'sizes'  => [
					'thumbnail' => [
						'file'      => basename( $size ),
						'width'     => 150,
						'height'    => 150,
						'mime-type' => 'image/png',
					],
				],
			]
			);
		$this->attachments[] = $id;
		$this->files         = array_merge( $this->files, [ $file, $size ] );
		return $id;
	}

	public function test_migration_copies_all_derivatives_removes_old_files_and_is_idempotent(): void {
		$person = $this->createPerson();
		$id     = $this->photo( $person );
		set_post_thumbnail( $person, $id );
		$source = get_attached_file( $id );
		$stale  = str_replace( '.png', '-99x99.png', $source );
		copy( $source, $stale );
		$this->files[] = $stale;
		$manifest      = MemberMediaMigration::inventory( $id );
		$this->assertCount( 3, $manifest['files'] );
		$this->assertTrue( MemberMediaMigration::migrate( $id ) );
		$this->assertTrue( MemberMediaMigration::verify( $id ) );
		$private = get_attached_file( $id );
		$this->assertStringStartsWith( MemberMedia::directory() . '/', $private );
		foreach ( $manifest['files'] as $file ) {
			$this->assertFileDoesNotExist( $file['source'] );
			$this->assertSame( $file['sha256'], hash_file( 'sha256', dirname( $private ) . '/' . $file['name'] ) );
		}
		$this->assertSame( MemberMedia::delivery_url( $id, 'thumbnail' ), get_the_post_thumbnail_url( $person, 'thumbnail' ) );
		$this->assertTrue( MemberMediaMigration::migrate( $id ) );
		$this->assertSame( $private, get_attached_file( $id ) );
		$this->assertFalse( MemberMedia::file( $id, '../../wp-config.php' ) );
	}

	public function test_interrupted_cleanup_resumes_and_changed_sources_are_preserved(): void {
		$id     = $this->photo( $this->createPerson() );
		$source = get_attached_file( $id );
		$this->assertTrue( MemberMediaMigration::migrate( $id ) );
		copy( get_attached_file( $id ), $source );
		$this->assertWPError( MemberMediaMigration::verify( $id ) );
		$this->assertTrue( MemberMediaMigration::migrate( $id ) );
		$this->assertFileDoesNotExist( $source );
		file_put_contents( $source, 'changed fixture' );
		$result = MemberMediaMigration::migrate( $id );
		$this->assertWPError( $result );
		$this->assertSame( 'member_media_changed', $result->get_error_code() );
		$this->assertSame( 'changed fixture', file_get_contents( $source ) );
	}

	public function test_symlinks_and_traversal_metadata_are_rejected_without_removing_sources(): void {
		$id                                     = $this->photo( $this->createPerson() );
		$source                                 = get_attached_file( $id );
		$metadata                               = wp_get_attachment_metadata( $id );
		$metadata['sizes']['thumbnail']['file'] = '../outside.png';
		wp_update_attachment_metadata( $id, $metadata );
		$this->assertWPError( MemberMediaMigration::migrate( $id ) );
		$this->assertFileExists( $source );
		$link = $source . '.link';
		symlink( $source, $link );
		$this->files[] = $link;
		update_attached_file( $id, $link );
		$this->assertWPError( MemberMediaMigration::inventory( $id ) );
	}

	public function test_media_queries_counts_and_direct_reads_obey_person_scope(): void {
		$visible = $this->createPerson();
		$hidden  = $this->createPerson();
		$own     = $this->photo( $visible );
		$other   = $this->photo( $hidden );
		$orphan  = $this->photo( 0 );
		update_post_meta( $hidden, 'photo_gallery', [ $orphan ] );
		$logo = $this->photo( $this->createOrganization() );
		$user = $this->createRondoUser();
		update_user_meta( $user, 'rondo_linked_person_id', $visible );
		wp_set_current_user( $user );
		$access = new MemberMedia( false );
		$this->assertTrue( $access->can_view( $own ) );
		$this->assertFalse( $access->can_view( $other ) );
		$this->assertFalse( $access->can_view( $orphan ) );
		$request = new \WP_REST_Request( 'GET', '/wp/v2/media' );
		$request->set_param( 'include', [ $own, $other, $orphan, $logo ] );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 2, $response->get_headers()['X-WP-Total'] );
		$this->assertEqualsCanonicalizing( [ $own, $logo ], array_column( $response->get_data(), 'id' ) );
		$this->assertSame( 403, rest_do_request( new \WP_REST_Request( 'GET', '/WP/v2/Media/' . $other ) )->get_status() );
		$this->assertSame( 200, rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/media/' . $own ) )->get_status() );
		wp_set_current_user( 0 );
		$this->assertFalse( $access->can_view( $own ) );
		$this->assertTrue( $access->can_view( $logo ) );
		$response = rest_do_request( $request );
		$this->assertSame( [ $logo ], array_column( $response->get_data(), 'id' ) );
	}

	public function test_shared_files_and_missing_private_derivatives_fail_closed(): void {
		$id         = $this->photo( $this->createPerson() );
		$metadata   = wp_get_attachment_metadata( $id );
		$derivative = dirname( get_attached_file( $id ) ) . '/' . $metadata['sizes']['thumbnail']['file'];
		$shared     = wp_insert_attachment(
			[
				'post_mime_type' => 'image/png',
				'post_status'    => 'inherit',
			],
			$derivative
			);
		$this->assertSame( 'member_media_shared_file', MemberMediaMigration::migrate( $id )->get_error_code() );
		$this->assertFileExists( $derivative );
		delete_post_meta( $shared, '_wp_attached_file' );
		wp_delete_attachment( $shared, true );
		$this->assertTrue( MemberMediaMigration::migrate( $id ) );
		unlink( MemberMedia::file( $id, 'thumbnail' ) );
		$this->assertWPError( MemberMediaMigration::verify( $id ) );
		$this->assertWPError( MemberMediaMigration::migrate( $id ) );
	}

	public function test_media_modal_and_mixed_search_do_not_reveal_hidden_photos(): void {
		$id = $this->photo( $this->createPerson() );
		wp_set_current_user( $this->createRondoUser() );
		$this->assertFalse( wp_prepare_attachment_for_js( $id ) );
		$query = new \WP_Query(
			[
				'post_type'        => [ 'post', 'attachment' ],
				'post_status'      => [ 'publish', 'inherit' ],
				'post__in'         => [ $id ],
				'suppress_filters' => false,
			]
			);
		$this->assertSame( 0, $query->found_posts );
		$request = new \WP_REST_Request( 'POST', '/wp/v2/media/' . $id );
		$request->set_param( 'post', 0 );
		$this->assertSame( 403, rest_do_request( $request )->get_status() );
	}

	public function test_new_member_uploads_are_private_and_generic_linking_cannot_bypass_storage(): void {
		$person = $this->createPerson();
		$path   = wp_tempnam( 'synthetic.png' );
		$image  = imagecreatetruecolor( 320, 240 );
		imagepng( $image, $path );
		$request = new \WP_REST_Request( 'POST', '/rondo/v1/people/' . $person . '/photo' );
		$request->set_file_params(
			[
				'file' => [
					'name'     => 'synthetic.png',
					'tmp_name' => $path,
					'type'     => 'image/png',
					'size'     => filesize( $path ),
					'error'    => 0,
				],
			]
			);
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status(), wp_json_encode( $response->get_data() ) );
		$id                  = $response->get_data()['attachment_id'];
		$this->attachments[] = $id;
		$this->assertTrue( MemberMediaMigration::verify( $id ) );
		$this->assertSame( 'Ledenfoto', get_the_title( $id ) );
		$this->assertMatchesRegularExpression( '/^member-[a-f0-9]{32}\.png$/', basename( get_attached_file( $id ) ) );
		$this->assertStringNotContainsString( MemberMedia::directory(), wp_json_encode( rest_do_request( new \WP_REST_Request( 'GET', '/wp/v2/media/' . $id ) )->get_data() ) );
		$this->assertStringNotContainsString( MemberMedia::directory(), wp_upload_dir()['basedir'] );
		$public  = $this->photo( 0 );
		$request = new \WP_REST_Request( 'POST', '/wp/v2/people/' . $person );
		$request->set_param( 'featured_media', $public );
		$this->assertSame( 403, rest_do_request( $request )->get_status() );
		$this->assertSame( $id, get_post_thumbnail_id( $person ) );
	}
}
