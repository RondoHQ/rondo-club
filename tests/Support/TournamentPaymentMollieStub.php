<?php

namespace Tests\Support;

use Rondo\Fields\Fields;
use Rondo\Finance\MolliePayment;

/** Test double for persistent tournament payment links. */
class TournamentPaymentMollieStub extends MolliePayment {
	public int $create_calls  = 0;
	public int $archive_calls = 0;

	public function create_payment_link( int $invoice_id ) {
		++$this->create_calls;
		$link = 'https://pay.example.test/tournament';
		Fields::update_for_post( $invoice_id, 'payment_link', $link );
		update_post_meta( $invoice_id, '_mollie_payment_link_id', 'pl_tournament_test' );
		return $link;
	}

	public function archive_payment_links( int $invoice_id ): void {
		++$this->archive_calls;
	}
}
