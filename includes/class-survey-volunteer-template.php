<?php
/** First reusable questionnaire, based on the AWC volunteer policy survey. */
namespace Rondo\Surveys;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VolunteerTemplate {
	private static function question( string $id, string $label, string $type = 'text', array $choices = [], ?array $condition = null, bool $required = false ): array {
		$options = [];
		foreach ( $choices as $key => $text ) {
			$options[] = [
				'id'    => is_int( $key ) ? 'o' . $key : $key,
				'label' => $text,
			];
		}
		return [
			'id'        => $id,
			'label'     => $label,
			'type'      => $type,
			'required'  => $required,
			'options'   => $options,
			'condition' => $condition,
		];
	}

	public static function data(): array {
		$satisfaction = [ 'Zeer ontevreden', 'Ontevreden', 'Neutraal', 'Tevreden', 'Zeer tevreden', 'Niet van toepassing / weet ik niet' ];
		$agreement    = [ 'Helemaal mee oneens', 'Mee oneens', 'Neutraal', 'Mee eens', 'Helemaal mee eens', 'Niet van toepassing / weet ik niet' ];
		$route        = static fn( $value ) => [
			'question' => 'route',
			'value'    => $value,
		];
		$rule         = static fn( $q, $v ) => [
			'question' => $q,
			'value'    => $v,
		];
		$q            = [ self::class, 'question' ];
		$sections     = [
			[
				'id'        => 'general',
				'title'     => 'Jouw ervaring bij AWC',
				'questions' => [
					$q( 'roles', 'Wat is je rol binnen AWC? Meerdere antwoorden mogelijk.', 'multiple', [ 'Speler', 'Ouder van een speler', 'Kaderlid', 'Commissielid', 'Sponsor', 'Om een andere reden betrokken bij AWC' ], null, true ),
					$q( 'satisfaction', 'Hoe tevreden ben je over het vrijwilligersbeleid binnen AWC?', 'scale', $satisfaction, null, true ),
					$q( 'welcome', 'Ik voel me welkom en gewaardeerd binnen AWC.', 'scale', $agreement, null, true ),
					$q( 'contribute', 'Vind je dat iedereen binnen het vrijwilligersbeleid een steentje moet bijdragen?', 'single', [ 'Ja', 'Nee', 'Weet ik niet' ], null, true ),
					$q( 'contribute_note', 'Wil je je antwoord toelichten?' ),
					$q( 'expectations', 'Het is duidelijk wat er van mij als lid wordt verwacht.', 'scale', $agreement, null, true ),
					$q(
					'account',
					'Heb je al een account aangemaakt in Rondo?',
					'single',
					[
						'easy'      => 'Ja, dat ging makkelijk',
						'difficult' => 'Ja, maar het was best ingewikkeld',
						'time'      => 'Nee, ik wil het wel doen maar het kost te veel tijd',
						'help'      => 'Nee, ik wil het wel doen maar het lukt me niet',
						'other'     => 'Nee, want ik zet me op een andere manier al in voor AWC',
						'untried'   => 'Nee, en ook nog niet geprobeerd',
					],
					null,
					true
					),
					$q( 'account_note', 'Waarom heb je het nog niet geprobeerd?', 'text', [], $rule( 'account', 'untried' ) ),
					$q(
					'route',
					'Welke omschrijving past het beste bij jouw situatie?',
					'single',
					[
						'done'    => 'Ik heb me ingeschreven en mijn dienst al uitgevoerd',
						'planned' => 'Ik heb me ingeschreven, maar moet mijn dienst nog uitvoeren',
						'other'   => 'Ik zet me op een andere manier in voor AWC',
						'none'    => 'Ik heb me nog niet ingeschreven',
					],
					null,
					true
					),
				],
			],
			[
				'id'          => 'done',
				'title'       => 'Je hebt je dienst al uitgevoerd',
				'description' => 'Na je dienst heb je een aparte enquête gekregen. Heb je die al ingevuld? Dan hoef je hier alleen nog iets toe te voegen als je dat wilt. Verzend daarna deze enquête, zodat ook je algemene antwoorden worden meegenomen.',
				'condition'   => $route( 'done' ),
				'questions'   => [
					$q( 'done_note', 'Wil je nog iets delen met het bestuur?' ),
				],
			],
			[
				'id'        => 'planned',
				'title'     => 'Je gaat je dienst nog uitvoeren',
				'condition' => $route( 'planned' ),
				'questions' => [
					$q( 'signup', 'Hoe ging het inschrijven?' ),
					$q( 'feeling', 'Hoe sta je tegenover je komende dienst?', 'single', [ 'Ik zie ertegenop', 'Ik vind het spannend, maar oké', 'Ik heb er zin in', 'Ik doe het omdat het moet en zie het echt als een verplichting' ] ),
					$q( 'chosen_task', 'Waarom heb je gekozen voor deze inschrijftaak?' ),
					$q( 'duration', 'Wat vind je van diensten van 3 uur?', 'single', [ 'Prima', 'Te lang', 'Te kort' ] ),
					$q( 'moments', 'Welke momenten hebben je voorkeur? Meerdere antwoorden mogelijk.', 'multiple', [ 'Doordeweeks', 'De ochtend', 'De middag', 'De avond', 'Het moment waarop mijn kind speelt' ] ),
				],
			],
			[
				'id'        => 'other',
				'title'     => 'Je doet een andere vrijwilligerstaak',
				'condition' => $route( 'other' ),
				'questions' => [
					$q( 'other_task', 'Wat voor andere taak doe je? Meerdere antwoorden mogelijk.', 'multiple', [ 'Trainer / teammanager', 'Jeugdscheidsrechter', 'Commissie', 'Individuele functie', 'Werkgroep' ] ),
					$q( 'other_task_feeling', 'Wat vind je van die taak?' ),
					$q(
					'cooperation',
					'Wat vind je van de samenwerking met andere trainers, leiders, commissieleden en het bestuur?',
					'single',
					[
						'questions' => 'Fijn, ik kan bij anderen terecht met vragen',
						'advice'    => 'Fijn, anderen komen veel bij mij met vragen',
						'team'      => 'Fijn, we werken samen en versterken elkaar',
						'improve'   => 'Oké, maar het kan beter',
						'different' => 'Anders',
					]
					),
					$q( 'cooperation_contact', 'Met wie overleg je het meeste?', 'text', [], $rule( 'cooperation', 'questions' ) ),
					$q( 'cooperation_improve', 'Welk verbeterpunt heb je?', 'text', [], $rule( 'cooperation', 'improve' ) ),
					$q( 'cooperation_other', 'Hoe ervaar je de samenwerking?', 'text', [], $rule( 'cooperation', 'different' ) ),
					$q(
					'parents',
					'Hoe verloopt het contact met ouders?',
					'single',
					[
						'none'    => 'Ik heb geen contact met ouders',
						'good'    => 'Ik heb contact met ouders en dat gaat goed',
						'improve' => 'Ik heb contact met ouders en dat kan beter',
					]
					),
					$q( 'parents_help', 'Hoe kan het bestuur je hierbij helpen?', 'text', [], $rule( 'parents', 'improve' ) ),
					$q(
					'time',
					'Hoe ervaar je de tijdsinvestering?',
					'single',
					[
						'enjoy'  => 'Het kost me veel tijd, maar ik vind het leuk en heb het ervoor over',
						'little' => 'Het kost me veel tijd, maar geeft me te weinig voldoening',
						'fine'   => 'De tijdsinvestering is prima',
						'more'   => 'Het kost me niet zoveel tijd; ik wil me ook op een andere manier inzetten',
					]
					),
					$q( 'time_help', 'Wat kan het bestuur doen om dit te verbeteren?', 'text', [], $rule( 'time', 'little' ) ),
					$q( 'time_more', 'Wat lijkt je interessant om daarnaast te doen?', 'text', [], $rule( 'time', 'more' ) ),
					$q(
					'support',
					'Voel je je voldoende ondersteund bij je taak? Meerdere antwoorden mogelijk.',
					'multiple',
					[
						'members' => 'Ja, door de leden',
						'board'   => 'Ja, door het bestuur',
						'staff'   => 'Ja, door de andere kaderleden',
						'no'      => 'Nee, ik voel me niet gezien en ondersteund',
					]
					),
					$q( 'support_help', 'Welke verbeterpunten heb je?', 'text', [], $rule( 'support', 'no' ) ),
					$q( 'involvement', 'Wat vind je van de betrokkenheid van (ouders van) de leden?', 'single', [ 'Goed', 'Voldoende', 'Matig', 'Slecht' ] ),
					$q( 'involvement_missing', 'Wat mis je in die betrokkenheid?' ),
					$q( 'involvement_tips', 'Welke tips heb je om dat te bereiken?' ),
					$q( 'other_note', 'Wil je nog iets aanvullen?' ),
				],
			],
			[
				'id'        => 'none',
				'title'     => 'Je hebt je nog niet ingeschreven',
				'condition' => $route( 'none' ),
				'questions' => [
					$q(
					'no_signup',
					'Waarom heb je je nog niet ingeschreven?',
					'single',
					[
						'time'  => 'Ik snap dat een club vrijwilligers nodig heeft, maar ik heb er geen tijd voor',
						'tasks' => 'Ik vind de aangeboden taken niet passend',
						'fees'  => 'Mijn contributie is voldoende bijdrage; ik hoef mijzelf niet in te schrijven',
						'other' => 'Een andere reden',
					]
					),
					$q( 'no_signup_other', 'Wat is je andere reden?', 'text', [], $rule( 'no_signup', 'other' ) ),
					$q(
					'talent',
					'Heb je een ander talent dat je voor AWC kunt inzetten?',
					'single',
					[
						'yes' => 'Ja',
						'no'  => 'Nee',
					]
					),
					$q( 'talent_note', 'Wat zou je voor AWC kunnen betekenen?', 'text', [], $rule( 'talent', 'yes' ) ),
					$q( 'motivation', 'Hoe kunnen we je motiveren om iets in het belang van AWC te doen?' ),
					$q( 'hours', 'AWC verwacht dat je je minimaal 6 uur per seizoen inzet voor de club. Wat vind je van dat aantal uren?' ),
				],
			],
		];
		return [
			'title'    => 'Enquête vrijwilligersbeleid AWC',
			'intro'    => 'Een paar maanden geleden is AWC gestart met het nieuwe vrijwilligersbeleid. Het bestuur is benieuwd naar jouw ervaringen. Met je antwoorden help je ons om het beleid verder te verbeteren.',
			'thanks'   => 'Bedankt voor het invullen! De uitkomsten worden gepresenteerd tijdens de eerstvolgende ALV op maandag 14 december 2026.',
			'document' => [ 'sections' => $sections ],
		];
	}
}
