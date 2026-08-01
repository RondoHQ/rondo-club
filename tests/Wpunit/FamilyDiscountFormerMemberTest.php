<?php

namespace Tests\Wpunit;

use Rondo\Fees\FamilyGroupingService;
use Rondo\Fees\FeeCache;
use Rondo\Fees\FeeCacheInvalidator;
use Rondo\Fees\FeeCalculator;
use Rondo\Fees\FeeCategoryResolver;
use Rondo\Fees\MembershipFeeSettings;
use Rondo\Fees\PersonFeeContext;
use Rondo\Fees\SeasonKey;
use Tests\Support\RondoTestCase;

/**
 * Former members may owe a season fee, but never count toward family discounts.
 */
class FamilyDiscountFormerMemberTest extends RondoTestCase {

	public function test_family_key_can_be_derived_from_pre_update_addresses(): void {
		$service = ( new \ReflectionClass( FamilyGroupingService::class ) )->newInstanceWithoutConstructor();

		$this->assertSame(
			'1234AB-12A',
			$service->get_family_key_from_addresses(
				[
					[
						'postal_code'           => '1234 ab',
						'house_number'          => '12',
						'house_number_addition' => 'A',
					],
				]
			)
		);
		$this->assertNull( $service->get_family_key_from_addresses( null ) );
	}

	private function configure_youth_fees(): MembershipFeeSettings {
		$settings = new MembershipFeeSettings();
		$settings->save_categories_for_season(
			[
				'pupil' => [
					'label'                 => 'Pupil',
					'amount'                => 100,
					'age_classes'           => [ 'Onder 10' ],
					'is_youth'              => true,
					'sort_order'            => 10,
					'matching_teams'        => [],
					'matching_werkfuncties' => [],
				],
			],
			SeasonKey::current()
		);

		return $settings;
	}

	private function create_youth_member( bool $former = false ): int {
		return $this->createPerson(
			[],
			[
				'leeftijdsgroep' => 'Onder 10',
				'former_member'  => $former,
				'addresses'      => [
					[
						'address_label'         => 'Thuis',
						'street_name'           => 'Teststraat',
						'house_number'          => '12',
						'house_number_addition' => '',
						'postal_code'           => '1234 AB',
						'city'                  => 'Teststad',
					],
				],
			]
		);
	}

	private function family_grouping( MembershipFeeSettings $settings ): FamilyGroupingService {
		return new FamilyGroupingService(
			new PersonFeeContext(),
			$settings,
			static function ( int $person_id ): array {
				return [
					'category'  => 'pupil',
					'base_fee'  => 100,
					'person_id' => $person_id,
				];
			}
		);
	}

	public function test_family_groups_only_contain_active_youth_members_and_clear_stale_meta(): void {
		$settings = $this->configure_youth_fees();
		$active_1 = $this->create_youth_member();
		$active_2 = $this->create_youth_member();
		$former   = $this->create_youth_member( true );
		$grouping = $this->family_grouping( $settings );

		update_post_meta( $former, '_family_discount_rate', '0.5' );
		update_post_meta( $former, '_family_discount_position', '3' );

		$groups = $grouping->build_family_groups();
		$grouping->recalculate_family_positions_for_person( $active_1 );

		$this->assertSame( [ $active_1, $active_2 ], $groups['families']['1234AB-12'] );
		$this->assertArrayNotHasKey( $former, $groups['person_data'] );
		$this->assertSame( '0', get_post_meta( $former, '_family_discount_rate', true ) );
		$this->assertSame( '', get_post_meta( $former, '_family_discount_position', true ) );
		$this->assertSame( '1', get_post_meta( $active_1, '_family_discount_position', true ) );
		$this->assertSame( '2', get_post_meta( $active_2, '_family_discount_position', true ) );
	}

	public function test_former_member_never_receives_a_stored_family_discount(): void {
		$settings = $this->configure_youth_fees();
		$former   = $this->create_youth_member( true );

		update_post_meta( $former, '_family_discount_rate', '0.5' );
		update_post_meta( $former, '_family_discount_position', '3' );

		$resolver        = new FeeCategoryResolver(
			static function ( string $season ) use ( $settings ): array {
				return $settings->get_categories_for_season( $season );
			}
		);
		$family_grouping = $this->createMock( FamilyGroupingService::class );
		$family_grouping->expects( $this->never() )->method( 'get_family_key' );

		$calculator = new FeeCalculator( $resolver, $family_grouping, $settings, new PersonFeeContext() );
		$fee        = $calculator->calculate_fee_with_family_discount( $former );

		$this->assertSame( 0.0, $fee['family_discount_rate'] );
		$this->assertSame( 0, $fee['family_discount_amount'] );
		$this->assertSame( 100, $fee['final_fee'] );
		$this->assertNull( $fee['family_position'] );
		$this->assertNull( $fee['family_key'] );
	}

	public function test_former_member_change_invalidates_and_recalculates_the_household(): void {
		$current = $this->create_youth_member();
		$sibling = $this->create_youth_member();

		foreach ( [ $current, $sibling ] as $person_id ) {
			update_post_meta( $person_id, '_family_discount_rate', '0.25' );
			update_post_meta( $person_id, '_family_discount_position', '2' );
		}

		$cleared   = [];
		$fee_cache = $this->createMock( FeeCache::class );
		$fee_cache->expects( $this->exactly( 2 ) )
			->method( 'clear_fee_cache' )
			->willReturnCallback(
				static function ( int $person_id ) use ( &$cleared ): bool {
					$cleared[] = $person_id;
					return true;
				}
			);

		$family_grouping = $this->createMock( FamilyGroupingService::class );
		$family_grouping->expects( $this->once() )->method( 'get_family_key' )->with( $current )->willReturn( '1234AB-12' );
		$family_grouping->expects( $this->once() )
			->method( 'build_family_groups' )
			->willReturn(
				[
					'families'    => [ '1234AB-12' => [ $sibling ] ],
					'person_data' => [],
				]
			);
		$family_grouping->expects( $this->once() )
			->method( 'recalculate_family_positions_for_person' )
			->with( $sibling );

		$reflection  = new \ReflectionClass( FeeCacheInvalidator::class );
		$invalidator = $reflection->newInstanceWithoutConstructor();

		$cache_property = $reflection->getProperty( 'fee_cache' );
		$cache_property->setAccessible( true );
		$cache_property->setValue( $invalidator, $fee_cache );
		$family_property = $reflection->getProperty( 'family_grouping' );
		$family_property->setAccessible( true );
		$family_property->setValue( $invalidator, $family_grouping );

		$returned = $invalidator->invalidate_family_membership_cache( true, $current, [] );
		sort( $cleared );
		$expected = [ $current, $sibling ];
		sort( $expected );

		$this->assertTrue( $returned );
		$this->assertSame( $expected, $cleared );
		$this->assertSame( '', get_post_meta( $current, '_family_discount_rate', true ) );
		$this->assertSame( '', get_post_meta( $sibling, '_family_discount_rate', true ) );
	}
}
