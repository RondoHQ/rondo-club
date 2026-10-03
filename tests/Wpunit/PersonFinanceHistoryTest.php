<?php
namespace Tests\Wpunit;

use Rondo\Fields\Fields;
use Rondo\Finance\PersonFinanceHistory;
use Rondo\Finance\CreditNotes;
use Rondo\Finance\FinanceServices;
use Rondo\REST\Fees;
use Rondo\REST\Invoices;
use Tests\Support\RondoTestCase;

class PersonFinanceHistoryTest extends RondoTestCase {
	private int $person;
	private int $invoice;

	protected function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		update_option( 'rondo_finance_active_payment_provider', 'rabobank' );
		FinanceServices::reset();
		$this->person  = $this->createPerson( [ 'post_title' => 'Historisch lid' ], [ 'former_member' => true ] );
		$this->invoice = self::factory()->post->create(
			[
				'post_type'   => 'rondo_invoice',
				'post_status' => 'rondo_paid',
			]
			);
		Fields::update_for_post( $this->invoice, 'person', $this->person );
		Fields::update_for_post( $this->invoice, 'invoice_type', 'membership' );
		Fields::update_for_post( $this->invoice, 'invoice_number', 'TEST-123' );
		Fields::update_for_post( $this->invoice, 'total_amount', 200 );
		Fields::update_for_post(
			$this->invoice,
			'line_items',
			[
				[
					'description' => 'Contributie',
					'amount'      => 200,
				],
			]
			);
		update_post_meta( $this->invoice, '_invoice_season', '2025-2026' );
	}

	protected function tear_down(): void {
		FinanceServices::reset();
		parent::tear_down();
	}

	public function test_history_uses_issued_amount_even_for_excluded_former_member(): void {
		update_post_meta( $this->person, '_exclude_from_contributie', true );
		update_post_meta( $this->person, '_nikki_2025_total', 250 );
		update_post_meta( $this->person, '_nikki_2025_saldo', 0 );
		$before = get_post_meta( $this->person );
		$data   = PersonFinanceHistory::get( $this->person, '2025-2026' );
		$this->assertSame( 200, $data['contribution_total'] );
		$this->assertSame( 200, $data['contribution_paid'] );
		$this->assertSame( 'rondo', $data['source'] );
		$this->assertSame( $before, get_post_meta( $this->person ), 'Historical reads must not calculate or write caches.' );
	}

	public function test_nikki_balance_missing_is_unknown_not_paid(): void {
		update_post_meta( $this->person, '_nikki_2024_total', 180 );
		$data = PersonFinanceHistory::get( $this->person, '2024-2025' );
		$this->assertNull( $data['contribution_paid'] );
		$this->assertContains( '2024-2025', $data['seasons'] );
	}

	public function test_draft_does_not_replace_stored_nikki_amounts(): void {
		wp_update_post(
			[
				'ID'          => $this->invoice,
				'post_status' => 'rondo_draft',
			]
			);
		update_post_meta( $this->person, '_nikki_2025_total', 237 );
		update_post_meta( $this->person, '_nikki_2025_saldo', 0 );
		$data = PersonFinanceHistory::get( $this->person, '2025-2026' );
		$this->assertSame( 'nikki', $data['source'] );
		$this->assertEquals( 237, $data['contribution_paid'] );
		$this->assertCount( 1, $data['invoices'] );
	}

	public function test_linked_credit_preserves_unknown_season(): void {
		delete_post_meta( $this->invoice, '_invoice_season' );
		$created = CreditNotes::create(
			[
				'source_invoice_id' => $this->invoice,
				'amount'            => 50,
				'reason'            => 'Correctie',
				'request_id'        => wp_generate_uuid4(),
			]
			);
		$this->assertNotWPError( $created );
		$this->assertNull( PersonFinanceHistory::invoice_season( $created['id'] ) );
		$data = PersonFinanceHistory::get( $this->person, '2025-2026' );
		$this->assertCount( 2, $data['unassigned_invoices'] );
		$this->assertCount( 0, $data['invoices'] );
	}

	public function test_unknown_membership_season_is_not_inferred_from_creation_date(): void {
		delete_post_meta( $this->invoice, '_invoice_season' );
		$data = PersonFinanceHistory::get( $this->person, '2025-2026' );
		$this->assertCount( 0, $data['invoices'] );
		$this->assertCount( 1, $data['unassigned_invoices'] );
		$this->assertNull( $data['contribution_total'] );
	}

	public function test_credit_replay_and_reservation_prevent_double_credit(): void {
		$payload = [
			'source_invoice_id' => $this->invoice,
			'amount'            => 125,
			'reason'            => 'Correctie',
			'request_id'        => wp_generate_uuid4(),
		];
		$first   = CreditNotes::create( $payload );
		$this->assertNotWPError( $first );
		$this->assertSame( $first, CreditNotes::create( $payload ) );
		$this->assertSame( -125.0, (float) Fields::get_for_post( $first['id'], 'total_amount' ) );
		$this->assertSame( 'rondo_draft', get_post_status( $first['id'] ) );
		$this->assertSame( $this->invoice, (int) get_post_meta( $first['id'], '_credit_source_invoice_id', true ) );
		$this->assertWPError( CreditNotes::create( array_merge( $payload, [ 'amount' => 50 ] ) ) );
		$this->assertWPError( CreditNotes::create( array_merge( $payload, [ 'request_id' => wp_generate_uuid4() ] ) ) );
		$this->assertCount( 1, CreditNotes::linked( $this->invoice ) );
	}

	public function test_linked_credit_cannot_be_edited_through_generic_endpoint(): void {
		$result = CreditNotes::create(
			[
				'source_invoice_id' => $this->invoice,
				'amount'            => 50,
				'reason'            => 'Correctie',
				'request_id'        => wp_generate_uuid4(),
			]
			);
		$this->assertNotWPError( $result );
		$request = new \WP_REST_Request( 'POST' );
		$request->set_param( 'id', $result['id'] );
		$this->assertWPError( ( new Invoices() )->update_draft_invoice( $request ) );
		$request->set_param( 'description', 'Aanpassing' );
		$request->set_param( 'amount', -10 );
		$this->assertSame( 'credit_locked', ( new Invoices() )->add_draft_line_item( $request )->get_error_code() );
	}

	public function test_permissions_read_only_can_read_history_but_not_create_credit(): void {
		$server = $this->bootRestControllers( [ Fees::class, Invoices::class ] );
		$user   = $this->createRondoUser();
		( new \WP_User( $user ) )->add_cap( 'financieel_read' );
		wp_set_current_user( $user );
		$request = new \WP_REST_Request( 'GET', '/rondo/v1/fees/person/' . $this->person . '/history' );
		$request->set_param( 'season', '2025-2026' );
		$this->assertSame( 200, $server->dispatch( $request )->get_status() );
		$this->assertSame( 403, $server->dispatch( new \WP_REST_Request( 'POST', '/rondo/v1/invoices/credits' ) )->get_status() );
		wp_set_current_user( $this->createRondoUser() );
		$this->assertSame( 403, $server->dispatch( $request )->get_status() );
	}
	private function injury_payload(): array {
		return [
			'mode'                 => 'injury',
			'person_id'            => $this->person,
			'season'               => '2025-2026',
			'source_invoice_id'    => $this->invoice,
			'percentage'           => 75,
			'costs'                => 50,
			'injury_start'         => '2025-09-01',
			'injury_end'           => '2026-06-30',
			'conditions_confirmed' => true,
			'request_id'           => wp_generate_uuid4(),
		];
	}

	public function test_injury_calculation_creates_negative_credit_and_separate_cost_line(): void {
		$payload = $this->injury_payload();
		$preview = CreditNotes::preview( $payload );
		$this->assertNotWPError( $preview );
		$this->assertEquals( 100, $preview['amount'] );
		$this->assertEquals( -150, $preview['line_items'][0]['amount'] );
		$this->assertEquals( 50, $preview['line_items'][1]['amount'] );
		$created = CreditNotes::create( $payload );
		$this->assertNotWPError( $created );
		$this->assertSame( true, CreditNotes::validate_draft( $created['id'] ) );
		$this->assertSame( $created, CreditNotes::create( $payload ) );
		$this->assertWPError( CreditNotes::create( array_merge( $payload, [ 'request_id' => wp_generate_uuid4() ] ) ) );
	}

	public function test_injury_rules_reject_ineligible_or_invalid_requests(): void {
		foreach ( [
			[ 'injury_start' => '2026-03-01' ],
			[ 'injury_start' => '2025-06-30' ],
			[ 'injury_start' => '2025-02-30' ],
			[ 'injury_end' => '2026-07-01' ],
			[ 'conditions_confirmed' => false ],
			[ 'percentage' => 60 ],
			[ 'costs' => 49.99 ],
			[ 'costs' => 150 ],
			[ 'source_invoice_id' => 0 ],
			[ 'season' => '2025-2027' ],
		] as $invalid ) {
			$this->assertWPError( CreditNotes::preview( array_merge( $this->injury_payload(), $invalid ) ), wp_json_encode( $invalid ) );
		}
		wp_update_post(
			[
				'ID'          => $this->invoice,
				'post_status' => 'rondo_sent',
			]
			);
		$this->assertWPError( CreditNotes::preview( $this->injury_payload() ) );
	}

	public function test_nikki_can_supply_a_paid_season_without_creating_an_original_invoice(): void {
		update_post_meta( $this->person, '_nikki_2024_total', 237 );
		update_post_meta( $this->person, '_nikki_2024_saldo', 0 );
		$payload = array_merge(
			$this->injury_payload(),
			[
				'season'            => '2024-2025',
				'source_invoice_id' => 0,
				'injury_start'      => '2024-08-01',
				'injury_end'        => '2025-06-30',
			]
			);
		$preview = CreditNotes::preview( $payload );
		$this->assertNotWPError( $preview );
		$this->assertSame( 127.75, $preview['amount'] );
		$created = CreditNotes::create( $payload );
		$this->assertNotWPError( $created );
		$this->assertSame( true, CreditNotes::validate_draft( $created['id'] ) );
		$this->assertSame( '2024-2025', get_post_meta( $created['id'], '_invoice_season', true ) );
		$this->assertSame( 'Nikki 2024-2025', get_post_meta( $created['id'], '_credit_calculation', true )['source_invoice_number'] );
		update_post_meta( $this->person, '_nikki_2024_saldo', 20 );
		$this->assertWPError( CreditNotes::validate_draft( $created['id'] ) );
	}

	public function test_send_rechecks_source_payment_and_reserved_amount(): void {
		$created = CreditNotes::create( $this->injury_payload() );
		$this->assertNotWPError( $created );
		wp_update_post(
			[
				'ID'          => $this->invoice,
				'post_status' => 'rondo_sent',
			]
			);
		$this->assertWPError( CreditNotes::validate_draft( $created['id'] ) );
		$request = new \WP_REST_Request( 'POST' );
		$request->set_param( 'id', $created['id'] );
		$this->assertWPError( ( new Invoices() )->send_invoice( $request ) );
		$this->assertSame( 'rondo_draft', get_post_status( $created['id'] ) );
	}

	public function test_cancelled_credit_cannot_reenter_reserved_budget(): void {
		$created = CreditNotes::create( $this->injury_payload() );
		$this->assertNotWPError( $created );
		wp_update_post(
			[
				'ID'          => $created['id'],
				'post_status' => 'rondo_cancelled',
			]
			);
		$request = new \WP_REST_Request( 'POST' );
		$request->set_param( 'id', $created['id'] );
		$request->set_param( 'status', 'sent' );
		$this->assertSame( 'credit_cancelled', ( new Invoices() )->update_invoice_status( $request )->get_error_code() );
	}
	public function test_credit_never_creates_payment_links_or_becomes_overdue(): void {
		$created = CreditNotes::create( $this->injury_payload() );
		$this->assertNotWPError( $created );
		$id = $created['id'];
		$this->assertSame( 'credit_no_payment_link', ( new \Rondo\Finance\MolliePayment() )->create_payment_link( $id )->get_error_code() );
		$this->assertSame( 'credit_no_payment_link', ( new \Rondo\Finance\RabobankPayment() )->create_payment_request( $id )->get_error_code() );
		wp_update_post(
			[
				'ID'          => $id,
				'post_status' => 'rondo_sent',
			]
			);
		Fields::update_for_post( $id, 'due_date', '20250101' );
		( new Invoices() )->get_invoice_list( new \WP_REST_Request( 'GET' ) );
		$this->assertSame( 'rondo_sent', get_post_status( $id ) );
	}

	public function test_changed_payment_amount_requires_a_new_preview(): void {
		$payload                    = $this->injury_payload();
		$payload['expected_amount'] = 99;
		$this->assertWPError( CreditNotes::create( $payload ) );
		$this->assertCount( 0, CreditNotes::linked( $this->invoice ) );
	}
}
