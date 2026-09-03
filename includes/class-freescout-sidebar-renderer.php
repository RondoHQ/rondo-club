<?php
/**
 * Script-free FreeScout sidebar markup.
 *
 * @package Rondo\Integrations\FreeScout
 */

namespace Rondo\Integrations\FreeScout;

use Rondo\Core\AccessControl;
use Rondo\Fields\Fields;
use Rondo\Fields\Formatter;
use Rondo\Fields\RestFields;
use Rondo\Passes\MembershipPassService;
use Rondo\People\CommunicationPolicy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Render the approved FreeScout sidebar field policies. */
final class SidebarRenderer {
	private const SUPPORTED_POLICIES = [ 'basis.v1', 'ledenadministratie.v2', 'contributie.v1' ];
	private const FINANCE_POLICIES   = [ 'ledenadministratie.v2', 'contributie.v1' ];

	private const PERSON_FIELDS = [
		'first_name',
		'infix',
		'last_name',
		'former_member',
		'lid_tot',
		'wacht_op_overschrijving',
		'type_lid',
		'knvb_id',
		'work_history',
		'spelactiviteit',
		'birthdate',
		'leeftijdsgroep',
		'lid_sinds',
		'email_1',
		'email_2',
		'mobile_1',
		'mobile_2',
		'telephone_1',
		'telephone_2',
		'addresses',
		'relationships',
		'onboarding_email_lid_sent',
	];

	public function render( int $person_id, string $policy, int $viewer_user_id ): string {
		if ( ! in_array( $policy, self::SUPPORTED_POLICIES, true ) || $viewer_user_id <= 0 || get_current_user_id() !== $viewer_user_id ) {
			return $this->state( 'Deze sidebarweergave wordt niet ondersteund.' );
		}
		$person = get_post( $person_id );
		if ( ! $person || $person->post_type !== 'person' || $person->post_status !== 'publish' ) {
			return $this->state( 'Geen gekoppeld Rondo-profiel gevonden.' );
		}

		$fields      = RestFields::for_post_fields( 'person', $person_id, self::PERSON_FIELDS );
		$name        = trim( implode( ' ', array_filter( [ $fields['first_name'] ?? '', $fields['infix'] ?? '', $fields['last_name'] ?? '' ] ) ) );
		$name        = $name !== '' ? $name : get_the_title( $person_id );
		$is_deceased = CommunicationPolicy::is_deceased( $person_id );
		$badges      = $this->badges( $fields, $is_deceased );
		$teams       = $this->current_teams( (array) ( $fields['work_history'] ?? [] ) );
		$finance     = in_array( $policy, self::FINANCE_POLICIES, true ) ? $this->invoice_rows( $person_id, $viewer_user_id ) : [];
		$member_id   = 'rondo-member-' . $person_id;
		$contact_id  = 'rondo-contact-' . $person_id;
		$process_id  = 'rondo-process-' . $person_id;

		$html  = '<section class="rondo-sidebar" data-rondo-card aria-label="' . esc_attr( 'Rondo-profiel van ' . $name ) . '">';
		$html .= '<div class="rondo-heading"><strong>Rondo</strong><span class="rondo-mailbox-badge">' . esc_html( $this->mailbox_label( $policy ) ) . '</span></div>';
		if ( $finance !== [] ) {
			$html .= '<section class="rondo-alert rondo-alert--finance"><h3>Openstaande contributie</h3>' . $this->row_list( $finance ) . '</section>';
		}
		$html .= '<div class="rondo-tabs" role="tablist" aria-label="Rondo-profielinformatie">';
		$html .= '<button type="button" class="rondo-tab is-active" role="tab" aria-selected="true" aria-controls="' . esc_attr( $member_id ) . '" data-rondo-tab="member">Lid</button>';
		$html .= '<button type="button" class="rondo-tab" role="tab" aria-selected="false" aria-controls="' . esc_attr( $contact_id ) . '" data-rondo-tab="contact">Contact</button>';
		$html .= '<button type="button" class="rondo-tab" role="tab" aria-selected="false" aria-controls="' . esc_attr( $process_id ) . '" data-rondo-tab="process">Proces</button>';
		$html .= '</div>';
		$html .= '<div id="' . esc_attr( $member_id ) . '" class="rondo-tab-panel is-active" role="tabpanel" data-rondo-tab-panel="member">';
		$html .= $this->section( 'Lidmaatschap', $this->membership_rows( $fields, $badges ) );
		$html .= $this->section( 'Sport en team', $this->sport_rows( $fields, $teams ) );
		$html .= $this->section( 'Huishouden', $this->household_rows( (array) ( $fields['relationships'] ?? [] ) ) );
		$html .= '</div>';
		$html .= '<div id="' . esc_attr( $contact_id ) . '" class="rondo-tab-panel" role="tabpanel" data-rondo-tab-panel="contact" hidden>';
		$html .= $this->section( 'Contactgegevens', $this->contact_rows( $fields, $is_deceased ) );
		$html .= '</div>';
		$html .= '<div id="' . esc_attr( $process_id ) . '" class="rondo-tab-panel" role="tabpanel" data-rondo-tab-panel="process" hidden>';
		$html .= $this->section( 'Ledenprocessen', $this->process_rows( $person_id, $fields ) );
		$html .= $this->section( 'Open werk', $this->todo_rows( $person_id ) );
		$html .= '</div>';
		$html .= '<div class="rondo-actions"><a class="rondo-action rondo-action--primary" href="' . esc_url( home_url( '/people/' . $person_id ) ) . '">Open in Rondo</a></div>';
		$html .= '<p class="rondo-note">Live uit Rondo · ' . esc_html( wp_date( 'd-m-Y H:i' ) ) . '</p>';
		$html .= '</section>';

		return wp_kses( $html, $this->allowed_html() );
	}

	/** Render all accessible exact-email matches with an in-frame profile switcher. */
	public function render_switcher( array $person_ids, string $policy, int $viewer_user_id ): string {
		$profiles = [];
		foreach ( array_values( array_unique( array_map( 'absint', $person_ids ) ) ) as $person_id ) {
			$person = get_post( $person_id );
			if ( ! $person || $person->post_type !== 'person' || $person->post_status !== 'publish' || ! AccessControl::can_view_person( $person_id ) ) {
				continue;
			}
			$profiles[] = [
				'id'   => $person_id,
				'name' => get_the_title( $person_id ),
				'html' => $this->render( $person_id, $policy, $viewer_user_id ),
			];
		}
		if ( $profiles === [] ) {
			return $this->state( 'Geen gekoppeld Rondo-profiel gevonden.' );
		}

		$html  = '<section class="rondo-profile-choice">';
		$html .= '<label for="rondo-profile-switcher"><strong>Profiel</strong></label>';
		$html .= '<select id="rondo-profile-switcher" data-rondo-profile-switcher>';
		foreach ( $profiles as $index => $profile ) {
			$html .= '<option value="rondo-profile-' . esc_attr( (string) $index ) . '">' . esc_html( $profile['name'] ) . '</option>';
		}
		$html .= '</select><p><small>' . esc_html( count( $profiles ) . ' Rondo-profielen gebruiken dit e-mailadres.' ) . '</small></p></section>';
		foreach ( $profiles as $index => $profile ) {
			$html .= '<div id="rondo-profile-' . esc_attr( (string) $index ) . '" data-rondo-profile-panel' . ( $index > 0 ? ' hidden' : '' ) . '>' . $profile['html'] . '</div>';
		}

		return wp_kses( $html, $this->allowed_html() );
	}

	public function state( string $message ): string {
		return '<section class="rondo-sidebar"><div class="rondo-highlight"><p>' . esc_html( $message ) . '</p></div></section>';
	}

	/** @return string[] */
	private function badges( array $fields, bool $is_deceased ): array {
		$badges = [];
		if ( $is_deceased ) {
			$badges[] = 'Niet benaderen';
		}
		if ( ! empty( $fields['former_member'] ) ) {
			$badges[] = 'Oud-lid';
		} elseif ( ! empty( $fields['lid_tot'] ) && (string) $fields['lid_tot'] < wp_date( 'Y-m-d' ) ) {
			$badges[] = 'Lidmaatschap beëindigd';
		} else {
			$badges[] = 'Actief';
		}
		if ( ! empty( $fields['wacht_op_overschrijving'] ) ) {
			$badges[] = 'Wacht op overschrijving';
		}

		return $badges;
	}

	/** @return array<string,string> */
	private function sport_rows( array $fields, array $teams ): array {
		$rows = [];
		$this->add_row( $rows, 'Team', implode( ', ', $teams ) );
		$this->add_row( $rows, 'Spelactiviteit', $fields['spelactiviteit'] ?? '' );
		$this->add_row( $rows, 'Overschrijving', ! empty( $fields['wacht_op_overschrijving'] ) ? 'In behandeling' : '' );

		return $rows;
	}

	/** @return array<string,string> */
	private function membership_rows( array $fields, array $badges ): array {
		$rows = [];
		$this->add_row( $rows, 'Status', $this->badge_markup( $badges ) );
		$this->add_row( $rows, 'Lidsoort', $fields['type_lid'] ?? '' );
		$this->add_row( $rows, 'KNVB-ID', $fields['knvb_id'] ?? '' );
		$birthdate = (string) ( $fields['birthdate'] ?? '' );
		if ( $birthdate !== '' ) {
			$age = $this->age( $birthdate );
			$this->add_row( $rows, 'Geboortedatum', $this->format_date( $birthdate ) . ( $age !== null ? ' (' . $age . ' jaar)' : '' ) );
		}
		$this->add_row( $rows, 'Leeftijdsgroep', $fields['leeftijdsgroep'] ?? '' );
		$this->add_row( $rows, 'Lid sinds', $this->format_date( (string) ( $fields['lid_sinds'] ?? '' ) ) );
		$this->add_row( $rows, 'Lid tot', $this->format_date( (string) ( $fields['lid_tot'] ?? '' ) ) );

		return $rows;
	}

	/** @return array<string,string> */
	private function contact_rows( array $fields, bool $is_deceased ): array {
		$rows = [];
		foreach ( [
			'email_1' => 'E-mail',
			'email_2' => 'E-mail 2',
		] as $field => $label ) {
			$value = (string) ( $fields[ $field ] ?? '' );
			if ( $value !== '' ) {
				$this->add_row( $rows, $label, $is_deceased ? $value : '<a href="mailto:' . esc_attr( $value ) . '">' . esc_html( $value ) . '</a>' );
			}
		}
		foreach ( [
			'mobile_1'    => 'Mobiel',
			'mobile_2'    => 'Mobiel 2',
			'telephone_1' => 'Telefoon',
			'telephone_2' => 'Telefoon 2',
		] as $field => $label ) {
			$value = (string) ( $fields[ $field ] ?? '' );
			if ( $value !== '' ) {
				$this->add_row( $rows, $label, $is_deceased ? $value : '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $value ) ) . '">' . esc_html( $value ) . '</a>' );
			}
		}
		$addresses = array_values( array_filter( (array) ( $fields['addresses'] ?? [] ), 'is_array' ) );
		usort(
			$addresses,
			static fn( array $left, array $right ): int => ( strtolower( (string) ( $right['address_label'] ?? '' ) ) === 'home' ? 1 : 0 )
				<=> ( strtolower( (string) ( $left['address_label'] ?? '' ) ) === 'home' ? 1 : 0 )
		);
		foreach ( $addresses as $address ) {
			if ( ! is_array( $address ) ) {
				continue;
			}
			$label = (string) ( $address['address_label'] ?? 'Adres' );
			$parts = array_filter( [ $address['street_name'] ?? '', $address['house_number'] ?? '', $address['house_number_addition'] ?? '', $address['postal_code'] ?? '', $address['city'] ?? '' ] );
			$this->add_row( $rows, ucfirst( $label ), implode( ' ', array_map( 'strval', $parts ) ) );
		}

		return $rows;
	}

	/** @return array<string,string> */
	private function household_rows( array $relationships ): array {
		$rows  = [];
		$count = 0;
		foreach ( $relationships as $relationship ) {
			if ( ! is_array( $relationship ) || $count >= 6 ) {
				break;
			}
			$person_id = absint( $relationship['related_person_id'] ?? $relationship['related_person'] ?? 0 );
			if ( $person_id <= 0 || ! \Rondo\Core\AccessControl::can_view_person( $person_id ) ) {
				continue;
			}
			$related = get_post( $person_id );
			if ( ! $related || $related->post_type !== 'person' || $related->post_status !== 'publish' ) {
				continue;
			}
			$label  = (string) ( $relationship['relationship_name'] ?? $relationship['relationship_label'] ?? 'Relatie' );
			$status = Fields::get_for_post( $person_id, 'former_member' ) ? 'Oud-lid' : 'Actief';
			$teams  = $this->current_teams( (array) Fields::get_for_post( $person_id, 'work_history' ) );
			$value  = get_the_title( $person_id ) . ' · ' . $status . ( $teams !== [] ? ' · ' . implode( ', ', $teams ) : '' );
			$this->add_row( $rows, $label, $value );
			++$count;
		}

		return $rows;
	}

	/** @return array<string,string> */
	private function process_rows( int $person_id, array $fields ): array {
		$rows = [];
		$this->add_row( $rows, 'Onboardingmail', $fields['onboarding_email_lid_sent'] ?? '' ? 'Verstuurd' : '' );
		$linked_user_id = (int) get_post_meta( $person_id, '_rondo_wp_user_id', true );
		if ( $linked_user_id <= 0 ) {
			$users          = get_users(
				[
					'meta_key'   => 'rondo_linked_person_id',
					'meta_value' => $person_id,
					'number'     => 1,
					'fields'     => 'ids',
				]
				);
			$linked_user_id = isset( $users[0] ) ? (int) $users[0] : 0;
		}
		$this->add_row( $rows, 'Rondo-account', $linked_user_id > 0 ? 'Gekoppeld' : '' );
		$this->add_row( $rows, 'Welkomstmail', get_post_meta( $person_id, '_welcome_email_sent_at', true ) ? 'Verstuurd' : '' );
		$pass = MembershipPassService::get_person_pass_summary( $person_id );
		if ( is_array( $pass ) ) {
			$wallets = [];
			foreach ( (array) ( $pass['wallets'] ?? [] ) as $name => $wallet ) {
				if ( ! empty( $wallet['available'] ) ) {
					$wallets[] = ucfirst( (string) $name );
				}
			}
			$this->add_row( $rows, 'Digitale pas', (string) ( $pass['label'] ?? '' ) . ( $wallets !== [] ? ' · ' . implode( ', ', $wallets ) : '' ) );
		}

		return $rows;
	}

	/** @return array<string,string> */
	private function todo_rows( int $person_id ): array {
		$current_user_id = get_current_user_id();
		$todo_ids        = get_posts(
			[
				'post_type'        => 'rondo_todo',
				'post_status'      => [ 'rondo_open', 'rondo_awaiting' ],
				'posts_per_page'   => 30,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_query'       => [
					'relation' => 'OR',
					[
						'key'     => 'related_persons',
						'value'   => sprintf( '"%d"', $person_id ),
						'compare' => 'LIKE',
					],
					[
						'key'     => 'related_persons',
						'value'   => sprintf( 'i:%d;', $person_id ),
						'compare' => 'LIKE',
					],
				],
			]
		);
		$items           = [];
		foreach ( $todo_ids as $todo_id ) {
			$todo = get_post( $todo_id );
			if ( ! $todo || ( (int) $todo->post_author !== $current_user_id && (int) get_post_meta( $todo_id, 'assigned_user_id', true ) !== $current_user_id ) ) {
				continue;
			}
			$due     = Formatter::for_wire( 'rondo_todo', [ 'due_date' => Fields::get_for_post( $todo_id, 'due_date' ) ] )['due_date'];
			$status  = $todo->post_status === 'rondo_awaiting' ? 'Wacht' : 'Open';
			$overdue = $due && $due < wp_date( 'Y-m-d' ) ? ' · te laat' : '';
			$value   = $status . ( $due ? ' · ' . $this->format_date( (string) $due ) : '' ) . $overdue;
			$items[] = [
				'label' => get_the_title( $todo_id ),
				'value' => $value . ' · <a href="' . esc_url( home_url( '/todos' ) ) . '">Open</a>',
			];
		}
		if ( $items === [] ) {
			return [];
		}
		$rows = [];
		$this->add_row( $rows, 'Aantal', count( $items ) );
		foreach ( array_slice( $items, 0, 3 ) as $item ) {
			$this->add_row( $rows, (string) $item['label'], (string) $item['value'] );
		}

		return $rows;
	}

	/**
	 * Return a minimal contribution-invoice summary for the exact OIDC-bound viewer.
	 *
	 * @return array<string,string>
	 */
	private function invoice_rows( int $person_id, int $viewer_user_id ): array {
		if ( get_current_user_id() !== $viewer_user_id
			|| ( ! user_can( $viewer_user_id, 'financieel_read' ) && ! user_can( $viewer_user_id, 'financieel' ) )
		) {
			return [];
		}

		$invoice_ids = get_posts(
			[
				'post_type'              => 'rondo_invoice',
				'post_status'            => [ 'rondo_sent', 'rondo_overdue' ],
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
				'meta_query'             => [
					'relation' => 'AND',
					[
						'key'     => 'person',
						'value'   => $person_id,
						'compare' => '=',
						'type'    => 'NUMERIC',
					],
					[
						'key'     => 'invoice_type',
						'value'   => 'membership',
						'compare' => '=',
					],
				],
			]
		);
		if ( $invoice_ids === [] ) {
			return [];
		}

		update_meta_cache( 'post', $invoice_ids );
		$rows              = [];
		$total_outstanding = 0.0;
		foreach ( $invoice_ids as $invoice_id ) {
			$invoice_id = (int) $invoice_id;
			if ( get_post_meta( $invoice_id, '_invoice_kind', true ) === 'credit' ) {
				continue;
			}

			$total       = max( 0.0, (float) Fields::get_for_post( $invoice_id, 'total_amount' ) );
			$outstanding = max( 0.0, $total - $this->paid_installment_principal( $invoice_id ) );
			if ( $outstanding <= 0.0 ) {
				continue;
			}

			$total_outstanding += $outstanding;
			$number             = (string) Fields::get_for_post( $invoice_id, 'invoice_number' );
			$label              = $number !== '' ? 'Factuur ' . $number : 'Factuur';
			$value              = $this->format_money( $outstanding ) . ' open van ' . $this->format_money( $total );
			$installments       = $this->installment_summary( $invoice_id );
			if ( $installments !== '' ) {
				$value .= ' · ' . $installments;
			}
			$due_date = $this->next_due_date( $invoice_id );
			if ( $due_date !== '' ) {
				$value .= ' · vervalt ' . $this->format_date( $due_date );
			}
			if ( get_post_status( $invoice_id ) === 'rondo_overdue' || ( $due_date !== '' && $due_date < wp_date( 'Y-m-d' ) ) ) {
				$value .= ' · te laat';
			}
			$value .= ' · <a href="' . esc_url( home_url( '/financien/facturen/' . $invoice_id ) ) . '">Open</a>';
			$this->add_row( $rows, $label, $value );
		}

		return $rows === [] ? [] : array_merge( [ 'Totaal open' => $this->format_money( $total_outstanding ) ], $rows );
	}

	private function paid_installment_principal( int $invoice_id ): float {
		$principal = 0.0;
		$count     = (int) get_post_meta( $invoice_id, '_installment_count', true );
		for ( $number = 1; $number <= $count; $number++ ) {
			if ( get_post_meta( $invoice_id, '_installment_' . $number . '_status', true ) === 'betaald' ) {
				$principal += (float) get_post_meta( $invoice_id, '_installment_' . $number . '_amount', true );
			}
		}

		return $principal;
	}

	private function installment_summary( int $invoice_id ): string {
		$count = (int) get_post_meta( $invoice_id, '_installment_count', true );
		$plan  = (string) get_post_meta( $invoice_id, '_installment_plan', true );
		if ( $count <= 1 || ! in_array( $plan, [ 'quarterly_3', 'monthly_8' ], true ) ) {
			return '';
		}

		$paid = 0;
		for ( $number = 1; $number <= $count; $number++ ) {
			if ( get_post_meta( $invoice_id, '_installment_' . $number . '_status', true ) === 'betaald' ) {
				++$paid;
			}
		}

		return $paid . '/' . $count . ' termijnen betaald';
	}

	private function next_due_date( int $invoice_id ): string {
		$count = (int) get_post_meta( $invoice_id, '_installment_count', true );
		for ( $number = 1; $number <= $count; $number++ ) {
			if ( get_post_meta( $invoice_id, '_installment_' . $number . '_status', true ) === 'betaald' ) {
				continue;
			}
			$due = (string) get_post_meta( $invoice_id, '_installment_' . $number . '_due_date', true );
			if ( $due !== '' ) {
				return $this->wire_date( $due );
			}
		}

		return $this->wire_date( (string) Fields::get_for_post( $invoice_id, 'due_date' ) );
	}

	private function wire_date( string $date ): string {
		return (string) ( Formatter::for_wire( 'rondo_invoice', [ 'due_date' => $date ] )['due_date'] ?? '' );
	}

	private function format_money( float $amount ): string {
		return '€ ' . number_format_i18n( $amount, 2 );
	}

	/** @return string[] */
	private function current_teams( array $work_history ): array {
		$teams = [];
		foreach ( $work_history as $row ) {
			if ( ! is_array( $row ) || empty( $row['is_current'] ) ) {
				continue;
			}
			$team_id = absint( $row['team_id'] ?? $row['team'] ?? 0 );
			if ( $team_id > 0 && get_post_type( $team_id ) === 'team' && get_post_status( $team_id ) === 'publish' ) {
				$teams[] = get_the_title( $team_id );
			}
		}

		return array_values( array_unique( array_filter( $teams ) ) );
	}

	private function section( string $label, array $rows ): string {
		if ( $rows === [] ) {
			return '';
		}

		return '<section class="rondo-section"><h3>' . esc_html( $label ) . '</h3>' . $this->row_list( $rows ) . '</section>';
	}

	private function row_list( array $rows ): string {
		if ( $rows === [] ) {
			return '';
		}
		$html = '<dl class="rondo-rows">';
		foreach ( $rows as $label => $value ) {
			$html .= '<dt>' . esc_html( (string) $label ) . '</dt><dd>' . wp_kses_post( (string) $value ) . '</dd>';
		}

		return $html . '</dl>';
	}

	private function add_row( array &$rows, string $label, $value ): void {
		$value = is_scalar( $value ) ? trim( (string) $value ) : '';
		if ( $value !== '' ) {
			$rows[ $label ] = $value;
		}
	}

	private function age( string $birthdate ): ?int {
		try {
			$birth = new \DateTimeImmutable( $birthdate );
			$today = new \DateTimeImmutable( wp_date( 'Y-m-d' ) );
			return $birth <= $today ? $birth->diff( $today )->y : null;
		} catch ( \Exception $error ) {
			return null;
		}
	}

	private function format_date( string $date ): string {
		$timestamp = strtotime( $date );

		return $timestamp ? wp_date( 'd-m-Y', $timestamp ) : '';
	}

	private function mailbox_label( string $policy ): string {
		if ( $policy === 'ledenadministratie.v2' ) {
			return 'Ledenadministratie';
		}
		if ( $policy === 'contributie.v1' ) {
			return 'Contributie';
		}

		return 'Profiel';
	}

	private function badge_tone( string $badge ): string {
		if ( $badge === 'Actief' ) {
			return 'success';
		}
		if ( $badge === 'Wacht op overschrijving' ) {
			return 'warning';
		}

		return 'muted';
	}

	private function badge_markup( array $badges ): string {
		$html = '';
		foreach ( $badges as $badge ) {
			$html .= '<span class="rondo-badge rondo-badge--' . esc_attr( $this->badge_tone( (string) $badge ) ) . '">' . esc_html( (string) $badge ) . '</span>';
		}

		return $html;
	}

	/** @return array<string,array<string,bool>> */
	private function allowed_html(): array {
		$allowed = wp_kses_allowed_html( 'post' );
		foreach ( [ 'section', 'header', 'div' ] as $tag ) {
			$allowed[ $tag ]['class']                    = true;
			$allowed[ $tag ]['id']                       = true;
			$allowed[ $tag ]['role']                     = true;
			$allowed[ $tag ]['aria-label']               = true;
			$allowed[ $tag ]['hidden']                   = true;
			$allowed[ $tag ]['data-rondo-card']          = true;
			$allowed[ $tag ]['data-rondo-tab-panel']     = true;
			$allowed[ $tag ]['data-rondo-profile-panel'] = true;
		}
		$allowed['button'] = [
			'type'           => true,
			'class'          => true,
			'role'           => true,
			'aria-selected'  => true,
			'aria-controls'  => true,
			'data-rondo-tab' => true,
		];
		$allowed['select'] = [
			'id'                          => true,
			'class'                       => true,
			'data-rondo-profile-switcher' => true,
			'aria-label'                  => true,
		];
		$allowed['option'] = [
			'value'    => true,
			'selected' => true,
		];

		return $allowed;
	}
}
