<?php
/**
 * Twelve daily revenue report parser.
 *
 * Parses the plain-text extraction of the PDF that Twelve (twelve.eu) emails
 * every morning with the previous day's kassa omzet. The parser is pure PHP
 * on purpose: no WordPress dependencies, so it can be unit tested in
 * isolation and reused anywhere.
 *
 * Expected input: the text of the PDF, e.g. via smalot/pdfparser
 * (Parser::parseContent($pdf_bytes)->getText()) or `pdftotext`.
 *
 * Output shape (all amounts as float, euro):
 *
 * [
 *   'club'          => 'AWC Wijchen, SV',
 *   'period_start'  => '2026-09-29 06:00',
 *   'period_end'    => '2026-09-30 06:00',
 *   'omzet'         => [ [ 'section', 'label', 'bedrag', 'netto', 'hoog', 'laag', 'transacties' ], ... ],
 *   'no_sale'       => [ [ 'label', 'aantal' ], ... ],
 *   'rekeningen'    => [ 'bijboekingen', 'afboekingen', 'totaal' ],
 *   'betaalwijze'   => [ [ 'methode', 'totaal', 'types' => [ [ 'type', 'bedrag' ] ] ], ... ],
 *   'terminals'     => [ [ 'terminal', 'betaald', 'transacties' ], ... ],
 *   'terminals_totaal' => [ 'betaald', 'transacties' ],
 *   'producten'     => [ [ 'product', 'bruto', 'btw', 'netto', 'btw_groep', 'aantal' ], ... ],
 *   'producten_totaal' => [ 'bruto', 'btw', 'netto', 'aantal' ],
 *   'bonnen'        => [ 'op_bon_gezet', 'saldo' ],
 *   'cashflow'      => [ 'subtotaal', 'beginsaldo', 'totaal' ],
 * ]
 *
 * Sections that are absent or empty in a given report yield empty arrays;
 * unknown row labels are kept with section 'unknown' instead of failing.
 */

namespace Rondo\Twelve;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReportParser {

	/**
	 * Main section headers as they appear in the PDF text.
	 */
	private const SECTIONS = [
		'Omzetoverzicht',
		'No sale, ingehouden op omzet',
		'Mutaties rekeningen',
		'Op- en afwaardering',
		'Betaalwijze',
		'Uitgave per virtuele terminal',
		'Uitgave per product',
		'Bonnen',
		'Cashflow',
	];

	/**
	 * Known Omzetoverzicht row labels mapped to their section.
	 */
	private const OMZET_SECTIONS = [
		'Omzet (excl. no-sale)'   => 'totaal',
		'Bestuur'                 => 'categorie',
		'Breuk en bederf'         => 'categorie',
		'Businessclub'            => 'categorie',
		'Verbruik kantinedienst'  => 'categorie',
		'Munten over/onderwaarde' => 'categorie',
		'Omzet munten'            => 'betaalmethode',
		'Omzet betaalpas'         => 'betaalmethode',
		'Omzet contant'           => 'betaalmethode',
		'Omzet rekening'          => 'betaalmethode',
		'Omzet pin'               => 'betaalmethode',
		'Subtotaal'               => 'subtotaal',
	];

	/**
	 * Parse the extracted PDF text into a structured array.
	 *
	 * @param string $text Plain-text extraction of the Twelve PDF.
	 * @return array Structured report data.
	 * @throws ReportParserException When the header or period cannot be read.
	 */
	public static function parse( string $text ): array {
		$lines = self::tokenize( $text );

		if ( empty( $lines ) ) {
			throw new ReportParserException( 'Lege rapporttekst, niets te parsen.' );
		}

		$report = [
			'club'             => self::parse_club( $lines ),
			'period_start'     => self::parse_period( $lines, 'Begindatum (incl.)' ),
			'period_end'       => self::parse_period( $lines, 'Einddatum (excl.)' ),
			'omzet'            => [],
			'no_sale'          => [],
			'rekeningen'       => [
				'bijboekingen' => 0.0,
				'afboekingen'  => 0.0,
				'totaal'       => 0.0,
			],
			'betaalwijze'      => [],
			'terminals'        => [],
			'terminals_totaal' => [
				'betaald'     => 0.0,
				'transacties' => 0,
			],
			'producten'        => [],
			'producten_totaal' => [
				'bruto'  => 0.0,
				'btw'    => 0.0,
				'netto'  => 0.0,
				'aantal' => 0,
			],
			'bonnen'           => [
				'op_bon_gezet' => 0.0,
				'saldo'        => 0.0,
			],
			'cashflow'         => [
				'subtotaal'  => 0.0,
				'beginsaldo' => 0.0,
				'totaal'     => 0.0,
			],
		];

		$cursor = 0;
		$total  = count( $lines );
		while ( $cursor < $total ) {
			$line = $lines[ $cursor ];

			if ( $line === 'Omzetoverzicht' ) {
				$cursor = self::parse_omzetoverzicht( $lines, $cursor + 1, $report );
			} elseif ( $line === 'No sale, ingehouden op omzet' ) {
				$cursor = self::parse_no_sale( $lines, $cursor + 1, $report );
			} elseif ( $line === 'Mutaties rekeningen' ) {
				$cursor = self::parse_rekeningen( $lines, $cursor + 1, $report );
			} elseif ( $line === 'Betaalwijze' && self::peek( $lines, $cursor + 1 ) === 'Transactietype' ) {
				// "Betaalwijze" also occurs as a row label inside the no-sale
				// section; only treat it as a section when followed by its
				// column header.
				$cursor = self::parse_betaalwijze( $lines, $cursor + 1, $report );
			} elseif ( $line === 'Uitgave per virtuele terminal' ) {
				$cursor = self::parse_terminals( $lines, $cursor + 1, $report );
			} elseif ( $line === 'Uitgave per product' ) {
				$cursor = self::parse_producten( $lines, $cursor + 1, $report );
			} elseif ( $line === 'Bonnen' ) {
				$cursor = self::parse_bonnen( $lines, $cursor + 1, $report );
			} elseif ( $line === 'Cashflow' ) {
				$cursor = self::parse_cashflow( $lines, $cursor + 1, $report );
			} else {
				++$cursor;
			}
		}

		return $report;
	}

	/**
	 * Split raw text into a clean list of non-empty lines.
	 *
	 * @param string $text Raw extracted text.
	 * @return string[]
	 */
	private static function tokenize( string $text ): array {
		$text  = str_replace( [ "\f", "\r" ], [ "\n", '' ], $text );
		$lines = [];
		foreach ( preg_split( '/\n/', $text ) as $line ) {
			$line = trim( (string) $line );
			if ( $line === '' ) {
				continue;
			}
			// Smalot emits whole table rows; pdftotext emits separate cells.
			if ( preg_match( '/^(Begindatum \(incl\.\)|Einddatum \(excl\.\))\s*(.*)$/', $line, $match ) ) {
				$lines[] = $match[1] . ' ' . $match[2];
				continue;
			}
			if ( preg_match( '/^(Totaal bijboekingen|Totaal afboekingen|Totaal)(-?[\d.]+,\d{2})$/', $line, $match ) ) {
				$lines[] = $match[1] . ' ' . $match[2];
				continue;
			}
			$headers = [ 'BTW Type', 'Transactietype', 'Terminal', 'Product', 'Soort actie' ];
			if ( str_contains( $line, "\t" ) && in_array( explode( "\t", $line )[0], $headers, true ) ) {
				$lines[] = explode( "\t", $line )[0];
				continue;
			}
			if ( str_contains( $line, "\t" ) ) {
				$cells = preg_split( '/\t+|\s+(?=-?[\d.]+,\d{2}(?:\s|$))|(?<=\d{2})\s+(?=(?:Hoog|Laag) \d+%)/', $line );
				foreach ( $cells as $cell ) {
					$lines[] = trim( $cell );
				}
			} else {
				$lines[] = $line;
			}
		}
		// These headings occur inside the first table in Smalot extraction.
		foreach ( $lines as $index => $line ) {
			$next               = $lines[ $index + 1 ] ?? '';
			$has_amount_columns = true;
			for ( $column = 2; $column <= 5; ++$column ) {
				$has_amount_columns = $has_amount_columns && self::is_bedrag( $lines[ $index + $column ] ?? null );
			}
			if ( ( $line === 'No sale, ingehouden op omzet' && $has_amount_columns )
				|| ( $line === 'Betaalwijze' && str_starts_with( $next, 'Omzet ' ) ) ) {
				unset( $lines[ $index ] );
			}
		}
		return array_values( $lines );
	}

	/**
	 * Peek at the line at an offset without moving the cursor.
	 */
	private static function peek( array $lines, int $cursor ): ?string {
		return $lines[ $cursor ] ?? null;
	}

	/**
	 * Whether the line is a main section header (stop condition for rows).
	 */
	private static function is_section_header( ?string $line ): bool {
		if ( $line === null ) {
			return true;
		}
		// "Betaalwijze" is ambiguous, handled with lookahead by the caller.
		if ( $line === 'Betaalwijze' ) {
			return false;
		}
		return in_array( $line, self::SECTIONS, true );
	}

	/**
	 * Parse a Dutch-formatted amount ("15,75", "491.605,36") to float.
	 */
	public static function parse_bedrag( string $value ): float {
		$value = trim( $value );
		if ( preg_match( '/^-?\d{1,3}(\.\d{3})+,\d{2}$/', $value ) ) {
			$value = str_replace( '.', '', $value );
		}
		return (float) str_replace( ',', '.', $value );
	}

	/**
	 * Whether the line looks like a Dutch amount (always has ",cc").
	 */
	private static function is_bedrag( ?string $line ): bool {
		return $line !== null && (bool) preg_match( '/^-?[\d.]+,\d{2}$/', $line );
	}

	/**
	 * Whether the line is a plain integer (used for counts).
	 */
	private static function is_aantal( ?string $line ): bool {
		return $line !== null && (bool) preg_match( '/^-?\d+$/', $line );
	}

	/**
	 * Read the club name from the "Dagtotaal <club>" title line.
	 *
	 * @throws ReportParserException
	 */
	private static function parse_club( array $lines ): string {
		foreach ( $lines as $line ) {
			if ( str_starts_with( $line, 'Dagtotaal ' ) ) {
				return trim( substr( $line, strlen( 'Dagtotaal ' ) ) );
			}
		}
		throw new ReportParserException( 'Clubnaam (Dagtotaal-regel) niet gevonden in rapport.' );
	}

	/**
	 * Read a period line like "Begindatum (incl.) 29-09-2026 06:00".
	 *
	 * @throws ReportParserException
	 */
	private static function parse_period( array $lines, string $label ): string {
		foreach ( $lines as $line ) {
			if ( str_starts_with( $line, $label . ' ' ) ) {
				$raw = trim( substr( $line, strlen( $label ) + 1 ) );
				$dt  = \DateTime::createFromFormat( 'd-m-Y H:i', $raw );
				if ( $dt instanceof \DateTime ) {
					return $dt->format( 'Y-m-d H:i' );
				}
				throw new ReportParserException( 'Ongeldige datum bij "' . $label . '": ' . $raw );
			}
		}
		throw new ReportParserException( 'Periode-regel "' . $label . '" niet gevonden in rapport.' );
	}

	/**
	 * Parse the Omzetoverzicht table.
	 *
	 * Rows are: label, bedrag, nettobedrag, hoog, laag, [aantal transacties].
	 * The transaction count is a plain integer; amounts always carry ",cc".
	 */
	private static function parse_omzetoverzicht( array $lines, int $cursor, array &$report ): int {
		$total = count( $lines );

		// Skip the column headers (BTW Type / Bedrag / Nettobedrag / Hoog /
		// Laag / Aantal transacties).
		while ( $cursor < $total && ! self::is_section_header( $lines[ $cursor ] )
			&& ! self::is_bedrag( $lines[ $cursor ] )
			&& ! isset( self::OMZET_SECTIONS[ $lines[ $cursor ] ] )
			&& ! str_ends_with( $lines[ $cursor ], '(Excl. no-sale)' )
		) {
			++$cursor;
		}

		while ( $cursor < $total && ! self::is_section_header( $lines[ $cursor ] ) ) {
			$label = $lines[ $cursor ];
			++$cursor;

			$bedragen = [];
			while ( $cursor < $total && self::is_bedrag( $lines[ $cursor ] ) && count( $bedragen ) < 4 ) {
				$bedragen[] = self::parse_bedrag( $lines[ $cursor ] );
				++$cursor;
			}

			if ( count( $bedragen ) < 4 ) {
				// Card-brand details have only an amount and transaction count.
				if ( count( $bedragen ) === 1 && self::is_aantal( self::peek( $lines, $cursor ) ) ) {
					++$cursor;
					continue;
				}
				// Not a data row; step back so the dispatcher can handle it.
				$cursor -= count( $bedragen );
				break;
			}

			$transacties = null;
			if ( self::is_aantal( self::peek( $lines, $cursor ) ) ) {
				$transacties = (int) $lines[ $cursor ];
				++$cursor;
			}

			$report['omzet'][] = [
				'section'     => self::omzet_section( $label ),
				'label'       => $label,
				'bedrag'      => $bedragen[0],
				'netto'       => $bedragen[1],
				'hoog'        => $bedragen[2],
				'laag'        => $bedragen[3],
				'transacties' => $transacties,
			];
		}

		return $cursor;
	}

	/**
	 * Classify an Omzetoverzicht row label.
	 */
	private static function omzet_section( string $label ): string {
		if ( isset( self::OMZET_SECTIONS[ $label ] ) ) {
			return self::OMZET_SECTIONS[ $label ];
		}
		if ( str_ends_with( $label, '(Excl. no-sale)' ) ) {
			return 'btw_type';
		}
		return 'unknown';
	}

	/**
	 * Parse "No sale, ingehouden op omzet": (label, aantal) pairs.
	 */
	private static function parse_no_sale( array $lines, int $cursor, array &$report ): int {
		$total = count( $lines );
		while ( $cursor < $total && ! self::is_section_header( $lines[ $cursor ] ) ) {
			$label = $lines[ $cursor ];
			++$cursor;
			$aantal              = self::is_aantal( self::peek( $lines, $cursor ) ) ? (int) $lines[ $cursor++ ] : 0;
			$report['no_sale'][] = [
				'label'  => $label,
				'aantal' => $aantal,
			];
		}
		return $cursor;
	}

	/**
	 * Parse "Mutaties rekeningen": "Totaal bijboekingen 0,00" style lines.
	 */
	private static function parse_rekeningen( array $lines, int $cursor, array &$report ): int {
		$total = count( $lines );
		while ( $cursor < $total && ! self::is_section_header( $lines[ $cursor ] ) ) {
			$line = $lines[ $cursor ];
			++$cursor;
			if ( preg_match( '/^(Totaal bijboekingen|Totaal afboekingen|Totaal)\s+(-?[\d.,]+)$/', $line, $m ) ) {
				$key                          = [
					'Totaal bijboekingen' => 'bijboekingen',
					'Totaal afboekingen'  => 'afboekingen',
					'Totaal'              => 'totaal',
				][ $m[1] ];
				$report['rekeningen'][ $key ] = self::parse_bedrag( $m[2] );
			}
		}
		return $cursor;
	}

	/**
	 * Parse the "Betaalwijze" table: method, (type, bedrag) pairs, Totaal.
	 */
	private static function parse_betaalwijze( array $lines, int $cursor, array &$report ): int {
		$total = count( $lines );

		// Skip column headers (Transactietype / Bedrag).
		while ( $cursor < $total && in_array( $lines[ $cursor ], [ 'Transactietype', 'Bedrag' ], true ) ) {
			++$cursor;
		}

		while ( $cursor < $total && ! self::is_section_header( $lines[ $cursor ] ) ) {
			$methode = $lines[ $cursor ];
			++$cursor;
			$types  = [];
			$totaal = 0.0;

			while ( $cursor < $total && $lines[ $cursor ] !== 'Totaal' && ! self::is_section_header( $lines[ $cursor ] ) ) {
				$type = $lines[ $cursor ];
				++$cursor;
				$bedrag  = self::is_bedrag( self::peek( $lines, $cursor ) ) ? self::parse_bedrag( $lines[ $cursor++ ] ) : 0.0;
				$types[] = [
					'type'   => $type,
					'bedrag' => $bedrag,
				];
			}

			if ( self::peek( $lines, $cursor ) === 'Totaal' ) {
				++$cursor;
				$totaal = self::is_bedrag( self::peek( $lines, $cursor ) ) ? self::parse_bedrag( $lines[ $cursor++ ] ) : 0.0;
			}

			$report['betaalwijze'][] = [
				'methode' => $methode,
				'totaal'  => $totaal,
				'types'   => $types,
			];
		}

		return $cursor;
	}

	/**
	 * Parse "Uitgave per virtuele terminal".
	 */
	private static function parse_terminals( array $lines, int $cursor, array &$report ): int {
		$total = count( $lines );

		// Skip column headers (Terminal / Betaald / Aantal transacties).
		while ( $cursor < $total && in_array( $lines[ $cursor ], [ 'Terminal', 'Betaald', 'Aantal transacties' ], true ) ) {
			++$cursor;
		}

		while ( $cursor < $total && $lines[ $cursor ] !== 'Totaal betaald' && ! self::is_section_header( $lines[ $cursor ] ) ) {
			$label = self::is_bedrag( $lines[ $cursor ] ) ? null : $lines[ $cursor++ ];
			if ( ! self::is_bedrag( self::peek( $lines, $cursor ) ) ) {
				break;
			}
			$betaald = self::parse_bedrag( $lines[ $cursor++ ] );
			$aantal  = self::is_aantal( self::peek( $lines, $cursor ) ) ? (int) $lines[ $cursor++ ] : 0;

			if ( $label === null ) {
				$report['terminals_totaal'] = [
					'betaald'     => $betaald,
					'transacties' => $aantal,
				];
			} else {
				$report['terminals'][] = [
					'terminal'    => $label,
					'betaald'     => $betaald,
					'transacties' => $aantal,
				];
			}
		}

		if ( self::peek( $lines, $cursor ) === 'Totaal betaald' ) {
			++$cursor;
		}

		return $cursor;
	}

	/**
	 * Parse "Uitgave per product".
	 *
	 * Rows are: product, brutoprijs, btw-bedrag, nettoprijs, [btw-groep],
	 * aantal. The totals row ("Totaal incl. no-sale") has no btw-groep.
	 */
	private static function parse_producten( array $lines, int $cursor, array &$report ): int {
		$total = count( $lines );

		// Skip column headers.
		while ( $cursor < $total
			&& in_array( $lines[ $cursor ], [ 'Product', 'Brutoprijs', 'BTW bedrag', 'Nettoprijs', 'BTW groep', 'Aantal producten' ], true )
		) {
			++$cursor;
		}

		while ( $cursor < $total && ! self::is_section_header( $lines[ $cursor ] ) ) {
			$product = $lines[ $cursor ];
			++$cursor;

			$bedragen = [];
			while ( $cursor < $total && self::is_bedrag( $lines[ $cursor ] ) && count( $bedragen ) < 3 ) {
				$bedragen[] = self::parse_bedrag( $lines[ $cursor ] );
				++$cursor;
			}

			if ( count( $bedragen ) < 3 ) {
				$cursor -= count( $bedragen );
				break;
			}

			$btw_groep = null;
			if ( self::peek( $lines, $cursor ) !== null && (bool) preg_match( '/^(Hoog|Laag) \d+%$/', $lines[ $cursor ] ) ) {
				$btw_groep = $lines[ $cursor ];
				++$cursor;
			}

			$aantal = self::is_aantal( self::peek( $lines, $cursor ) ) ? (int) $lines[ $cursor++ ] : 0;

			$row = [
				'bruto'  => $bedragen[0],
				'btw'    => $bedragen[1],
				'netto'  => $bedragen[2],
				'aantal' => $aantal,
			];

			if ( $product === 'Totaal incl. no-sale' ) {
				$report['producten_totaal'] = $row;
			} else {
				$report['producten'][] = array_merge(
					[
						'product'   => $product,
						'btw_groep' => $btw_groep,
					],
					$row
					);
			}
		}

		return $cursor;
	}

	/**
	 * Parse "Bonnen": (label, bedrag) pairs.
	 */
	private static function parse_bonnen( array $lines, int $cursor, array &$report ): int {
		$total = count( $lines );

		while ( $cursor < $total && in_array( $lines[ $cursor ], [ 'Transactietype', 'Bedrag' ], true ) ) {
			++$cursor;
		}

		$map = [
			'Totaal op bon gezet' => 'op_bon_gezet',
			'Saldo bonnen'        => 'saldo',
		];
		while ( $cursor < $total && ! self::is_section_header( $lines[ $cursor ] ) ) {
			$label = $lines[ $cursor ];
			++$cursor;
			if ( isset( $map[ $label ] ) && self::is_bedrag( self::peek( $lines, $cursor ) ) ) {
				$report['bonnen'][ $map[ $label ] ] = self::parse_bedrag( $lines[ $cursor++ ] );
			}
		}

		return $cursor;
	}

	/**
	 * Parse "Cashflow": (label, bedrag) pairs.
	 */
	private static function parse_cashflow( array $lines, int $cursor, array &$report ): int {
		$total = count( $lines );

		while ( $cursor < $total && in_array( $lines[ $cursor ], [ 'Soort actie', 'Bedrag' ], true ) ) {
			++$cursor;
		}

		$map = [
			'Subtotaal'  => 'subtotaal',
			'Beginsaldo' => 'beginsaldo',
			'Totaal'     => 'totaal',
		];
		while ( $cursor < $total && ! self::is_section_header( $lines[ $cursor ] ) ) {
			$label = $lines[ $cursor ];
			++$cursor;
			if ( isset( $map[ $label ] ) && self::is_bedrag( self::peek( $lines, $cursor ) ) ) {
				$report['cashflow'][ $map[ $label ] ] = self::parse_bedrag( $lines[ $cursor++ ] );
			}
		}

		return $cursor;
	}
}
