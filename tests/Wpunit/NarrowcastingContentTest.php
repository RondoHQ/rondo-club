<?php

namespace Tests\Wpunit;

use DateTimeImmutable;
use Rondo\Fields\Fields;
use Rondo\Narrowcasting\Content;
use Rondo\REST\Narrowcasting;
use Tests\Support\RondoTestCase;
use WP_REST_Request;

/** Contract tests for Club TV content, playlists and authorization. */
class NarrowcastingContentTest extends RondoTestCase {

	private Content $content;
	private \WP_REST_Server $server;

	protected function set_up(): void {
		parent::set_up();
		$this->content = new Content();
		$this->server  = $this->bootRestControllers( [ Narrowcasting::class ] );
	}

	protected function tear_down(): void {
		delete_option( Content::DEFAULT_OPTION );
		parent::tear_down();
	}

	public function test_content_manager_can_build_a_weighted_scheduled_playlist(): void {
		$this->login_with_capability( 'narrowcasting' );
		$match = $this->content->create_item(
			[
				'title'        => 'Programma',
				'content_type' => 'matches',
			]
			);
		$news  = $this->content->create_item(
			[
				'title'            => 'Nieuws',
				'content_type'     => 'announcement',
				'body'             => 'Welkom',
				'duration_seconds' => 8,
			]
			);

		$playlist = $this->content->create_playlist(
			[
				'title'        => 'Zaterdag',
				'days_of_week' => [ 'sat' ],
				'start_time'   => '08:00',
				'end_time'     => '23:00',
				'items'        => [
					[
						'item_id'          => $news['id'],
						'duration_seconds' => 10,
						'weight'           => 2,
					],
					[
						'item_id'          => $match['id'],
						'duration_seconds' => 0,
						'weight'           => 1,
					],
				],
			]
		);

		$this->assertFalse( is_wp_error( $playlist ) );
		$manifest = $this->content->resolve_manifest( 0, $playlist['id'], new DateTimeImmutable( '2026-08-15T12:00:00+02:00' ), true );
		$this->assertCount( 3, $manifest['scenes'] );
		$this->assertSame( [ 'item-' . $news['id'], 'item-' . $match['id'], 'item-' . $news['id'] ], array_column( $manifest['scenes'], 'id' ) );
		$this->assertSame( 10, $manifest['scenes'][0]['duration_seconds'] );

		$inactive = $this->content->resolve_manifest( 0, $playlist['id'], new DateTimeImmutable( '2026-08-16T12:00:00+02:00' ), true );
		$this->assertSame( 'builtin-matches', $inactive['scenes'][0]['id'] );
		$this->assertSame( 'Niet gepland op deze dag.', $inactive['excluded'][0]['reason'] );
	}

	public function test_active_override_replaces_a_display_playlist(): void {
		$this->login_with_capability( 'narrowcasting' );
		$normal   = $this->content->create_item(
			[
				'title'        => 'Normaal',
				'content_type' => 'announcement',
			]
			);
		$playlist = $this->content->create_playlist(
			[
				'title' => 'Basis',
				'items' => [
					[
						'item_id' => $normal['id'],
						'weight'  => 1,
					],
				],
			]
			);
		$this->content->set_default_playlist( $playlist['id'] );
		$display_id = self::factory()->post->create(
			[
				'post_type'   => 'rondo_display',
				'post_status' => 'publish',
				'post_title'  => 'Kantine',
			]
			);
		$override   = $this->content->create_item(
			[
				'title'                => 'Noodbericht',
				'content_type'         => 'announcement',
				'body'                 => 'Wedstrijden afgelast',
				'is_override'          => true,
				'priority'             => 100,
				'override_display_ids' => [ $display_id ],
			]
		);

		$manifest = $this->content->resolve_manifest( $display_id );
		$this->assertTrue( $manifest['override'] );
		$this->assertCount( 1, $manifest['scenes'] );
		$this->assertSame( 'item-' . $override['id'], $manifest['scenes'][0]['id'] );
	}

	public function test_sponsor_manager_is_limited_to_safe_sponsor_items(): void {
		$sponsor_id = $this->createPerson(
			[ 'post_title' => 'Veilige Sponsor' ],
			[
				'is_sponsor' => true,
				'email_1'    => 'private@example.test',
			]
		);
		$this->login_with_capability( 'sponsorbeheer' );

		$forbidden = $this->json_request(
			'POST',
			'/rondo/v1/narrowcasting/items',
			[
				'title'        => 'Bericht',
				'content_type' => 'announcement',
			]
			);
		$this->assertSame( 403, $forbidden->get_status() );

		$created = $this->json_request(
			'POST',
			'/rondo/v1/narrowcasting/items',
			[
				'title'             => 'Sponsorbeeld',
				'content_type'      => 'sponsor',
				'sponsor_person_id' => $sponsor_id,
			]
		);
		$this->assertSame( 200, $created->get_status() );
		$this->assertSame( 'sponsor', $created->get_data()['fields']['content_type'] );

		$playlist = $this->json_request( 'GET', '/rondo/v1/narrowcasting/playlists' );
		$this->assertSame( 403, $playlist->get_status() );

		$sponsor_playlist = $this->content->create_playlist(
			[
				'title' => 'Sponsors',
				'items' => [
					[
						'item_id' => $created->get_data()['id'],
						'weight'  => 1,
					],
				],
			]
		);
		$manifest         = $this->content->resolve_manifest( 0, $sponsor_playlist['id'] );
		$this->assertSame( 'Veilige Sponsor', $manifest['scenes'][0]['sponsor']['name'] );
		$this->assertStringNotContainsString( 'private@example.test', wp_json_encode( $manifest ) );
	}

	public function test_paired_player_receives_assigned_safe_manifest(): void {
		$this->login_with_capability( 'narrowcasting' );
		$item     = $this->content->create_item(
			[
				'title'        => 'Welkom',
				'content_type' => 'announcement',
				'body'         => 'Fijne wedstrijd',
			]
			);
		$playlist = $this->content->create_playlist(
			[
				'title' => 'Kantine',
				'items' => [
					[
						'item_id' => $item['id'],
						'weight'  => 1,
					],
				],
			]
			);
		$token    = 'player-test-token-with-enough-entropy-1234567890';
		$display  = self::factory()->post->create(
			[
				'post_type'   => 'rondo_display',
				'post_status' => 'publish',
				'post_title'  => 'Kantine',
			]
			);
		Fields::update_many_for_post(
			$display,
			[
				'pairing_status'       => 'paired',
				'device_secret_hash'   => hash_hmac( 'sha256', $token, wp_salt( 'auth' ) ),
				'assigned_playlist_id' => $playlist['id'],
			]
		);
		wp_set_current_user( 0 );

		$response = $this->json_request( 'GET', '/rondo/v1/narrowcasting/devices/me/playlist', [], [ 'X-Rondo-Device-Token' => $token ] );
		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $playlist['id'], $response->get_data()['playlist_id'] );
		$this->assertSame( 'Fijne wedstrijd', $response->get_data()['scenes'][0]['body'] );
		$this->assertArrayNotHasKey( 'fields', $response->get_data()['scenes'][0] );
	}

	private function login_with_capability( string $capability ): int {
		$user_id = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$user    = get_user_by( 'id', $user_id );
		$user->add_cap( $capability );
		wp_set_current_user( $user_id );
		return $user_id;
	}

	private function json_request( string $method, string $route, array $body = [], array $headers = [] ): \WP_REST_Response {
		$request = new WP_REST_Request( $method, $route );
		if ( $body ) {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( (string) wp_json_encode( $body ) );
		}
		foreach ( $headers as $name => $value ) {
			$request->set_header( $name, $value );
		}
		return $this->server->dispatch( $request );
	}
}
