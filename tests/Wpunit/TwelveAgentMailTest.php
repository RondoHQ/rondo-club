<?php
namespace Tests\Wpunit;

use Rondo\Twelve\AgentMailClient;
use Tests\Support\RondoTestCase;

class TwelveAgentMailTest extends RondoTestCase {
	public function test_pagination_and_exact_sender_filter(): void {
		$urls = [];
		$mock = static function ( $response, $args, $url ) use ( &$urls ) {
			$urls[] = $url;
			$page   = count( $urls ) === 1 ? [
				'messages'        => [
					[
						'message_id' => 'old',
						'from'       => 'Twelve <noreply@twelve.eu>',
					],
					[
						'message_id' => 'wrong',
						'from'       => 'noreply@twelve.eu.evil.test',
					],
				],
				'next_page_token' => 'next',
			] : [
				'messages' => [
					[
						'message_id' => 'new',
						'from'       => 'noreply@twelve.eu',
					],
				],
			];
			return [
				'response' => [ 'code' => 200 ],
				'body'     => wp_json_encode( $page ),
			];
		};
		add_filter( 'pre_http_request', $mock, 10, 3 );
		try {
			$client = new AgentMailClient(
				[
					'api_key'  => 'test',
					'inbox_id' => 'reports@agentmail.to',
				]
				);
			$this->assertSame( [ 'old', 'new' ], $client->list_report_message_ids() );
			$this->assertStringContainsString( 'page_token=next', $urls[1] );
		} finally {
			remove_filter( 'pre_http_request', $mock, 10 );
		}
	}
}
