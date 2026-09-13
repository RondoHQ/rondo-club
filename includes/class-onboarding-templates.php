<?php
/** Editable, non-sending onboarding drafts and fictional situation previews. */

namespace Rondo\Onboarding;

use Rondo\Notifications\EmailTemplate;
use Rondo\Notifications\OnboardingEmailSender;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Templates {
	const OPTION    = 'rondo_onboarding_template_blocks';
	const LOCK      = 'rondo_onboarding_template_lock';
	const VARIABLES = [ 'first_name', 'club_naam', 'email' ];

	/** Server-owned conditions; editing copy cannot alter eligibility. */
	public static function definitions(): array {
		$rows   = [
			'subject_member'          => [ 'Onderwerp ledenmail', 'Onderwerpen', 'Nieuw lid', 'Welkom bij {club_naam}' ],
			'subject_volunteer'       => [ 'Onderwerp vrijwilligersmail', 'Onderwerpen', 'Nieuwe vrijwilliger', 'Welkom als vrijwilliger bij {club_naam}' ],
			'subject_combined'        => [ 'Onderwerp gecombineerde mail', 'Onderwerpen', 'Nieuw lid én nieuwe vrijwilliger', 'Welkom als lid en vrijwilliger bij {club_naam}' ],
			'greeting_adult'          => [ 'Aanhef vanaf 18 jaar', 'Welkom', 'Volwassen lid of vrijwilliger', 'Beste {first_name},' ],
			'greeting_minor'          => [ 'Aanhef jonger dan 18', 'Welkom', 'Minderjarig lid of vrijwilliger', 'Beste {first_name} en eventuele ouders/verzorgers,' ],
			'intro_member_adult'      => [ 'Welkom volwassen lid', 'Welkom', 'Ledenmail of gecombineerde mail, vanaf 18', 'Welkom bij {club_naam}! We zijn blij dat je lid bent geworden.' ],
			'intro_member_minor'      => [ 'Welkom minderjarig lid', 'Welkom', 'Ledenmail of gecombineerde mail, jonger dan 18', 'Welkom bij {club_naam}! We zijn blij dat {first_name} lid is geworden.' ],
			'intro_volunteer'         => [ 'Welkom vrijwilliger', 'Welkom', 'Vrijwilligersmail of gecombineerde mail', 'Wat fijn dat je vrijwilliger bent geworden bij {club_naam}! Bedankt dat je je wilt inzetten voor onze club.' ],
			'member_fees'             => [ 'Contributie', 'Ledeninformatie', 'Ledenmail of gecombineerde mail; leeg is weglaten', '' ],
			'member_clothing'         => [ 'Kleding voor leden', 'Ledeninformatie', 'Ledenmail of gecombineerde mail; leeg is weglaten', '' ],
			'member_training'         => [ 'Trainingen en wedstrijden', 'Ledeninformatie', 'Ledenmail of gecombineerde mail; leeg is weglaten', '' ],
			'member_volunteering'     => [ 'Vrijwilligerswerk voor leden', 'Ledeninformatie', 'Ledenmail of gecombineerde mail; leeg is weglaten', '' ],
			'volunteer_information'   => [ 'Overige vrijwilligersinformatie', 'Vrijwilligersinformatie', 'Vrijwilligersmail of gecombineerde mail; leeg is weglaten', '' ],
			'vog_missing'             => [ 'VOG ontbreekt', 'VOG', 'VOG vereist, geen geldige VOG en geen actuele aanvraag', 'Voor jouw functie vragen we een Verklaring Omtrent het Gedrag (VOG). Je ontvangt apart uitleg over de gratis aanvraag via de club. Heb je al een VOG? Neem dan contact op met onze VOG-coördinator om te bespreken of je die kunt gebruiken.' ],
			'vog_requested'           => [ 'VOG-aanvraag loopt', 'VOG', 'Huidige aanvraag bij Justis klaargezet', 'Voor jouw VOG loopt al een aanvraag. Volg de instructies die je daarvoor ontvangt. Zodra je de VOG hebt ontvangen, kun je deze uploaden in Rondo.' ],
			'vog_review'              => [ 'VOG in controle of ter beoordeling', 'VOG', 'Ontvangen VOG wordt gecontroleerd of beoordeeld', 'We hebben je VOG ontvangen en controleren deze. Je hoeft nu niets te doen.' ],
			'vog_valid'               => [ 'VOG geldig', 'VOG', 'VOG vereist en geldig geregistreerd', 'Er staat al een geldige VOG voor je geregistreerd. Hiervoor hoef je nu niets te doen.' ],
			'vog_renew'               => [ 'VOG vernieuwen', 'VOG', 'Vernieuwing nodig, nog geen actuele aanvraag', 'Je VOG moet worden vernieuwd. Je ontvangt apart uitleg over de gratis aanvraag via de club.' ],
			'vog_resubmit'            => [ 'VOG opnieuw aanleveren', 'VOG', 'Nieuwe inzending nodig; voorstel ter controle vóór activering', 'Je VOG moet opnieuw worden aangeleverd. Volg de toelichting die je hierover hebt ontvangen.' ],
			'clothing_blocked'        => [ 'Kleding wacht op VOG', 'Vrijwilligerskleding', 'Geselecteerde kledingfunctie, vereiste VOG nog niet in orde', 'Voor jouw functie krijg je kleding van de club. Zodra je VOG in orde is, verschijnt dit bij de kledingcoördinator. Die neemt contact met je op over het ophalen.' ],
			'clothing_ready'          => [ 'Kleding kan geregeld worden', 'Vrijwilligerskleding', 'Geselecteerde kledingfunctie, geen VOG-plicht of geldige VOG', 'Voor jouw functie krijg je kleding van de club. De kledingcoördinator ziet dat dit geregeld kan worden en neemt contact met je op over het ophalen.' ],
			'clothing_return_blocked' => [ 'Kleding bij terugkeer wacht op VOG', 'Vrijwilligerskleding', 'Terugkerende vrijwilliger met kledingfunctie; VOG nog nodig', 'Zodra je VOG in orde is, bekijkt de kledingcoördinator welke kleding je voor jouw functie nog nodig hebt.' ],
			'clothing_return_ready'   => [ 'Kleding bij terugkeer beoordelen', 'Vrijwilligerskleding', 'Terugkerende vrijwilliger met kledingfunctie; voorwaarden voldaan', 'De kledingcoördinator bekijkt welke kleding je voor jouw functie nog nodig hebt en neemt contact met je op.' ],
			'account_activate'        => [ 'Account activeren', 'Account', 'Voor deze ontvanger is nog geen passend account vastgesteld', 'In Rondo kun je je gegevens bekijken en aanpassen. Activeer je account met het e-mailadres waarop je deze mail ontvangt.' ],
			'account_exists'          => [ 'Account bestaat', 'Account', 'Een passend account voor deze ontvanger is vastgesteld', 'Je hebt al een Rondo-account. Daarmee kun je je gegevens bekijken en aanpassen.' ],
			'account_parents'         => [ 'Toelichting ouderaccounts', 'Account', 'Jonger dan 18 en account nog activeren', 'Ouders/verzorgers kunnen een eigen account aanmaken.' ],
			'button_activate'         => [ 'Knop account activeren', 'Account', 'Vaste link naar /activeren', 'Activeer je Rondo-account' ],
			'button_login'            => [ 'Knop inloggen', 'Account', 'Vaste link naar /login', 'Log in op Rondo' ],
			'closing'                 => [ 'Afsluiting', 'Afsluiting', 'Alle welkomstmails', "Heb je vragen? Neem gerust contact met ons op.\n\nMet sportieve groet,\n{club_naam}" ],
		];
		$result = [];
		foreach ( $rows as $id => $row ) {
			$result[ $id ]                = array_combine( [ 'label', 'group', 'condition', 'default' ], $row );
			$result[ $id ]['single_line'] = str_starts_with( $id, 'subject_' ) || str_starts_with( $id, 'button_' );
			$result[ $id ]['optional']    = in_array( $id, [ 'member_fees', 'member_clothing', 'member_training', 'member_volunteering', 'volunteer_information' ], true );
		}
		return $result;
	}

	public static function settings(): array {
		$definitions = self::definitions();
		$blocks      = array_column( $definitions, 'default' );
		$blocks      = array_combine( array_keys( $definitions ), $blocks );
		$blocks      = array_replace( $blocks, array_intersect_key( (array) get_option( self::OPTION, [] ), $blocks ) );
		$legacy      = new OnboardingEmailSender();
		return [
			'blocks'          => $blocks,
			'revision'        => self::revision( $blocks ),
			'definitions'     => $definitions,
			'variables'       => self::VARIABLES,
			'legacy'          => [
				'lid'          => $legacy->get_settings( 'lid' ),
				'vrijwilliger' => $legacy->get_settings( 'vrijwilliger' ),
			],
			'sending_enabled' => false,
		];
	}

	private static function revision( array $blocks ): string {
		return hash( 'sha256', wp_json_encode( $blocks ) );
	}

	/** Validate the entire draft before any write, including placeholder names. */
	private static function validate( $blocks ) {
		$definitions = self::definitions();
		if ( ! is_array( $blocks ) || array_diff_key( $blocks, $definitions ) || array_diff_key( $definitions, $blocks ) ) {
			return new \WP_Error( 'onboarding_templates', 'Lever alle bekende mailblokken aan.', [ 'status' => 400 ] );
		}
		$clean = [];
		foreach ( $definitions as $id => $definition ) {
			$value = $blocks[ $id ];
			if ( ! is_string( $value ) || strlen( $value ) > ( $definition['single_line'] ? 300 : 20000 ) ) {
				return new \WP_Error( 'onboarding_templates', 'Ongeldige of te lange tekst: ' . $definition['label'], [ 'status' => 400 ] );
			}
			$remaining = str_replace( array_map( static fn( $name ) => '{' . $name . '}', self::VARIABLES ), '', $value );
			if ( strpbrk( $remaining, '{}' ) !== false ) {
				return new \WP_Error( 'onboarding_variables', 'Onbekende variabele in: ' . $definition['label'], [ 'status' => 400 ] );
			}
			$clean[ $id ] = $definition['single_line'] ? sanitize_text_field( $value ) : sanitize_textarea_field( $value );
			if ( ! $definition['optional'] && $clean[ $id ] === '' ) {
				return new \WP_Error( 'onboarding_templates', 'Vul dit blok in: ' . $definition['label'], [ 'status' => 400 ] );
			}
		}
		return $clean;
	}

	public static function save( array $input ) {
		$blocks = self::validate( $input['blocks'] ?? null );
		if ( is_wp_error( $blocks ) ) {
			return $blocks;
		}
		// An invariant value is essential for atomic add_option contenders.
		if ( ! add_option( self::LOCK, 'locked', '', false ) ) {
			return new \WP_Error( 'onboarding_templates_busy', 'Er wordt al opgeslagen. Probeer opnieuw.', [ 'status' => 409 ] );
		}
		try {
			if ( ( $input['revision'] ?? '' ) !== self::settings()['revision'] ) {
				return new \WP_Error( 'onboarding_templates_conflict', 'De teksten zijn intussen gewijzigd. Kopieer je wijzigingen en laad de instellingen opnieuw.', [ 'status' => 409 ] );
			}
			update_option( self::OPTION, $blocks, false );
			if ( get_option( self::OPTION ) !== $blocks ) {
				return new \WP_Error( 'onboarding_templates_save', 'Opslaan is niet gelukt. Probeer opnieuw.', [ 'status' => 500 ] );
			}
			return self::settings();
		} finally {
			delete_option( self::LOCK );
		}
	}

	/** Fictional scenarios only: never infer verified account or source state here. */
	public static function preview( array $input ) {
		$blocks = self::validate( $input['blocks'] ?? null );
		if ( is_wp_error( $blocks ) ) {
			return $blocks;
		}
		$options  = [
			'type'      => [ 'member', 'volunteer', 'combined' ],
			'age'       => [ 'adult', 'minor' ],
			'recipient' => [ 'self', 'parent' ],
			'account'   => [ 'activate', 'exists' ],
			'vog'       => [ 'none', 'missing', 'requested', 'review', 'valid', 'renew', 'resubmit' ],
			'clothing'  => [ 'no', 'yes' ],
			'returning' => [ 'no', 'yes' ],
		];
		$scenario = $input['scenario'] ?? null;
		if ( ! is_array( $scenario ) || array_diff_key( $scenario, $options ) || array_diff_key( $options, $scenario ) ) {
			return new \WP_Error( 'onboarding_scenario', 'Kies een volledige voorbeeldsituatie.', [ 'status' => 400 ] );
		}
		foreach ( $options as $key => $values ) {
			if ( ! in_array( $scenario[ $key ], $values, true ) ) {
				return new \WP_Error( 'onboarding_scenario', 'Ongeldige voorbeeldkeuze: ' . $key, [ 'status' => 400 ] );
			}
		}
		if ( $scenario['age'] === 'adult' && $scenario['recipient'] === 'parent' ) {
			return new \WP_Error( 'onboarding_parent', 'Bij een volwassen lid ontvangen ouders geen welkomstmail.', [ 'status' => 400 ] );
		}
		$member    = $scenario['type'] !== 'volunteer';
		$volunteer = $scenario['type'] !== 'member';
		$ids       = [ 'greeting_' . $scenario['age'] ];
		if ( $member ) {
			$ids[] = 'intro_member_' . $scenario['age'];
		}
		if ( $volunteer ) {
			$ids[] = 'intro_volunteer';
		}
		if ( $member ) {
			$ids = array_merge( $ids, [ 'member_fees', 'member_clothing', 'member_training', 'member_volunteering' ] );
		}
		if ( $volunteer ) {
			$ids[] = 'volunteer_information';
			if ( $scenario['vog'] !== 'none' ) {
				$ids[] = 'vog_' . $scenario['vog'];
			}
			if ( $scenario['clothing'] === 'yes' ) {
				$ids[] = 'clothing_' . ( $scenario['returning'] === 'yes' ? 'return_' : '' ) . ( in_array( $scenario['vog'], [ 'none', 'valid' ], true ) ? 'ready' : 'blocked' );
			}
		}
		$ids[] = 'account_' . $scenario['account'];
		if ( $scenario['age'] === 'minor' && $scenario['account'] === 'activate' ) {
			$ids[] = 'account_parents';
		}
		$button     = $scenario['account'] === 'exists' ? 'login' : 'activate';
		$ids[]      = 'button_' . $button;
		$ids[]      = 'closing';
		$club_name  = (string) \Rondo\Config\ClubConfig::get_club_name();
		$vars       = [
			'{first_name}' => $scenario['age'] === 'minor' ? 'Emma' : 'Robin',
			'{club_naam}'  => $club_name !== '' ? $club_name : get_bloginfo( 'name' ),
			'{email}'      => $scenario['recipient'] === 'parent' ? 'ouder@example.invalid' : 'lid@example.invalid',
		];
		$subject_id = 'subject_' . $scenario['type'];
		$subject    = sanitize_text_field( strtr( $blocks[ $subject_id ], $vars ) );
		$html       = '';
		$included   = [ $subject_id ];
		foreach ( $ids as $id ) {
			if ( $blocks[ $id ] === '' ) {
				continue;
			}
			$included[] = $id;
			$text       = strtr( $blocks[ $id ], $vars );
			$html      .= str_starts_with( $id, 'button_' )
				? EmailTemplate::render_cta_button( home_url( $button === 'login' ? '/login' : '/activeren' ), $text )
				: EmailTemplate::format_plain_text( $text );
		}
		return [
			'simulation_only'  => true,
			'sending_enabled'  => false,
			'template_version' => self::revision( $blocks ),
			'recipient'        => $vars['{email}'],
			'subject'          => $subject,
			'block_ids'        => $included,
			'html'             => EmailTemplate::render(
				[
					'brand_name' => $vars['{club_naam}'],
					'heading'    => $subject,
					'body_html'  => $html,
				]
				),
		];
	}
}
