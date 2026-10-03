<?php
namespace Tests\Wpunit;

use Rondo\Feedback\FeedbackScreenshot;
use Rondo\REST\Feedback;
use Tests\Support\RondoTestCase;

class FeedbackScreenshotTest extends RondoTestCase {
	private array $paths = [];

	protected function set_up(): void {
		parent::set_up();
		$this->bootRestControllers( [ Feedback::class ] );
	}

	protected function tear_down(): void {
		foreach ( $this->paths as $path ) {
			if ( is_file( $path ) ) {
				unlink( $path );
			}
		}
		parent::tear_down();
	}

	private function image(): array {
		$path          = tempnam( sys_get_temp_dir(), 'feedback-image-' );
		$this->paths[] = $path;
		file_put_contents( $path, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRusAAAAASUVORK5CYII=' ) );
		return [
			'name'     => 'screenshot.png',
			'tmp_name' => $path,
			'error'    => UPLOAD_ERR_OK,
		];
	}

	public function test_validates_real_bytes_and_rejects_oversized_or_fake_images(): void {
		$file   = $this->image();
		$result = FeedbackScreenshot::validate( $file );
		$this->assertSame( 'image/png', $result['type'] );
		$this->assertStringEndsWith( '.png', $result['file'] );
		file_put_contents( $file['tmp_name'], '<svg xmlns="http://www.w3.org/2000/svg"></svg>' );
		$this->assertSame( 'feedback_screenshot_type', FeedbackScreenshot::validate( $file )->get_error_code() );
		file_put_contents( $file['tmp_name'], str_repeat( 'x', 5 * MB_IN_BYTES + 1 ) );
		clearstatcache();
		$this->assertSame( 'feedback_screenshot_size', FeedbackScreenshot::validate( $file )->get_error_code() );
		$file['error'] = UPLOAD_ERR_INI_SIZE;
		$this->assertSame( 'feedback_screenshot_upload', FeedbackScreenshot::validate( $file )->get_error_code() );
		$this->assertSame( '', FeedbackScreenshot::path( [ 'file' => '../wp-config.php' ] ) );
	}

	public function test_invalid_upload_does_not_create_feedback_or_send_email(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$request = new \WP_REST_Request( 'POST', '/rondo/v1/feedback' );
		$request->set_body_params(
			[
				'title'         => 'Rejected screenshot',
				'feedback_type' => 'bug',
			]
			);
		$request->set_file_params( [ 'screenshot' => [ 'error' => UPLOAD_ERR_PARTIAL ] ] );
		$sent = false;
		add_filter(
			'pre_wp_mail',
			function () use ( &$sent ) {
				$sent = true;
				return true;
			}
			);
		$response = rest_do_request( $request );
		$this->assertSame( 400, $response->get_status() );
		$this->assertFalse( $sent );
		$this->assertEmpty(
			get_posts(
			[
				'post_type' => 'rondo_feedback',
				'title'     => 'Rejected screenshot',
			]
			)
			);
	}

	public function test_replacing_screenshot_removes_old_file_and_changes_response_version(): void {
		$owner = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		wp_set_current_user( $owner );
		$id       = self::factory()->post->create(
			[
				'post_type'   => 'rondo_feedback',
				'post_status' => 'publish',
				'post_author' => $owner,
			]
			);
		$versions = [];
		$paths    = [];
		foreach ( [ 1, 2 ] as $attempt ) {
			$input = $this->image();
			$file  = FeedbackScreenshot::validate( $input );
			$path  = FeedbackScreenshot::path( $file );
			wp_mkdir_p( dirname( $path ) );
			copy( $input['tmp_name'], $path );
			$this->paths[] = $path;
			$paths[]       = $path;
			$this->assertTrue( FeedbackScreenshot::attach( $id, $file ) );
			$data = rest_do_request( new \WP_REST_Request( 'GET', '/rondo/v1/feedback/' . $id ) )->get_data();
			$this->assertTrue( $data['has_screenshot'] );
			$versions[] = $data['screenshot_version'];
		}
		$this->assertNotSame( $versions[0], $versions[1] );
		$this->assertFileDoesNotExist( $paths[0] );
		$this->assertFileExists( $paths[1] );

		// An ordinary edit must preserve the attachment and its preview version.
		$request = new \WP_REST_Request( 'POST', '/rondo/v1/feedback/' . $id );
		$request->set_body_params( [ 'title' => 'Changed title' ] );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $versions[1], $response->get_data()['screenshot_version'] );
		$this->assertFileExists( $paths[1] );

		// Bad screenshots must fail before changing the text or existing file.
		$request->set_body_params( [ 'title' => 'Must not be saved' ] );
		$request->set_file_params( [ 'screenshot' => [ 'error' => UPLOAD_ERR_PARTIAL ] ] );
		$this->assertSame( 400, rest_do_request( $request )->get_status() );
		$this->assertSame( 'Changed title', get_post( $id )->post_title );
		$this->assertFileExists( $paths[1] );
	}

	public function test_feedback_reader_cannot_replace_someone_elses_screenshot(): void {
		$owner  = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$id     = self::factory()->post->create(
			[
				'post_type'   => 'rondo_feedback',
				'post_status' => 'publish',
				'post_author' => $owner,
			]
			);
		$reader = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		get_user_by( 'id', $reader )->add_cap( 'feedback' );
		wp_set_current_user( $reader );
		$this->assertSame( 200, rest_do_request( new \WP_REST_Request( 'GET', '/rondo/v1/feedback/' . $id ) )->get_status() );
		$request = new \WP_REST_Request( 'POST', '/rondo/v1/feedback/' . $id );
		$request->set_file_params( [ 'screenshot' => $this->image() ] );
		$this->assertSame( 403, rest_do_request( $request )->get_status() );
		$this->assertEmpty( get_post_meta( $id, FeedbackScreenshot::META, true ) );
	}

	public function test_private_screenshot_uses_feedback_permissions_and_is_deleted_with_record(): void {
		$owner = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$id    = self::factory()->post->create(
			[
				'post_type'   => 'rondo_feedback',
				'post_status' => 'publish',
				'post_author' => $owner,
			]
			);
		$input = $this->image();
		$file  = FeedbackScreenshot::validate( $input );
		$path  = FeedbackScreenshot::path( $file );
		wp_mkdir_p( dirname( $path ) );
		copy( $input['tmp_name'], $path );
		$this->paths[] = $path;
		update_post_meta( $id, FeedbackScreenshot::META, $file );
		$request = new \WP_REST_Request( 'GET', '/rondo/v1/feedback/' . $id . '/screenshot' );

		wp_set_current_user( 0 );
		$this->assertSame( 401, rest_do_request( $request )->get_status() );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'subscriber' ] ) );
		$this->assertSame( 403, rest_do_request( $request )->get_status() );
		wp_set_current_user( $owner );
		$response = rest_do_request( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertNull( $response->get_data() );
		$this->assertSame( 'private, no-store', $response->get_headers()['Cache-Control'] );
		$detail = rest_do_request( new \WP_REST_Request( 'GET', '/rondo/v1/feedback/' . $id ) )->get_data();
		$this->assertTrue( $detail['has_screenshot'] );
		$this->assertStringNotContainsString( $file['file'], wp_json_encode( $detail ) );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertSame( 200, rest_do_request( $request )->get_status() );
		wp_trash_post( $id );
		$this->assertSame( 404, rest_do_request( $request )->get_status() );
		wp_delete_post( $id, true );
		$this->assertFileDoesNotExist( $path );
	}
}
