<?php

namespace Tests\Wpunit;

use Rondo\Core\UserRoles;
use Rondo\REST\Communication;
use Rondo\REST\UserSettings;
use Tests\Support\RondoTestCase;

class CommunicationTest extends RondoTestCase {
	private string $role;
	private int $user_id;

	protected function set_up(): void {
		parent::set_up();
		delete_option( 'rondo_communication_channels' );
		$this->role    = UserRoles::add_custom_role( 'Communication test role' );
		$this->user_id = $this->createRondoUser( [ 'role' => $this->role ] );
		wp_set_current_user( $this->user_id );
		$this->bootRestControllers( [ Communication::class, UserSettings::class ] );
	}

	protected function tear_down(): void {
		UserRoles::remove_custom_role( $this->role );
		delete_option( 'rondo_communication_channels' );
		parent::tear_down();
	}

	private function request( string $route, string $method = 'GET', array $body = [] ): \WP_REST_Response {
		$request = new \WP_REST_Request( $method, $route );
		if ( $body ) {
			$request->set_header( 'content-type', 'application/json' );
			$request->set_body( wp_json_encode( $body ) );
		}
		return rest_do_request( $request );
	}

	private function grant_access(): void {
		get_role( $this->role )->add_cap( 'communicatie' );
		UserRoles::sync_role_capabilities( $this->role );
		wp_set_current_user( 0 );
		wp_set_current_user( $this->user_id );
	}

	public function test_communication_requires_dedicated_capability(): void {
		$this->assertSame( 403, $this->request( '/rondo/v1/communications' )->get_status() );
		$this->assertFalse( $this->request( '/rondo/v1/user/me' )->get_data()['can_access_communicatie'] );

		$this->grant_access();

		$this->assertSame( 200, $this->request( '/rondo/v1/communications' )->get_status() );
		$this->assertTrue( $this->request( '/rondo/v1/user/me' )->get_data()['can_access_communicatie'] );
	}

	public function test_monthly_series_creates_separate_occurrences_without_duplicates(): void {
		$this->grant_access();
		$start = wp_date( 'Y-m-15' );
		$end   = wp_date( 'Y-m-15', strtotime( '+2 months' ) );
		$body  = [
			'title'        => 'Maandnieuws',
			'channel'      => 'newsletter',
			'status'       => 'concept',
			'recurrence'   => 'monthly',
			'start_date'   => $start,
			'planned_date' => $start,
			'end_date'     => $end,
		];

		$response = $this->request( '/rondo/v1/communications', 'POST', $body );
		$this->assertSame( 201, $response->get_status() );
		$this->assertNotEmpty( $response->get_data()['series_id'] );
		$list = $this->request( '/rondo/v1/communications' )->get_data()['items'];
		$this->assertCount( 3, $list );

		$again = $this->request( '/rondo/v1/communications' )->get_data()['items'];
		$this->assertCount( 3, $again );
		$this->assertCount( 3, array_unique( array_column( $again, 'planned_date' ) ) );
	}

	public function test_completion_requires_preparation_fields_and_can_be_reopened(): void {
		$this->grant_access();
		$created = $this->request(
			'/rondo/v1/communications',
			'POST',
			[
				'title'      => 'Los idee',
				'channel'    => 'website',
				'status'     => 'concept',
				'recurrence' => 'none',
			]
		);
		$this->assertSame( 201, $created->get_status() );
		$id = $created->get_data()['id'];
		$this->assertSame( 400, $this->request( "/rondo/v1/communications/{$id}/action", 'POST', [ 'action' => 'complete' ] )->get_status() );

		$updated = $this->request(
			"/rondo/v1/communications/{$id}",
			'PUT',
			[
				'title'        => 'Los idee',
				'channel'      => 'website',
				'status'       => 'ready',
				'recurrence'   => 'none',
				'description'  => 'Publiceer dit bericht.',
				'planned_date' => wp_date( 'Y-m-d' ),
				'audience'     => 'Alle leden',
				'assignee_id'  => $this->user_id,
			]
		);
		$this->assertSame( 200, $updated->get_status() );
		$completed = $this->request(
			"/rondo/v1/communications/{$id}/action",
			'POST',
			[
				'action'      => 'complete',
				'actual_date' => wp_date( 'Y-m-d' ),
			]
			);
		$this->assertSame( 'sent', $completed->get_data()['status'] );
		$reopened = $this->request( "/rondo/v1/communications/{$id}/action", 'POST', [ 'action' => 'reopen' ] );
		$this->assertSame( 'ready', $reopened->get_data()['status'] );
	}
	private function create_multi( array $extra = [] ): array {
		$this->grant_access();
		$response = $this->request(
			'/rondo/v1/communications',
			'POST',
			array_merge(
			[
				'title'        => 'Vrijwilliger van de maand',
				'channel_ids'  => [ 'newsletter', 'website' ],
				'status'       => 'ready',
				'description'  => 'In het zonnetje zetten.',
				'audience'     => 'Alle leden',
				'planned_date' => wp_date( 'Y-m-d' ),
				'assignee_id'  => $this->user_id,
			],
			$extra
			)
			);
		$this->assertSame( 201, $response->get_status(), wp_json_encode( $response->get_data() ) );
		return $response->get_data();
	}

	public function test_each_channel_has_independent_completion_and_reopening(): void {
		$item = $this->create_multi();
		$id   = $item['id'];
		$this->assertCount( 2, $item['channels'] );
		$this->assertSame( 400, $this->request( "/rondo/v1/communications/{$id}/action", 'POST', [ 'action' => 'complete' ] )->get_status() );
		$first = $this->request(
			"/rondo/v1/communications/{$id}/action",
			'POST',
			[
				'action'        => 'complete',
				'channel_id'    => 'website',
				'published_url' => 'https://example.org/vrijwilliger',
			]
			)->get_data();
		$this->assertSame( 'ready', $first['status'] );
		$this->assertEmpty( $first['actual_date'] );
		$this->assertEmpty( $first['channels'][0]['actual_date'] );
		$this->assertSame( wp_date( 'Y-m-d' ), $first['channels'][1]['actual_date'] );
		$this->assertSame( $this->user_id, $first['channels'][1]['completed_by'] );
		$this->assertSame( 'https://example.org/vrijwilliger', $first['channels'][1]['published_url'] );
		$all = $this->request(
			"/rondo/v1/communications/{$id}/action",
			'POST',
			[
				'action'     => 'complete',
				'channel_id' => 'newsletter',
			]
			)->get_data();
		$this->assertSame( 'sent', $all['status'] );
		$this->assertSame( wp_date( 'Y-m-d' ), $all['actual_date'] );
		$again = $this->request(
			"/rondo/v1/communications/{$id}/action",
			'POST',
			[
				'action'     => 'complete',
				'channel_id' => 'website',
			]
			)->get_data();
		$this->assertSame( $all['channels'], $again['channels'] );
		$corrected = $this->request(
			"/rondo/v1/communications/{$id}/action",
			'POST',
			[
				'action'        => 'complete',
				'channel_id'    => 'website',
				'actual_date'   => '2026-01-01',
				'published_url' => 'https://example.org/corrected',
			]
			)->get_data();
		$this->assertSame( '2026-01-01', $corrected['channels'][1]['actual_date'] );
		$this->assertSame( 'https://example.org/corrected', $corrected['channels'][1]['published_url'] );
		$this->assertSame( wp_date( 'Y-m-d' ), $corrected['actual_date'] );

		$open = $this->request(
			"/rondo/v1/communications/{$id}/action",
			'POST',
			[
				'action'     => 'reopen',
				'channel_id' => 'website',
			]
			)->get_data();
		$this->assertSame( 'ready', $open['status'] );
		$this->assertEmpty( $open['channels'][1]['actual_date'] );
		$this->assertNotEmpty( $open['channels'][0]['actual_date'] );
	}

	public function test_selection_preserves_completion_and_cleans_native_rows(): void {
		$id    = $this->create_multi()['id'];
		$route = "/rondo/v1/communications/{$id}";
		$this->request(
			"$route/action",
			'POST',
			[
				'action'     => 'complete',
				'channel_id' => 'website',
			]
			);
		$this->assertSame( 400, $this->request( $route, 'PUT', [ 'channel_ids' => [ 'newsletter' ] ] )->get_status() );
		$updated = $this->request(
			$route,
			'PUT',
			[
				'title'       => 'Aangepast',
				'channel_ids' => [ 'website' ],
				'channels'    => [],
				'actual_date' => '',
			]
			)->get_data();
		$this->assertSame( 'sent', $updated['status'] );
		$this->assertNotEmpty( $updated['channels'][0]['actual_date'] );
		$this->assertSame( '1', get_post_meta( $id, 'channels', true ) );
		$this->assertSame( 'website', get_post_meta( $id, 'channels_0_channel_id', true ) );
		$this->assertSame( 'field_comm_channels', get_post_meta( $id, '_channels', true ) );
		$this->assertFalse( metadata_exists( 'post', $id, 'channels_1_channel_id' ) );
		$reopened = $this->request( $route, 'PUT', [ 'channel_ids' => [ 'website', 'newsletter' ] ] )->get_data();
		$this->assertSame( 'ready', $reopened['status'] );
		$this->assertEmpty( $reopened['channels'][1]['actual_date'] );
	}

	public function test_invalid_or_bypassed_completion_does_not_mutate(): void {
		$id    = $this->create_multi()['id'];
		$route = "/rondo/v1/communications/{$id}";
		foreach ( [ [], [ 'no-such-channel' ], [ 'website', 'website' ], 'website', [ [ 'website' ] ] ] as $ids ) {
			$this->assertSame( 400, $this->request( $route, 'PUT', [ 'channel_ids' => $ids ] )->get_status() );
		}
		$this->assertSame( 400, $this->request( $route, 'PUT', [ 'status' => 'sent' ] )->get_status() );
		$this->assertSame(
			400,
			$this->request(
			"$route/action",
			'POST',
			[
				'action'      => 'complete',
				'channel_id'  => 'website',
				'actual_date' => '2099-01-01',
			]
			)->get_status()
			);
		$this->request( "$route/action", 'POST', [ 'action' => 'cancel' ] );
		$this->assertSame(
			400,
			$this->request(
			"$route/action",
			'POST',
			[
				'action'     => 'complete',
				'channel_id' => 'website',
			]
			)->get_status()
			);
	}

	public function test_legacy_sent_records_keep_history_on_first_edit(): void {
		$this->grant_access();
		$id = wp_insert_post(
			[
				'post_type'   => 'rondo_comm_item',
				'post_status' => 'publish',
				'post_title'  => 'Oud bericht',
			]
			);
		foreach ( [
			'channel'       => 'website',
			'status'        => 'sent',
			'actual_date'   => '2026-01-01',
			'description'   => 'Historie',
			'planned_date'  => '2026-01-01',
			'audience'      => 'Leden',
			'assignee_id'   => $this->user_id,
			'published_url' => 'https://example.org/old',
			'completed_by'  => $this->user_id,
		] as $key => $value ) {
			update_post_meta( $id, $key, $value );
		}
		$item = $this->request( "/rondo/v1/communications/{$id}" )->get_data();
		$this->assertSame( [ 'website' ], $item['channel_ids'] );
		$this->assertSame( '2026-01-01', $item['channels'][0]['actual_date'] );
		$this->assertFalse( metadata_exists( 'post', $id, 'channels' ) );
		$updated = $this->request( "/rondo/v1/communications/{$id}", 'PUT', [ 'title' => 'Oud bericht aangepast' ] )->get_data();
		$this->assertSame( $item['channels'], $updated['channels'] );
		$this->assertSame( 'sent', $updated['status'] );
	}

	public function test_configured_channels_are_stable_and_archived_channels_are_retained(): void {
		$before   = \Rondo\Config\ClubConfig::get_communication_channels();
		$result   = \Rondo\Config\ClubConfig::update_communication_channels( array_merge( $before, [ [ 'label' => 'LinkedIn' ] ] ) );
		$linkedin = $result[3]['id'];
		$this->assertNotSame( '', $linkedin );
		$item                = $this->create_multi( [ 'channel_ids' => [ $linkedin, 'website' ] ] );
		$result[3]['label']  = 'LinkedIn club';
		$result[3]['active'] = false;
		\Rondo\Config\ClubConfig::update_communication_channels( $result );
		$id      = $item['id'];
		$current = $this->request( "/rondo/v1/communications/{$id}", 'PUT', [ 'description' => 'Nieuwe tekst' ] )->get_data();
		$this->assertSame( 'LinkedIn club', $current['channels'][0]['label'] );
		$this->assertSame(
			400,
			$this->request(
			'/rondo/v1/communications',
			'POST',
			[
				'title'       => 'Nieuw',
				'channel_ids' => [ $linkedin ],
			]
			)->get_status()
			);
		$this->assertSame(
			200,
			$this->request(
			"/rondo/v1/communications/{$id}/action",
			'POST',
			[
				'action'     => 'complete',
				'channel_id' => $linkedin,
			]
			)->get_status()
			);
		$this->assertWPError( \Rondo\Config\ClubConfig::update_communication_channels( [ [ 'label' => '' ] ] ) );
		$this->assertWPError( \Rondo\Config\ClubConfig::update_communication_channels( [ [ 'label' => 'Website' ], [ 'label' => 'website' ] ] ) );
		$this->assertSame( $result, \Rondo\Config\ClubConfig::get_communication_channels() );
	}

	public function test_recurring_and_duplicated_items_start_with_unchecked_channels(): void {
		$start = wp_date( 'Y-m-01', strtotime( '+1 month' ) );
		$end   = wp_date( 'Y-m-01', strtotime( '+2 months' ) );
		$item  = $this->create_multi(
			[
				'recurrence'   => 'monthly',
				'start_date'   => $start,
				'planned_date' => $start,
				'end_date'     => $end,
			]
			);
		$id    = $item['id'];
		$this->request( "/rondo/v1/communications/{$id}", 'PUT', [ 'status' => 'ready' ] );
		$this->request(
			"/rondo/v1/communications/{$id}/action",
			'POST',
			[
				'action'     => 'complete',
				'channel_id' => 'website',
			]
			);
		$copy = $this->request( "/rondo/v1/communications/{$id}/action", 'POST', [ 'action' => 'duplicate' ] )->get_data();
		$this->assertSame( [ '', '' ], array_column( $copy['channels'], 'actual_date' ) );
		$all   = $this->request( '/rondo/v1/communications' )->get_data()['items'];
		$other = array_values( array_filter( $all, static fn( $row ) => $row['series_id'] === $item['series_id'] && $row['id'] !== $id ) );
		$this->assertCount(
			1,
			$other,
			wp_json_encode(
			[
				'item' => $item,
				'all'  => $all,
			]
			)
			);
		$this->assertSame( [ '', '' ], array_column( $other[0]['channels'], 'actual_date' ) );
		$this->assertSame( [ 'newsletter', 'website' ], $other[0]['channel_ids'] );
	}

	public function test_abilities_enforce_permissions_and_support_creation_and_readback(): void {
		$create = wp_get_ability( 'rondo/create-communication' );
		$this->assertNotNull( $create );
		$this->assertFalse( $create->get_meta_item( 'annotations' )['readonly'] );
		$this->assertTrue( $create->get_meta_item( 'mcp' )['public'] );
		$body = [
			'title'       => 'Vanuit MCP',
			'channel_ids' => [ 'website', 'newsletter' ],
		];
		$this->assertWPError( $create->execute( $body ) );
		$this->grant_access();
		$this->assertWPError( wp_get_ability( 'rondo/add-communication-channel' )->execute( [ 'label' => 'LinkedIn' ] ) );
		$item = $create->execute( $body );
		$this->assertNotWPError( $item );
		$this->assertSame( $body['channel_ids'], $item['channel_ids'] );
		$read = wp_get_ability( 'rondo/get-communication' )->execute( [ 'id' => $item['id'] ] );
		$this->assertSame( $item['channel_ids'], $read['channel_ids'] );
		$list = wp_get_ability( 'rondo/list-communications' )->execute( [ 'search' => 'Vanuit MCP' ] );
		$this->assertSame( 1, $list['total'] );
		$this->assertCount( 3, $list['channels'] );
		$updated = wp_get_ability( 'rondo/update-communication' )->execute(
			[
				'id'      => $item['id'],
				'version' => $read['modified_gmt'],
				'title'   => 'Nieuwe titel',
			]
			);
		$this->assertSame( 'Nieuwe titel', $updated['title'] );
		$this->assertWPError( $create->execute( array_merge( $body, [ 'status' => 'sent' ] ) ) );
		$this->assertWPError( wp_get_ability( 'rondo/get-communication' )->execute( [ 'id' => $this->createPerson() ] ) );
	}

	public function test_mcp_channel_state_and_admin_channel_creation(): void {
		$item    = $this->create_multi();
		$ability = wp_get_ability( 'rondo/set-communication-channel-state' );
		foreach ( $item['channel_ids'] as $channel ) {
			$result = $ability->execute(
				[
					'id'         => $item['id'],
					'channel_id' => $channel,
					'completed'  => true,
				]
				);
			$this->assertNotWPError( $result );
		}
		$this->assertSame( 'sent', $result['status'] );
		$open = $ability->execute(
			[
				'id'         => $item['id'],
				'channel_id' => 'website',
				'completed'  => false,
			]
			);
		$this->assertSame( 'ready', $open['status'] );
		wp_set_current_user( $this->createRondoUser( [ 'role' => 'administrator' ] ) );
		$added = wp_get_ability( 'rondo/add-communication-channel' )->execute( [ 'label' => 'LinkedIn' ] );
		$this->assertNotWPError( $added );
		$this->assertSame( 'LinkedIn', $added['channels'][3]['label'] );
		$this->assertWPError( wp_get_ability( 'rondo/add-communication-channel' )->execute( [ 'label' => 'LinkedIn' ] ) );
	}

	public function test_mcp_read_does_not_expand_series_and_lock_prevents_lost_updates(): void {
		$item = $this->create_multi();
		$id   = $item['id'];
		add_option( 'rondo_comm_edit_' . $id, time(), '', false );
		$this->assertSame(
			409,
			$this->request(
			"/rondo/v1/communications/{$id}/action",
			'POST',
			[
				'action'     => 'complete',
				'channel_id' => 'website',
			]
			)->get_status()
			);
		delete_option( 'rondo_comm_edit_' . $id );
		$series = wp_insert_post(
			[
				'post_type'   => 'rondo_comm_series',
				'post_status' => 'publish',
				'post_title'  => 'Later',
			]
			);
		foreach ( [
			'series_status' => 'active',
			'recurrence'    => 'monthly',
			'start_date'    => wp_date( 'Y-m-d' ),
			'channel'       => 'website',
		] as $key => $value ) {
			update_post_meta( $series, $key, $value );
		}
		$list = wp_get_ability( 'rondo/list-communications' )->execute( [] );
		$this->assertSame( 1, $list['total'] );
	}
	public function test_future_templates_and_ability_series_preserve_channel_selection(): void {
		$this->grant_access();
		$start = wp_date( 'Y-m-01', strtotime( '+1 month' ) );
		$item  = wp_get_ability( 'rondo/create-communication' )->execute(
			[
				'title'       => 'Vrijwilliger via MCP',
				'channel_ids' => [ 'website', 'newsletter' ],
				'recurrence'  => 'monthly',
				'start_date'  => $start,
				'end_date'    => wp_date( 'Y-m-01', strtotime( '+2 months' ) ),
			]
			);
		$this->assertNotWPError( $item );
		$this->assertGreaterThan( 0, $item['series_id'] );
		$updated = $this->request(
			'/rondo/v1/communications/' . $item['id'],
			'PUT',
			[
				'channel_ids'     => [ 'website', 'whatsapp' ],
				'apply_to_future' => true,
			]
			);
		$this->assertSame( 200, $updated->get_status() );
		$all = $this->request( '/rondo/v1/communications' )->get_data()['items'];
		$this->assertCount( 2, $all );
		foreach ( $all as $row ) {
			$this->assertSame( [ 'website', 'whatsapp' ], $row['channel_ids'] );
			$this->assertSame( [ '', '' ], array_column( $row['channels'], 'actual_date' ) );
		}
		$this->assertSame( '2', get_post_meta( $item['series_id'], 'channels', true ) );
		$this->assertSame( 'whatsapp', get_post_meta( $item['series_id'], 'channels_1_channel_id', true ) );
		$this->assertFalse( metadata_exists( 'post', $item['series_id'], 'channels_0_actual_date' ) );
	}

	public function test_channel_configuration_rest_requires_admin_and_omission_archives(): void {
		$this->bootRestControllers( [ \Rondo\REST\Api::class ] );
		$this->grant_access();
		$this->assertSame( 403, $this->request( '/rondo/v1/config', 'POST', [ 'communication_channels' => [ [ 'label' => 'LinkedIn' ] ] ] )->get_status() );
		wp_set_current_user( $this->createRondoUser( [ 'role' => 'administrator' ] ) );
		$saved = $this->request( '/rondo/v1/config', 'POST', [ 'communication_channels' => [ [ 'label' => 'LinkedIn' ] ] ] );
		$this->assertSame( 200, $saved->get_status() );
		$channels = $saved->get_data()['communication_channels'];
		$this->assertCount( 4, $channels );
		$this->assertSame( 'LinkedIn', $channels[0]['label'] );
		$this->assertTrue( $channels[0]['active'] );
		$this->assertFalse( $channels[1]['active'] );
	}
}
