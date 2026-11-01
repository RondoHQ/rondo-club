<?php
/** Validated, ordered questionnaire documents and server-side branching. */
namespace Rondo\Surveys;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Schema {
	public static function error( string $message, int $status = 400 ): \WP_Error {
		return new \WP_Error( 'survey_invalid', $message, [ 'status' => $status ] );
	}

	/** Only backward references are accepted, making cycles impossible. */
	public static function validate( $document ) {
		if ( ! is_array( $document ) || strlen( (string) wp_json_encode( $document ) ) > 512000 || empty( $document['sections'] ) || ! is_array( $document['sections'] ) || count( $document['sections'] ) > 40 ) {
			return self::error( 'Voeg 1 tot 40 secties toe.' );
		}
		$seen  = [];
		$ids   = [];
		$out   = [ 'sections' => [] ];
		$total = 0;
		foreach ( $document['sections'] as $section ) {
			if ( ! is_array( $section ) || ! self::valid_id( $section['id'] ?? null ) || isset( $ids[ $section['id'] ] ) || ( ! is_string( $section['title'] ?? null ) || trim( $section['title'] ) === '' ) ) {
				return self::error( 'Iedere sectie heeft een unieke sleutel en een titel nodig.' );
			}
			$ids[ $section['id'] ] = true;
			$condition             = self::condition( $section['condition'] ?? null, $seen );
			if ( is_wp_error( $condition ) ) {
				return $condition;
			}
			$clean = [
				'id'          => $section['id'],
				'title'       => sanitize_text_field( $section['title'] ),
				'description' => sanitize_textarea_field( $section['description'] ?? '' ),
				'condition'   => $condition,
				'questions'   => [],
			];
			if ( ! isset( $section['questions'] ) || ! is_array( $section['questions'] ) || ! $section['questions'] ) {
				return self::error( 'Iedere sectie moet minstens één vraag bevatten.' );
			}
			foreach ( $section['questions'] as $question ) {
				if ( ++$total > 200 || ! is_array( $question ) || ! self::valid_id( $question['id'] ?? null ) || isset( $ids[ $question['id'] ] ) || ( ! is_string( $question['label'] ?? null ) || trim( $question['label'] ) === '' ) ) {
					return self::error( 'Vragen hebben een unieke sleutel en vraagtekst nodig (maximaal 200 vragen).' );
				}
				$type = $question['type'] ?? '';
				if ( ! in_array( $type, [ 'single', 'multiple', 'scale', 'text' ], true ) ) {
					return self::error( 'Onbekend vraagtype.' );
				}
				$rule = self::condition( $question['condition'] ?? null, $seen );
				if ( is_wp_error( $rule ) ) {
					return $rule;
				}
				$q = [
					'id'          => $question['id'],
					'label'       => sanitize_text_field( $question['label'] ),
					'type'        => $type,
					'required'    => ! empty( $question['required'] ),
					'description' => sanitize_textarea_field( $question['description'] ?? '' ),
					'condition'   => $rule,
					'options'     => [],
				];
				if ( $type !== 'text' ) {
					$options = $question['options'] ?? [];
					if ( ! is_array( $options ) || count( $options ) < 2 || count( $options ) > 40 ) {
						return self::error( 'Een keuzevraag heeft 2 tot 40 antwoordopties nodig.' );
					}
					$keys = [];
					foreach ( $options as $option ) {
						if ( ! is_array( $option ) || ! self::valid_id( $option['id'] ?? null ) || isset( $keys[ $option['id'] ] ) || ( ! is_string( $option['label'] ?? null ) || trim( $option['label'] ) === '' ) ) {
							return self::error( 'Antwoordopties hebben een unieke sleutel en tekst nodig.' );
						}
						$keys[ $option['id'] ] = true;
						$q['options'][]        = [
							'id'    => $option['id'],
							'label' => sanitize_text_field( $option['label'] ),
						];
					}
				}
				$ids[ $q['id'] ]      = true;
				$seen[ $q['id'] ]     = $q;
				$clean['questions'][] = $q;
			}
			$out['sections'][] = $clean;
		}
		return $out;
	}

	private static function valid_id( $id ): bool {
		return is_string( $id ) && preg_match( '/^[a-zA-Z][a-zA-Z0-9_-]{0,63}$/D', $id );
	}

	private static function condition( $condition, array $seen ) {
		if ( $condition === null || $condition === [] ) {
			return null;
		}
		if ( ! is_array( $condition ) || ! is_string( $condition['question'] ?? null ) || ! isset( $seen[ $condition['question'] ?? '' ] ) ) {
			return self::error( 'Een zichtbaarheidsregel moet naar een eerdere keuzevraag verwijzen.' );
		}
		$q = $seen[ $condition['question'] ];
		if ( $q['type'] === 'text' || ! in_array( $condition['value'] ?? null, array_column( $q['options'], 'id' ), true ) ) {
			return self::error( 'De antwoordoptie in een zichtbaarheidsregel bestaat niet.' );
		}
		return [
			'question' => $q['id'],
			'value'    => $condition['value'],
		];
	}

	public static function visible( ?array $rule, array $answers ): bool {
		if ( ! $rule ) {
			return true;
		}
		$value = $answers[ $rule['question'] ] ?? null;
		return is_array( $value ) ? in_array( $rule['value'], $value, true ) : $value === $rule['value'];
	}

	/** Discard hidden answers before evaluating later rules. */
	public static function answers( array $document, $answers ) {
		if ( ! is_array( $answers ) || count( $answers ) > 200 ) {
			return self::error( 'Ongeldige antwoorden.' );
		}
		$clean = [];
		foreach ( $document['sections'] as $section ) {
			if ( ! self::visible( $section['condition'], $clean ) ) {
				continue;
			}
			foreach ( $section['questions'] as $q ) {
				if ( ! self::visible( $q['condition'], $clean ) ) {
					continue;
				}
				$value = $answers[ $q['id'] ] ?? null;
				if ( $value === null || $value === '' || $value === [] ) {
					if ( $q['required'] ) {
						return self::error( 'Vul de vraag in: ' . $q['label'] );
					}
					continue;
				}
				if ( $q['type'] === 'text' ) {
					if ( ! is_string( $value ) || mb_strlen( $value ) > 10000 ) {
						return self::error( 'Een tekstantwoord mag maximaal 10.000 tekens bevatten.' );
					}
					$value = sanitize_textarea_field( $value );
					if ( $q['required'] && trim( $value ) === '' ) {
						return self::error( 'Vul de vraag in: ' . $q['label'] );
					}
				} else {
					$allowed = array_column( $q['options'], 'id' );
					if ( $q['type'] === 'multiple' ) {
						if ( ! is_array( $value ) || count( $value ) > count( $allowed ) ) {
							return self::error( 'Ongeldige meerkeuzeantwoorden.' );
						}
						foreach ( $value as $choice ) {
							if ( ! is_string( $choice ) || ! in_array( $choice, $allowed, true ) ) {
								return self::error( 'Onbekende antwoordoptie.' );
							}
						}
						$value = array_values( array_unique( $value ) );
					} elseif ( ! is_string( $value ) || ! in_array( $value, $allowed, true ) ) {
						return self::error( 'Onbekende antwoordoptie.' );
					}
				}
				$clean[ $q['id'] ] = $value;
			}
		}
		return $clean;
	}
}
