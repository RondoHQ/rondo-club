<?php

namespace Tests\Wpunit;

use Rondo\Volunteer\ShiftScheduler;
use Tests\Support\RondoTestCase;

/**
 * Tests volunteer shift scheduler hooks.
 */
class ShiftSchedulerTest extends RondoTestCase {

	/**
	 * A no-show must no longer trigger an automatic fine invoice.
	 */
	public function test_no_show_has_no_invoice_generator_hook(): void {
		$this->assertFalse( method_exists( ShiftScheduler::class, 'on_no_show_marked' ) );
		$this->assertFalse( class_exists( '\\Rondo\\Volunteer\\VolunteerFineGenerator' ) );
		$this->assertFalse( has_action( 'rondo_volunteer_no_show_marked' ) );
	}
}
