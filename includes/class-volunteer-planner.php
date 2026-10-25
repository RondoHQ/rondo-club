<?php
/** Weekly, reviewable proposals. Drafts never change shifts or mail members. */
namespace Rondo\Volunteer;

use Rondo\Core\AccessControl;
use Rondo\Core\PostTitle;
use Rondo\Fields\Fields;
use Rondo\Notifications\EmailTemplate;
use Rondo\REST\MemberShifts;
final class VolunteerPlanner {
	public const TYPE     = 'rondo_shift_plan';
	public const HOOK     = 'rondo_volunteer_planner';
	public const REPAIR   = 'rondo_volunteer_planner_repair';
	public const REMINDER = 'rondo_volunteer_planner_reminders';
	public const EMAIL    = 'rondo_volunteer_planner_email';
	private const LOCK    = 'rondo_volunteer_planner_lock';
	public function __construct() {
		add_action( 'init', [ $this, 'schedule' ] );
		add_action( self::HOOK, [ $this, 'run' ] );
		add_action( self::REPAIR, [ $this, 'repair' ] );
		add_action( self::REMINDER, [ $this, 'remind' ] );
		add_action( 'rondo_shift_member_cancelled', [ $this, 'on_cancel' ], 10, 2 );
	}
	public static function register(): void {
		register_post_type(
		self::TYPE,
		[
			'label'        => 'Indelingsvoorstellen',
			'public'       => false,
			'show_ui'      => false,
			'show_in_rest' => false,
			'supports'     => [ 'title' ],
			'capabilities' => array_fill_keys( [ 'read', 'read_post', 'edit_post', 'delete_post', 'edit_posts', 'create_posts', 'publish_posts', 'read_private_posts', 'delete_posts', 'edit_others_posts' ], 'do_not_allow' ),
			'map_meta_cap' => false,
		]
		);
	}
	public static function email(): string {
		return sanitize_email( (string) get_option( self::EMAIL, WeekendVolunteerMail::overview_email() ) );
	}
	public static function now(): \DateTimeImmutable {
		return new \DateTimeImmutable( 'now', wp_timezone() );
	}
	public static function error( string $message, int $code = 409 ): \WP_Error {
		return new \WP_Error( 'volunteer_plan_invalid', $message, [ 'status' => $code ] );
	}
	public static function next_run( \DateTimeImmutable $now ): \DateTimeImmutable {
		$next = $now->modify( 'wednesday this week' )->setTime( 9, 0 );
		return $next > $now ? $next : $next->modify( '+1 week' );
	}
	public function schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( self::next_run( self::now() )->getTimestamp(), self::HOOK );
		}
		if ( ! wp_next_scheduled( self::REMINDER ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::REMINDER );
		}
	}
	public function run(): void {
		try {
			$this->generate();
		}
		finally {
			$this->schedule();
		}
	}
	public function repair(): void {
		$this->generate( null, true );
	}
	public function on_cancel( int $shift, int $person ): void {
		if ( ! wp_next_scheduled( self::REPAIR ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::REPAIR );
		}
	}
	public static function plans( bool $open = false ): array {
		$args = [
			'post_type'        => self::TYPE,
			'post_status'      => 'private',
			'numberposts'      => -1,
			'orderby'          => 'ID',
			'order'            => 'DESC',
			'suppress_filters' => true,
		];
		if ( $open ) {
			$args['meta_query'] = [
				[
					'key'     => '_planner_phase',
					'value'   => [ 'draft', 'partial' ],
					'compare' => 'IN',
				],
			];
		}
		return get_posts( $args );
	}
	public static function data( int $id ) {
		if ( get_post_type( $id ) !== self::TYPE || get_post_status( $id ) !== 'private' ) {
			return self::error( 'Voorstel niet gevonden.', 404 );
		}
		$data = get_post_meta( $id, '_planner_data', true );
		return is_array( $data ) ? $data : self::error( 'Voorstel kon niet worden gelezen.', 500 );
	}
	private static function save( int $id, array $data ): void {
		update_post_meta( $id, '_planner_data', $data );
		update_post_meta( $id, '_planner_phase', $data['phase'] );
		if ( get_post_meta( $id, '_planner_data', true ) !== $data ) {
			throw new \RuntimeException( 'Indelingsvoorstel kon niet worden opgeslagen.' );
		}
	}
	public static function snapshot( int $shift ): string {
		return hash( 'sha256', wp_json_encode( array_map( static fn( $key ) => get_post_meta( $shift, $key, true ), [ 'start_datetime', 'end_datetime', 'dienst_type_id', 'capacity', 'iva_waived' ] ) ) );
	}
	private function locked( callable $callback ) {
		wp_cache_delete( self::LOCK, 'options' );
		$lock = get_option( self::LOCK, [] );
		if ( ! empty( $lock['expires'] ) && $lock['expires'] < time() ) {
			delete_option( self::LOCK );
		}
		$token = wp_generate_uuid4();
		if ( ! add_option(
		self::LOCK,
		[
			'token'   => $token,
			'expires' => time() + 15 * MINUTE_IN_SECONDS,
		],
		'',
		false
		) ) {
			return self::error( 'Er wordt al een voorstel verwerkt. Probeer het zo opnieuw.' );
		}
		try {
			return $callback();
		}
		finally {
			wp_cache_delete( self::LOCK, 'options' );
			$current = get_option( self::LOCK, [] );
			if ( ( $current['token'] ?? '' ) === $token ) {
				delete_option( self::LOCK );
			}
		}
	}
	private static function refresh(): void {
		VolunteerEligibilityService::invalidate_cache();
		VolunteerObligationCalculator::invalidate_cache();
	}
	/** Unfilled finite-capacity duties, through Sunday four calendar weeks ahead. */
	public function generate( ?\DateTimeImmutable $now = null, bool $force = false ) {
		$now = $now ?? self::now();
		return $this->locked(
		function () use ( $now, $force ) {
			self::refresh();
			$week = $now->format( 'o-W' );
			if ( ! $force ) {
				foreach ( self::plans() as $post ) {
					if ( get_post_meta( $post->ID, '_planner_week', true ) === $week ) {
						return $post->ID;
					}
				}
			}
			$until    = $now->modify( 'monday this week' )->setTime( 0, 0 )->modify( '+5 weeks' );
			$contexts = [];
			$shifts   = get_posts(
			[
				'post_type'        => 'dienst_shift',
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'suppress_filters' => true,
				'meta_query'       => [
					[
						'key'     => 'start_datetime',
						'value'   => [ $now->format( 'Y-m-d H:i:s' ), $until->modify( '-1 second' )->format( 'Y-m-d H:i:s' ) ],
						'compare' => 'BETWEEN',
						'type'    => 'DATETIME',
					],
				],
			]
			);
			// Scarce qualifications first; within each class, urgent dates first.
			usort( $shifts, static fn( $a, $b ) => (int) (bool) get_post_meta( (int) get_post_meta( $b->ID, 'dienst_type_id', true ), 'iva_required', true ) <=> (int) (bool) get_post_meta( (int) get_post_meta( $a->ID, 'dienst_type_id', true ), 'iva_required', true ) ?: strcmp( (string) get_post_meta( $a->ID, 'start_datetime', true ), (string) get_post_meta( $b->ID, 'start_datetime', true ) ) );
			$rows      = [];
			$shift_ids = [];
			foreach ( $shifts as $shift ) {
				$id   = $shift->ID;
				$span = PlannerAvailability::interval( $id );
				$type = (int) get_post_meta( $id, 'dienst_type_id', true );
				if ( ! $span || ! in_array( get_post_meta( $id, 'status', true ), [ 'open','vol' ], true ) || get_post_type( $type ) !== 'dienst_type' || get_post_status( $type ) !== 'publish' ) {
					continue;
				}
				$season = \Rondo\Fees\SeasonKey::current( $span[0]->format( 'Y-m-d' ) );
				if ( ! isset( $contexts[ $season ] ) ) {
					$contexts[ $season ] = new PlannerContext( $now, 0, null, $season );
					foreach ( $rows as $prior ) {
						if ( $prior['person_id'] ) {
							$contexts[ $season ]->reserve( $prior, PlannerAvailability::interval( (int) $prior['shift_id'] ) );
						}
					}
				}
				$context     = $contexts[ $season ];
				$shift_ids[] = $id;
				$capacity    = (int) get_post_meta( $id, 'capacity', true );
				$needed      = $capacity > 0 ? max( 0, $capacity - count( ShiftAssignments::person_ids( $id ) ) - ( $context->slots[ $id ] ?? 0 ) ) : 1;
				for ( $slot = 0; $slot < $needed; ++$slot ) {
						$target      = $type;
						$options     = $capacity > 0 ? $context->candidates( $id ) : [];
						$diagnostics = $context->reasons;
					if ( ! $options && $capacity > 0 && ! ShiftAssignments::person_ids( $id ) && get_post_meta( $type, 'iva_required', true ) ) {
						$luik = $this->luik_type();
						if ( $luik ) {
									$options = $context->candidates( $id, $luik );
							if ( $options ) {
								$target = $luik;
							}
						}
					}
					$chosen = $options[0] ?? null;
					$row    = [
						'shift_id'    => $id,
						'person_id'   => $chosen['person_id'] ?? 0,
						'unit'        => $chosen['unit'] ?? '',
						'target_type' => $target,
						'snapshot'    => self::snapshot( $id ),
						'state'       => 'pending',
						'issue'       => $chosen ? '' : ( $capacity > 0 ? 'Geen geschikte kandidaat met een open verplichting en bereikbaar e-mailadres.' : 'Onbegrensde capaciteit: stel eerst het aantal benodigde plekken in.' ),
						'diagnostics' => $chosen ? [] : $diagnostics,
					];
					$rows[] = $row;
					if ( $chosen ) {
						foreach ( $contexts as $ledger ) {
							$ledger->reserve( $row, $span );
						}
					}
				}
			}
			$id = wp_insert_post(
			[
				'post_type'   => self::TYPE,
				'post_status' => 'private',
				'post_title'  => 'Indelingsvoorstel ' . $now->format( 'd-m-Y H:i' ),
			],
			true
			);
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			$data = [
				'revision'   => 1,
				'phase'      => $rows ? 'draft' : 'published',
				'created_at' => $now->format( DATE_ATOM ),
				'from'       => $now->format( 'Y-m-d' ),
				'until'      => $until->modify( '-1 day' )->format( 'Y-m-d' ),
				'rows'       => $rows,
				'shift_ids'  => $shift_ids,
				'notices'    => [],
			];
			update_post_meta( $id, '_planner_week', $week );
			self::save( $id, $data );
			if ( $rows ) {
				$this->notice( $id, 'created' );
			}
			return $id;
		}
		);
	}
	private function luik_type(): int {
		$matches = [];
		foreach ( get_posts(
		[
			'post_type'        => 'dienst_type',
			'post_status'      => 'publish',
			'numberposts'      => -1,
			'suppress_filters' => true,
		]
		) as $type ) {
			if ( str_contains( mb_strtolower( $type->post_title ), 'luik' ) && ! get_post_meta( $type->ID, 'iva_required', true ) ) {
				$matches[] = $type->ID;
			}
		}
		return count( $matches ) === 1 ? $matches[0] : 0;
	}
	public function candidates( int $id, int $index ) {
		$data = self::data( $id );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$row = $data['rows'][ $index ] ?? null;
		if ( ! $row || $row['state'] !== 'pending' ) {
			return self::error( 'Deze voorstelregel kan niet worden aangepast.' );
		}
		self::refresh();
		$context = new PlannerContext( self::now(), 0, [ $id,$index ], \Rondo\Fees\SeasonKey::current( substr( (string) get_post_meta( $row['shift_id'], 'start_datetime', true ), 0, 10 ) ) );
		return array_map( static fn( $option )=>array_intersect_key( $option, array_flip( [ 'person_id','name','unit','remaining' ] ) ), $context->candidates( (int) $row['shift_id'], (int) $row['target_type'] ) );
	}
	public function edit( int $id, int $revision, int $index, int $person ) {
		return $this->locked(
		function () use ( $id, $revision, $index, $person ) {
			$data = self::data( $id );
			if ( is_wp_error( $data ) ) {
				return $data;
			}
			if ( $data['revision'] !== $revision || ! in_array( $data['phase'], [ 'draft','partial' ], true ) ) {
				return self::error( 'Het voorstel is gewijzigd. Ververs eerst.' );
			}
			$options = $this->candidates( $id, $index );
			if ( is_wp_error( $options ) ) {
				return $options;
			}
			$chosen = null;
			foreach ( $options as $option ) {
				if ( $option['person_id'] === $person ) {
					$chosen = $option;
					break;
				}
			}
			if ( $person > 0 && ! $chosen ) {
					return self::error( 'Deze persoon kan deze dienst momenteel niet vervullen.' );
			}
			$data['rows'][ $index ]['person_id'] = $person;
			$data['rows'][ $index ]['unit']      = $chosen['unit'] ?? '';
			$data['rows'][ $index ]['issue']     = $person ? '' : 'Handmatig open gelaten';
			++$data['revision'];
			self::save( $id, $data );
			return true;
		}
		);
	}
	public function publish( int $id, int $revision, bool $conversions ) {
		return $this->locked(
		function () use ( $id, $revision, $conversions ) {
			$data = self::data( $id );
			if ( is_wp_error( $data ) ) {
				return $data;
			}
			if ( $data['phase'] === 'published' ) {
				return true;
			}
			if ( $data['revision'] !== $revision || ! in_array( $data['phase'], [ 'draft','partial' ], true ) ) {
				return self::error( 'Het voorstel is gewijzigd. Ververs eerst.' );
			}
			foreach ( $data['rows'] as $row ) {
				if ( $row['state'] === 'pending' && $row['person_id'] > 0 && $row['target_type'] !== (int) get_post_meta( $row['shift_id'], 'dienst_type_id', true ) && ! $conversions ) {
					return self::error( 'Geef expliciet akkoord voor de omzetting van bar naar luik.' );
				}
			}
			$data['phase'] = 'partial';
			self::save( $id, $data );
			$controller  = new MemberShifts();
			$processed   = 0;
			$cursor      = (int) ( $data['publish_cursor'] ?? 0 );
			$next_cursor = 0;
			foreach ( $data['rows'] as $index => $row ) {
				if ( $index < $cursor ) {
					continue;
				}
				$row = $data['rows'][ $index ];
				if ( ! in_array( $row['state'], [ 'pending','publishing' ], true ) || ! $row['person_id'] ) {
					continue;
				}
				if ( $processed >= 10 ) {
					$next_cursor = $index;
					break;
				}
				++$processed;
				$shift  = (int) $row['shift_id'];
				$person = (int) $row['person_id'];
				if ( $row['state'] === 'publishing' && in_array( $person, ShiftAssignments::person_ids( $shift ), true ) && (int) get_post_meta( $shift, '_shift_plan_id_' . $person, true ) === $id ) {
						$resumed = $controller->resume_planned_assignee( $shift, $person, $id );
					if ( is_wp_error( $resumed ) ) {
						$data['rows'][ $index ]['issue'] = $resumed->get_error_message();
						self::save( $id, $data );
						continue;
					}
					$data['rows'][ $index ]['state'] = 'published';
					$data['rows'][ $index ]['issue'] = '';
					self::save( $id, $data );
					continue;
				}
				$data['rows'][ $index ]['state'] = 'publishing';
				self::save( $id, $data );
				$result = $controller->add_planned_assignee(
				$shift,
				$person,
				(int) $row['target_type'],
				$row['snapshot'],
				function () use ( $id, $row, $shift, $person ) {
					if ( get_post_type( $shift ) !== 'dienst_shift' || get_post_status( $shift ) !== 'publish' || ! in_array( get_post_meta( $shift, 'status', true ), [ 'open','vol' ], true ) ) {
						return self::error( 'De dienst is niet meer beschikbaar.' );
					}
					$span = PlannerAvailability::interval( $shift );
					if ( ! $span || $span[0] <= self::now() ) {
						return self::error( 'De dienst is al begonnen.' );
					}
					self::refresh();
					$context = new PlannerContext( self::now(), $id, null, \Rondo\Fees\SeasonKey::current( $span[0]->format( 'Y-m-d' ) ) );
					foreach ( $context->candidates( $shift, (int) $row['target_type'] ) as $option ) {
						if ( $option['person_id'] === $person && $option['unit'] === $row['unit'] ) {
							return true;
						}
					}
					return self::error( 'Verplichting, certificaten of beschikbaarheid zijn gewijzigd. Kies opnieuw een kandidaat.' );
				},
				$id
					);
				if ( is_wp_error( $result ) ) {
					$data['rows'][ $index ]['state'] = 'pending';
					$data['rows'][ $index ]['issue'] = $result->get_error_message();
				}
				else {
					$data['rows'][ $index ]['state'] = 'published';
					$data['rows'][ $index ]['issue'] = '';
					foreach ( $data['rows'] as &$other ) {
						if ( $other['shift_id'] === $shift && $other['state'] === 'pending' ) {
							$other['snapshot'] = self::snapshot( $shift );
						}
					}
					unset( $other );
				}
				self::save( $id, $data );
			}
			$data['publish_cursor'] = $next_cursor;
			$data['publish_more']   = $next_cursor > 0;
			$data['phase']          = array_filter( $data['rows'], static fn( $row )=>$row['state'] !== 'published' ) ? 'partial' : 'published';
			++$data['revision'];
			$data['published_by'] = get_current_user_id();
			self::save( $id, $data );
			return true;
		}
		);
	}
	public function discard( int $id, int $revision ) {
		return $this->locked(
		function () use ( $id, $revision ) {
			$data = self::data( $id );
			if ( is_wp_error( $data ) ) {
				return $data;
			}
			if ( $data['revision'] !== $revision ) {
				return self::error( 'Het voorstel is gewijzigd. Ververs eerst.' );
			}
			$data['phase'] = 'closed';
			++$data['revision'];
			self::save( $id, $data );
			return true;
		}
		);
	}
	/** Durable notice reservations avoid duplicate coordinator messages after timeouts. */
	public function notice( int $id, string $kind ): void {
		$data = self::data( $id );
		if ( is_wp_error( $data ) || isset( $data['notices'][ $kind ] ) ) {
			return;
		}
		$email = self::email();
		if ( ! $email ) {
			return;
		}
		$data['notices'][ $kind ] = 'reserved';
		self::save( $id, $data );
		$url     = home_url( '/vrijwilligers/voorstellen?plan=' . $id );
		$subject = $kind === 'reminder' ? 'Herinnering: indelingsvoorstel wacht op akkoord' : 'Indelingsvoorstel vrijwilligers staat klaar';
		$html    = EmailTemplate::render(
		[
			'heading'   => $subject,
			'body_html' => '<p>Er staat een indelingsvoorstel klaar voor ' . $data['from'] . ' t/m ' . $data['until'] . '. Controleer de kandidaten, pas de indeling aan en publiceer na akkoord. Open plekken en ontbrekende beschikbaarheidsgegevens staan in het voorstel.</p>',
			'cta_url'   => $url,
			'cta_label' => 'Bekijk het voorstel',
		]
		);
		try {
			$sent                     = wp_mail( $email, $subject, $html, [ 'Content-Type: text/html; charset=UTF-8','X-Rondo-Email-Tag: volunteer-planning' ] );
			$data['notices'][ $kind ] = $sent ? 'accepted' : 'failed';
		}
		catch ( \Throwable $e ) {
			$data['notices'][ $kind ] = 'uncertain';
		}
		self::save( $id, $data );
	}
	public function remind(): void {
		$this->locked(
		function () {
			foreach ( self::plans( true ) as $plan ) {
				$data = self::data( $plan->ID );
				if ( ! is_wp_error( $data ) && strtotime( $data['created_at'] ) <= time() - 2 * DAY_IN_SECONDS ) {
					$this->notice( $plan->ID, 'reminder' );
				}
			}
			return true;
		}
		);
	}
	/** All identity-bearing reads remain within the manager's existing record access. */
	public function view( int $id ) {
		$data = self::data( $id );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$rows = [];
		foreach ( $data['rows'] as $index => $row ) {
			$person = (int) $row['person_id'];
			if ( $person && ! AccessControl::can_view_person( $person ) ) {
				return self::error( 'Geen toegang tot alle personen in dit voorstel.', 403 );
			}
			$shift    = (int) $row['shift_id'];
			$original = (int) get_post_meta( $shift, 'dienst_type_id', true );
			$rows[]   = [
				'index'        => $index,
				'shift_id'     => $shift,
				'title'        => PostTitle::plain( $original ),
				'target_title' => PostTitle::plain( (int) $row['target_type'] ),
				'conversion'   => $original !== $row['target_type'],
				'start'        => get_post_meta( $shift, 'start_datetime', true ),
				'end'          => get_post_meta( $shift, 'end_datetime', true ),
				'person_id'    => $person,
				'person_name'  => $person ? PostTitle::plain( $person ) : '',
				'state'        => $row['state'],
				'issue'        => $row['issue'],
				'diagnostics'  => $row['diagnostics'] ?? [],
			];
		}
		$coverage = [
			'places'    => 0,
			'filled'    => 0,
			'unlimited' => 0,
		];
		foreach ( $data['shift_ids'] as $shift ) {
			if ( ! in_array( get_post_meta( $shift, 'status', true ), [ 'open','vol' ], true ) ) {
				continue;
			}
			$capacity = (int) get_post_meta( $shift, 'capacity', true );
			if ( $capacity <= 0 ) {
				++$coverage['unlimited'];
				continue;
			}
			$coverage['places'] += $capacity;
			$coverage['filled'] += min( $capacity, count( ShiftAssignments::person_ids( $shift ) ) );
		}
		return [
			'id'                 => $id,
			'revision'           => $data['revision'],
			'phase'              => $data['phase'],
			'created_at'         => $data['created_at'],
			'from'               => $data['from'],
			'until'              => $data['until'],
			'rows'               => $rows,
			'coverage'           => $coverage,
			'notices'            => $data['notices'],
			'notification_email' => self::email(),
			'publish_more'       => $data['publish_more'] ?? false,
		];
	}
}
