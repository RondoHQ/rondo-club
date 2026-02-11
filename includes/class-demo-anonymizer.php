<?php
/**
 * Demo Data Anonymizer
 *
 * Generates realistic Dutch fake data for demo fixtures.
 * Uses seeded randomization for reproducibility and caches identities per ref.
 *
 * @package Rondo
 * @since 1.0.0
 */

namespace Rondo\Demo;

/**
 * Class DemoAnonymizer
 *
 * Generates consistent, realistic Dutch fake data for demo exports.
 */
class DemoAnonymizer {

	/**
	 * Random seed for reproducibility
	 *
	 * @var int
	 */
	private $seed;

	/**
	 * Identity cache keyed by person ref
	 *
	 * @var array
	 */
	private $identities = array();

	/**
	 * Dutch male first names
	 *
	 * @var array
	 */
	private static $male_first_names = array(
		'Jan', 'Pieter', 'Willem', 'Daan', 'Sem', 'Lucas', 'Levi', 'Finn', 'Luuk', 'Milan',
		'Bram', 'Lars', 'Jesse', 'Thomas', 'Tim', 'Stijn', 'Ruben', 'Thijs', 'Max', 'Sven',
		'Niels', 'Joost', 'Jeroen', 'Mark', 'Erik', 'Peter', 'Kees', 'Henk', 'Bert', 'Rob',
		'Martijn', 'Wouter', 'Jasper', 'Bas', 'Rick', 'Dennis', 'Stefan', 'Marcel', 'Arjan', 'Gerrit',
		'Dirk', 'Frank', 'Hans', 'Michiel', 'Sander', 'Kevin', 'Jelle', 'Dave', 'Floris', 'Hugo',
		'Maarten', 'Gijs', 'Casper', 'Timo', 'Niek', 'Bart', 'Freek', 'Wim', 'Anton', 'Paul',
		'Jaap', 'Simon', 'David', 'Tom', 'Rens', 'Job', 'Sam', 'Arie', 'Cor', 'Piet',
		'Frits', 'Guus', 'Johan', 'Marco', 'Raymond', 'Patrick', 'Vincent', 'Edwin', 'Gerard', 'Ben',
	);

	/**
	 * Dutch female first names
	 *
	 * @var array
	 */
	private static $female_first_names = array(
		'Anna', 'Emma', 'Sophie', 'Julia', 'Sara', 'Eva', 'Lisa', 'Noa', 'Lotte', 'Fleur',
		'Iris', 'Isa', 'Sanne', 'Femke', 'Mila', 'Eline', 'Roos', 'Vera', 'Lynn', 'Hanna',
		'Linda', 'Monique', 'Sandra', 'Annemiek', 'Ingrid', 'Petra', 'Marieke', 'Esther', 'Ellen', 'Nicole',
		'Miranda', 'Wendy', 'Birgit', 'Marloes', 'Anke', 'Anouk', 'Demi', 'Tess', 'Fenna', 'Nina',
		'Maud', 'Amy', 'Yara', 'Lieke', 'Britt', 'Kim', 'Denise', 'Manon', 'Chantal', 'Diana',
		'Ilse', 'Rianne', 'Wilma', 'Joke', 'Marian', 'Anja', 'Claudia', 'Astrid', 'Tineke', 'Greetje',
		'Liesbeth', 'Hanneke', 'Janneke', 'Marjolein', 'Corina', 'Renate', 'Suzanne', 'Judith', 'Joyce', 'Silvia',
		'Miriam', 'Simone', 'Pauline', 'Nienke', 'Elise', 'Merel', 'Renee', 'Daphne', 'Elisa', 'Amber',
	);

	/**
	 * Dutch infixes (tussenvoegsel) with weights
	 *
	 * @var array
	 */
	private static $infixes = array(
		'van'        => 30,
		'de'         => 20,
		'van de'     => 15,
		'van der'    => 15,
		'van den'    => 10,
		'den'        => 5,
		'ter'        => 3,
		'te'         => 2,
	);

	/**
	 * Dutch last names
	 *
	 * @var array
	 */
	private static $last_names = array(
		'Jansen', 'Vries', 'Berg', 'Dijk', 'Bakker', 'Janssen', 'Visser', 'Smit', 'Meijer', 'Boer',
		'Mulder', 'Groot', 'Bos', 'Vos', 'Peters', 'Hendriks', 'Leeuwen', 'Dekker', 'Brouwer', 'Wit',
		'Dijkstra', 'Smeets', 'Graaf', 'Meer', 'Linden', 'Willems', 'Jong', 'Maas', 'Vermeer', 'Heijden',
		'Scholten', 'Veen', 'Post', 'Kuijpers', 'Jacobs', 'Heuvel', 'Wal', 'Hoekstra', 'Hermans', 'Bosman',
		'Wolters', 'Sanders', 'Bruin', 'Kok', 'Gerritsen', 'Wijk', 'Schouten', 'Beek', 'Haan', 'Timmermans',
		'Groen', 'Peeters', 'Koster', 'Blom', 'Beumer', 'Dam', 'Schipper', 'Klein', 'Huisman', 'Jonker',
		'Loon', 'Es', 'Prins', 'Vliet', 'Verhoeven', 'Pol', 'Boom', 'Steen', 'Hofman', 'Admiraal',
		'Kuiper', 'Evers', 'Horst', 'Koopman', 'Houten', 'Berends', 'Molenaar', 'Vink', 'Ruiter', 'Schaik',
		'Aarts', 'Zanden', 'Reijnen', 'Driessen', 'Renssen', 'Ridder', 'Claessen', 'Martens', 'Gelderen', 'Vonk',
		'Rutten', 'Poel', 'Boogaard', 'Tol', 'Rijn', 'Akkerman', 'Simons', 'Verhagen', 'Holterman', 'Otten',
	);

	/**
	 * Dutch street names
	 *
	 * @var array
	 */
	private static $streets = array(
		'Kerkstraat', 'Dorpsstraat', 'Schoolstraat', 'Molenweg', 'Stationsweg', 'Hoofdstraat', 'Kastanjelaan', 'Lindenlaan', 'Berkenlaan', 'Eikenlaan',
		'Beukenlaan', 'Populierenlaan', 'Wilgenlaan', 'Rozenlaan', 'Tulpenlaan', 'Julianastraat', 'Beatrixlaan', 'Wilhelminastraat', 'Oranjestraat', 'Nassaulaan',
		'Marktplein', 'Raadhuisstraat', 'Kloosterstraat', 'Brinkstraat', 'Grotestraat', 'Nieuwstraat', 'Langestraat', 'Kortestraat', 'Hoogstraat', 'Achterstraat',
		'Voorstraat', 'Torenstraat', 'Havenstraat', 'Dijkstraat', 'Kanaalweg', 'Lageweg', 'Hogeweg', 'Veldweg', 'Bosweg', 'Heideweg',
		'Duinweg', 'Polderweg', 'Rivierweg', 'Parkstraat', 'Sportlaan', 'Industrieweg', 'Ambachtstraat', 'Handelsweg', 'Burgemeester Jansenlaan', 'Professor de Vriesstraat',
	);

	/**
	 * Dutch cities
	 *
	 * @var array
	 */
	private static $cities = array(
		'Amsterdam', 'Rotterdam', 'Den Haag', 'Utrecht', 'Eindhoven', 'Groningen', 'Tilburg', 'Almere', 'Breda', 'Nijmegen',
		'Apeldoorn', 'Haarlem', 'Arnhem', 'Zaanstad', 'Amersfoort', 'Haarlemmermeer', 'Den Bosch', 'Zoetermeer', 'Zwolle', 'Leiden',
		'Maastricht', 'Dordrecht', 'Ede', 'Leeuwarden', 'Alphen aan den Rijn', 'Emmen', 'Westland', 'Delft', 'Deventer', 'Helmond',
		'Hilversum', 'Oss', 'Sittard', 'Schiedam', 'Amstelveen', 'Gouda', 'Spijkenisse', 'Veenendaal', 'Zeist', 'Hardenberg',
		'Assen', 'Purmerend', 'Roosendaal', 'Vlaardingen', 'Hoorn', 'Bergen op Zoom', 'Katwijk', 'Wageningen', 'Baarn', 'Bussum',
		'Doetinchem', 'Middelburg', 'Goes', 'Kampen', 'Meppel', 'Steenwijk', 'Waalwijk', 'Weert', 'Venlo', 'Heerlen',
	);

	/**
	 * Email domains (obviously fake)
	 *
	 * @var array
	 */
	private static $email_domains = array(
		'example.com',
		'rondo-demo.nl',
		'voorbeeld.nl',
	);

	/**
	 * Constructor
	 *
	 * @param int $seed Random seed for reproducibility. Default: 42.
	 */
	public function __construct( int $seed = 42 ) {
		$this->seed = $seed;
		mt_srand( $seed );
	}

	/**
	 * Generate complete identity for a person ref
	 *
	 * @param string      $ref Person reference ID.
	 * @param string|null $gender Gender ('male', 'female', or null for random).
	 * @return array Identity with keys: first_name, infix, last_name, email, phone, address.
	 */
	public function generate_identity( string $ref, ?string $gender = null ): array {
		// Check cache first.
		if ( isset( $this->identities[ $ref ] ) ) {
			return $this->identities[ $ref ];
		}

		// Determine gender.
		if ( ! in_array( $gender, array( 'male', 'female' ), true ) ) {
			$gender = mt_rand( 0, 1 ) === 0 ? 'male' : 'female';
		}

		// Generate name components.
		$first_name = $this->generate_first_name( $gender );
		$infix      = $this->generate_infix();
		$last_name  = $this->generate_last_name();

		// Generate contact details.
		$email   = $this->generate_email( $first_name, $infix, $last_name );
		$phone   = $this->generate_phone();
		$address = $this->generate_address();

		// Build identity.
		$identity = array(
			'first_name' => $first_name,
			'infix'      => $infix,
			'last_name'  => $last_name,
			'email'      => $email,
			'phone'      => $phone,
			'address'    => $address,
		);

		// Cache and return.
		$this->identities[ $ref ] = $identity;
		return $identity;
	}

	/**
	 * Generate first name
	 *
	 * @param string $gender Gender ('male' or 'female').
	 * @return string First name.
	 */
	public function generate_first_name( string $gender ): string {
		$names = $gender === 'male' ? self::$male_first_names : self::$female_first_names;
		return $names[ mt_rand( 0, count( $names ) - 1 ) ];
	}

	/**
	 * Generate infix (tussenvoegsel)
	 *
	 * @return string|null Infix or null.
	 */
	public function generate_infix(): ?string {
		// 40% chance of having an infix.
		if ( mt_rand( 1, 100 ) > 40 ) {
			return null;
		}

		// Weighted selection.
		$total_weight = array_sum( self::$infixes );
		$random       = mt_rand( 1, $total_weight );
		$current      = 0;

		foreach ( self::$infixes as $infix => $weight ) {
			$current += $weight;
			if ( $random <= $current ) {
				return $infix;
			}
		}

		return 'van'; // Fallback.
	}

	/**
	 * Generate last name
	 *
	 * @return string Last name.
	 */
	public function generate_last_name(): string {
		return self::$last_names[ mt_rand( 0, count( self::$last_names ) - 1 ) ];
	}

	/**
	 * Generate Dutch phone number
	 *
	 * @return string Phone number.
	 */
	public function generate_phone(): string {
		// 70% mobile, 30% landline.
		if ( mt_rand( 1, 100 ) <= 70 ) {
			// Mobile: 06-XXXXXXXX.
			return '06-' . str_pad( (string) mt_rand( 10000000, 99999999 ), 8, '0', STR_PAD_LEFT );
		} else {
			// Landline: 0XX-XXXXXXX (area codes 010-088).
			$area_code = str_pad( (string) mt_rand( 10, 88 ), 2, '0', STR_PAD_LEFT );
			$number    = str_pad( (string) mt_rand( 1000000, 9999999 ), 7, '0', STR_PAD_LEFT );
			return "0{$area_code}-{$number}";
		}
	}

	/**
	 * Generate email address
	 *
	 * @param string      $first_name First name.
	 * @param string|null $infix Infix (tussenvoegsel).
	 * @param string      $last_name Last name.
	 * @return string Email address.
	 */
	public function generate_email( string $first_name, ?string $infix, string $last_name ): string {
		// Normalize components.
		$first = $this->normalize_for_email( $first_name );
		$last  = $this->normalize_for_email( $last_name );

		// Pick email format.
		$format = mt_rand( 1, 4 );
		switch ( $format ) {
			case 1:
				$local = "{$first}.{$last}";
				break;
			case 2:
				$local = substr( $first, 0, 1 ) . ".{$last}";
				break;
			case 3:
				$local = "{$first}{$last}";
				break;
			case 4:
				$local = substr( $first, 0, 1 ) . $last;
				break;
			default:
				$local = "{$first}.{$last}";
		}

		// Pick domain.
		$domain = self::$email_domains[ mt_rand( 0, count( self::$email_domains ) - 1 ) ];

		return strtolower( $local ) . '@' . $domain;
	}

	/**
	 * Generate Dutch address
	 *
	 * @return array Address with keys: street, house_number, postal_code, city.
	 */
	public function generate_address(): array {
		$street       = self::$streets[ mt_rand( 0, count( self::$streets ) - 1 ) ];
		$house_number = (string) mt_rand( 1, 250 );
		$postal_code  = mt_rand( 1000, 9999 ) . ' ' . chr( mt_rand( 65, 90 ) ) . chr( mt_rand( 65, 90 ) );
		$city         = self::$cities[ mt_rand( 0, count( self::$cities ) - 1 ) ];

		return array(
			'street'       => $street,
			'house_number' => $house_number,
			'postal_code'  => $postal_code,
			'city'         => $city,
		);
	}

	/**
	 * Generate fake relatiecode (Sportlink member number)
	 *
	 * @return string 7-digit relatiecode.
	 */
	public function generate_relatiecode(): string {
		return str_pad( (string) mt_rand( 1000000, 9999999 ), 7, '0', STR_PAD_LEFT );
	}

	/**
	 * Normalize string for email
	 *
	 * @param string $string Input string.
	 * @return string Normalized string.
	 */
	private function normalize_for_email( string $string ): string {
		// Convert to lowercase.
		$string = strtolower( $string );

		// Remove diacritics.
		$string = iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $string );

		// Remove non-alphanumeric characters.
		$string = preg_replace( '/[^a-z0-9]/', '', $string );

		return $string;
	}
}
