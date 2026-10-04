<?php
namespace Tests\Wpunit;

use Tests\Support\RondoTestCase;
use Rondo\Fields\Fields;
use Rondo\Fields\RestFields;
use Rondo\Finance\PersonBankAccount;
use Rondo\REST\BankAccounts;

class PersonBankAccountTest extends RondoTestCase {
	private int $person;
	private int $user;
	protected function set_up(): void {
		parent::set_up();
		$this->user   = self::factory()->user->create( [ 'role' => 'subscriber' ] );
		$this->person = self::factory()->post->create(
			[
				'post_type'   => 'person',
				'post_status' => 'publish',
			]
			);
		update_user_meta( $this->user, 'rondo_linked_person_id', $this->person );
		wp_set_current_user( $this->user );
		$this->bootRestControllers( [ BankAccounts::class ] );
	}
	private function request( array $data ) {
		$request = new \WP_REST_Request( 'PATCH', '/rondo/v1/user/profile-bank-account' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
		return rest_do_request( $request );
	}
	public function test_own_fields_normalized_and_private_audit_contains_no_values(): void {
		$result = $this->request(
			[
				'iban'                => 'nl91 abna 0417 1643 00',
				'bank_account_holder' => 'Andere rekeninghouder',
			]
			);
		$this->assertSame( 200, $result->get_status(), wp_json_encode( $result->get_data() ) );
		$this->assertSame( 'NL91ABNA0417164300', Fields::get_for_post( $this->person, 'iban' ) );
		$this->assertSame( 'Andere rekeninghouder', get_post_meta( $this->person, 'bank_account_holder', true ) );
		$this->assertStringNotContainsString( 'NL91', wp_json_encode( get_post_meta( $this->person, '_rondo_bank_audit', false ) ) );
		$this->assertCount( 1, get_post_meta( $this->person, '_rondo_bank_audit', false ) );
		$this->assertArrayNotHasKey( 'iban', RestFields::for_post( 'person', $this->person ) );
	}
	public function test_invalid_or_injected_fields_do_not_write(): void {
		$this->assertSame(
			400,
			$this->request(
			[
				'iban'                => 'NL00ABNA0417164300',
				'bank_account_holder' => 'Nobody',
			]
			)->get_status()
			);
		$this->assertEmpty( Fields::get_for_post( $this->person, 'bank_account_holder' ) );
		$this->assertSame(
			400,
			$this->request(
			[
				'person_id' => 999,
				'iban'      => 'NL91ABNA0417164300',
			]
			)->get_status()
			);
	}
	public function test_other_person_and_former_member_denied(): void {
		$other = self::factory()->post->create(
			[
				'post_type'   => 'person',
				'post_status' => 'publish',
			]
			);
		$this->assertFalse( PersonBankAccount::can_read( $other ) );
		$this->assertTrue( is_wp_error( Fields::update_many_for_post( $other, [ 'iban' => 'NL91ABNA0417164300' ] ) ) );
		update_post_meta( $this->person, 'former_member', '1' );
		$this->assertSame( 403, $this->request( [ 'iban' => null ] )->get_status() );
	}
	public function test_null_clears_both_fields(): void {
		$this->request(
			[
				'iban'                => 'NL91ABNA0417164300',
				'bank_account_holder' => 'Test',
			]
			);
		$result = $this->request(
			[
				'iban'                => null,
				'bank_account_holder' => null,
			]
			);
		$this->assertSame( 200, $result->get_status() );
		$this->assertNull( $result->get_data()['iban'] );
	}
}
