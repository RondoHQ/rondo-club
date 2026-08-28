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
 * Age-based contributie requires a Sportlink playing signal.
 */
class FeePlayingEligibilityTest extends RondoTestCase {

	private function calculator( array $recreant_team_ids = [] ): FeeCalculator {
		$settings = new MembershipFeeSettings();
		$settings->save_categories_for_season(
			[
				'junior'   => [
					'label'                 => 'Junior',
					'amount'                => 237,
					'age_classes'           => [ 'Onder 19' ],
					'is_youth'              => true,
					'sort_order'            => 10,
					'matching_teams'        => [],
					'matching_werkfuncties' => [],
				],
				'recreant' => [
					'label'                 => 'Recreant',
					'amount'                => 67,
					'age_classes'           => [],
					'is_youth'              => false,
					'sort_order'            => 20,
					'matching_teams'        => $recreant_team_ids,
					'matching_werkfuncties' => [],
				],
				'donateur' => [
					'label'                 => 'Donateur',
					'amount'                => 57,
					'age_classes'           => [],
					'is_youth'              => false,
					'sort_order'            => 30,
					'matching_teams'        => [],
					'matching_werkfuncties' => [ 'Donateur' ],
				],
			],
			SeasonKey::current()
		);

		$resolver = new FeeCategoryResolver(
			static function ( string $season ) use ( $settings ): array {
				return $settings->get_categories_for_season( $season );
			}
		);

		return new FeeCalculator(
			$resolver,
			$this->createMock( FamilyGroupingService::class ),
			$settings,
			new PersonFeeContext()
		);
	}

	public function test_non_playing_youth_with_only_a_staff_role_is_excluded(): void {
		$team      = $this->createOrganization( [ 'post_title' => 'Onder 19' ] );
		$person_id = $this->createPerson(
			[],
			[
				'leeftijdsgroep' => 'Onder 19',
				'spelactiviteit' => null,
				'work_history'   => [
					[
						'team_id'    => $team,
						'job_title'  => 'Assistent-trainer/coach',
						'is_current' => true,
					],
				],
			]
		);

		$this->assertNull( $this->calculator()->calculate_fee( $person_id ) );
	}

	public function test_game_activity_keeps_age_based_youth_fee_without_a_team(): void {
		$person_id = $this->createPerson(
			[],
			[
				'leeftijdsgroep' => 'Onder 19',
				'spelactiviteit' => 'Veld - Algemeen',
			]
		);

		$fee = $this->calculator()->calculate_fee( $person_id );

		$this->assertSame( 'junior', $fee['category'] );
		$this->assertSame( 237, $fee['base_fee'] );
	}

	public function test_current_player_team_keeps_youth_fee_without_game_activity(): void {
		$team      = $this->createOrganization( [ 'post_title' => 'Onder 19' ] );
		$person_id = $this->createPerson(
			[],
			[
				'leeftijdsgroep' => 'Onder 19',
				'spelactiviteit' => null,
				'work_history'   => [
					[
						'team_id'    => $team,
						'job_title'  => 'Teamspeler',
						'is_current' => true,
					],
				],
			]
		);

		$this->assertSame( 'junior', $this->calculator()->calculate_fee( $person_id )['category'] );
	}

	public function test_recreant_team_remains_eligible_without_game_activity(): void {
		$team      = $this->createOrganization( [ 'post_title' => 'Walking Football' ] );
		$person_id = $this->createPerson(
			[],
			[
				'leeftijdsgroep' => 'Senioren',
				'spelactiviteit' => null,
				'work_history'   => [
					[
						'team_id'    => $team,
						'job_title'  => 'Zaterdag recreanten',
						'is_current' => true,
					],
				],
			]
		);

		$fee = $this->calculator( [ $team ] )->calculate_fee( $person_id );

		$this->assertSame( 'recreant', $fee['category'] );
		$this->assertSame( 67, $fee['base_fee'] );
	}

	public function test_function_based_donateur_remains_eligible_without_playing_signal(): void {
		$person_id = $this->createPerson(
			[],
			[
				'spelactiviteit' => null,
				'work_history'   => [
					[
						'job_title'  => 'Donateur',
						'is_current' => true,
					],
				],
			]
		);

		$fee = $this->calculator()->calculate_fee( $person_id );

		$this->assertSame( 'donateur', $fee['category'] );
		$this->assertSame( 57, $fee['base_fee'] );
	}

	public function test_game_activity_change_invalidates_fee_and_family_state(): void {
		$person_id = $this->createPerson();

		$fee_cache = $this->createMock( FeeCache::class );
		$fee_cache->expects( $this->once() )
			->method( 'clear_fee_cache' )
			->with( $person_id );

		$family_grouping = $this->createMock( FamilyGroupingService::class );
		$family_grouping->expects( $this->once() )
			->method( 'get_family_key' )
			->with( $person_id )
			->willReturn( null );

		$reflection  = new \ReflectionClass( FeeCacheInvalidator::class );
		$invalidator = $reflection->newInstanceWithoutConstructor();

		$cache_property = $reflection->getProperty( 'fee_cache' );
		$cache_property->setAccessible( true );
		$cache_property->setValue( $invalidator, $fee_cache );
		$family_property = $reflection->getProperty( 'family_grouping' );
		$family_property->setAccessible( true );
		$family_property->setValue( $invalidator, $family_grouping );

		$invalidator->invalidate_native_field( $person_id, 'spelactiviteit', null, 'Veld - Algemeen', [] );
	}
}
