<?php
/**
 * Canonical field value formatting and sanitization.
 *
 * @package Rondo\Fields
 */

namespace Rondo\Fields;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Formatter {

	/**
	 * Format a canonical-name payload for the REST wire contract.
	 *
	 * @param array<string,mixed> $payload Canonical payload.
	 * @return array<string,mixed>
	 */
	public static function for_wire( string $context, array $payload ): array {
		$formatted = [];
		foreach ( $payload as $name => $value ) {
			$definition         = Registry::resolve( $context, (string) $name );
			$formatted[ $name ] = self::format_value( $definition, $value );
		}
		return $formatted;
	}

	/**
	 * Validate and normalize a canonical write payload before name mapping.
	 *
	 * @param array<string,mixed> $payload Canonical payload.
	 * @return array<string,mixed>
	 */
	public static function for_storage( string $context, array $payload ): array {
		$normalized = [];
		foreach ( $payload as $name => $value ) {
			try {
				$definition = Registry::resolve( $context, (string) $name );
			} catch ( InvalidArgumentException $error ) {
				throw new InvalidArgumentException( 'fields.' . $name . ': ' . $error->getMessage(), 0, $error );
			}
			$path                = 'fields.' . $definition['canonical_name'];
			$normalized[ $name ] = self::sanitize_value( $definition, $value, $path );
		}
		return $normalized;
	}

	/**
	 * Format one stored value for the canonical wire.
	 *
	 * @param array<string,mixed> $definition Field definition.
	 * @param mixed               $value Value.
	 * @return mixed
	 */
	private static function format_value( array $definition, $value ) {
		$type = $definition['type'];

		if ( $type === 'select' && ! empty( $definition['multiple'] ) ) {
			return is_array( $value ) ? array_values( array_map( 'strval', $value ) ) : [];
		}
		if ( in_array( $type, [ 'repeater', 'relationship', 'gallery', 'checkbox' ], true ) && ! is_array( $value ) ) {
			return [];
		}
		if ( in_array( $type, [ 'post_object', 'taxonomy', 'file', 'image' ], true ) && ( $value === null || $value === '' || $value === false ) ) {
			return null;
		}
		if ( in_array( $type, [ 'select', 'radio' ], true ) && $value === '' && ! empty( $definition['allow_null'] ) ) {
			return null;
		}
		if ( $type === 'number' && $value === '' ) {
			return null;
		}

		if ( $value === null || $value === '' ) {
			if ( in_array( $type, [ 'date_picker', 'date_time_picker', 'time_picker' ], true ) ) {
				return null;
			}
			return $value;
		}

		if ( $type === 'repeater' && is_array( $value ) ) {
			$rows = [];
			foreach ( $value as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$formatted_row = [];
				foreach ( $row as $name => $child_value ) {
					$child                  = Registry::resolve_sub_field( $definition, (string) $name );
					$formatted_row[ $name ] = self::format_value( $child, $child_value );
				}
				$rows[] = $formatted_row;
			}
			return $rows;
		}
		if ( $type === 'gallery' ) {
			return array_values(
				array_filter(
					array_map(
						static fn( $attachment ) => self::format_attachment( $definition, $attachment ),
						$value
					)
				)
			);
		}

		switch ( $type ) {
			case 'date_picker':
				$date = self::parse_date( (string) $value );
				return $date ? $date->format( 'Y-m-d' ) : null;
			case 'date_time_picker':
				$date = self::parse_datetime( (string) $value, self::site_timezone() );
				return $date ? $date->format( DateTimeInterface::RFC3339 ) : null;
			case 'time_picker':
				return preg_match( '/^(\d{2}):(\d{2})/', (string) $value, $matches )
					? $matches[1] . ':' . $matches[2]
					: null;
			case 'true_false':
				return (bool) $value;
			case 'number':
				return is_numeric( $value ) ? ( (float) $value === (float) (int) $value ? (int) $value : (float) $value ) : null;
			case 'post_object':
			case 'taxonomy':
				if ( ! empty( $definition['multiple'] ) ) {
					return is_array( $value )
						? array_values( array_filter( array_map( [ self::class, 'object_id' ], $value ) ) )
						: [];
				}
				return self::object_id( $value );
			case 'relationship':
				if ( ! is_array( $value ) ) {
					return [];
				}
				return array_values( array_filter( array_map( [ self::class, 'object_id' ], $value ) ) );
			case 'file':
			case 'image':
				return self::format_attachment( $definition, $value );
			default:
				return $value;
		}
	}

	/**
	 * Sanitize one canonical value for native storage.
	 *
	 * @param array<string,mixed> $definition Field definition.
	 * @param mixed               $value Value.
	 * @return mixed
	 */
	private static function sanitize_value( array $definition, $value, string $path ) {
		$type = $definition['type'];

		if ( ! empty( $definition['read_only'] ) ) {
			throw new InvalidArgumentException( "{$path} is read-only." );
		}

		if ( ! empty( $definition['required'] ) && ( $value === null || $value === '' || $value === [] ) ) {
			throw new InvalidArgumentException( "{$path} is required." );
		}

		if ( $value === null ) {
			return in_array( $type, [ 'repeater', 'relationship', 'gallery', 'checkbox' ], true ) ? [] : '';
		}

		if ( $type === 'repeater' ) {
			if ( ! is_array( $value ) || ! array_is_list( $value ) ) {
				throw new InvalidArgumentException( "{$path} must be an array." );
			}
			self::validate_count( $definition, count( $value ), $path );
			$rows = [];
			foreach ( $value as $index => $row ) {
				if ( ! is_array( $row ) ) {
					throw new InvalidArgumentException( "{$path}.{$index} must be an object." );
				}
				$normalized_row = [];
				foreach ( $row as $name => $child_value ) {
					$child                   = Registry::resolve_sub_field( $definition, (string) $name );
					$normalized_row[ $name ] = self::sanitize_value( $child, $child_value, "{$path}.{$index}.{$child['canonical_name']}" );
				}
				$rows[] = $normalized_row;
			}
			return $rows;
		}
		if ( $type === 'gallery' ) {
			if ( ! is_array( $value ) ) {
				throw new InvalidArgumentException( "{$path} must be an array of attachment IDs." );
			}
			self::validate_count( $definition, count( $value ), $path );
			return array_values( array_filter( array_map( [ self::class, 'object_id' ], $value ) ) );
		}

		switch ( $type ) {
			case 'date_picker':
				$date = self::parse_date( (string) $value );
				if ( ! $date ) {
					throw new InvalidArgumentException( "{$path} must use YYYY-MM-DD." );
				}
				return $date->format( 'Ymd' );
			case 'date_time_picker':
				if ( ! is_string( $value ) || ! preg_match( '/(?:Z|[+-]\d{2}:\d{2})$/', $value ) ) {
					throw new InvalidArgumentException( "{$path} must be RFC 3339 with an explicit timezone." );
				}
				$date = self::parse_datetime( $value, null );
				if ( ! $date ) {
					throw new InvalidArgumentException( "{$path} must be a valid RFC 3339 timestamp." );
				}
				return $date->setTimezone( self::site_timezone() )->format( 'Y-m-d H:i:s' );
			case 'time_picker':
				if ( ! is_string( $value ) || ! preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value ) ) {
					throw new InvalidArgumentException( "{$path} must use HH:mm." );
				}
				return $value . ':00';
			case 'true_false':
				if ( ! is_bool( $value ) && ! in_array( $value, [ 0, 1, '0', '1' ], true ) ) {
					throw new InvalidArgumentException( "{$path} must be boolean." );
				}
				return $value ? 1 : 0;
			case 'number':
				if ( $value !== '' && ! is_numeric( $value ) ) {
					throw new InvalidArgumentException( "{$path} must be numeric." );
				}
				if ( $value === '' ) {
					return '';
				}
				$number = (float) $value;
				if ( isset( $definition['min'] ) && $definition['min'] !== '' && $number < (float) $definition['min'] ) {
					throw new InvalidArgumentException( "{$path} is below the minimum." );
				}
				if ( isset( $definition['max'] ) && $definition['max'] !== '' && $number > (float) $definition['max'] ) {
					throw new InvalidArgumentException( "{$path} exceeds the maximum." );
				}
				return $number;
			case 'post_object':
			case 'taxonomy':
				if ( ! empty( $definition['multiple'] ) ) {
					if ( ! is_array( $value ) ) {
						throw new InvalidArgumentException( "{$path} must be an array of IDs." );
					}
					return array_values( array_map( 'absint', $value ) );
				}
				if ( ! is_numeric( $value ) || (int) $value < 0 ) {
					throw new InvalidArgumentException( "{$path} must be an ID." );
				}
				return absint( $value );
			case 'relationship':
				if ( ! is_array( $value ) ) {
					throw new InvalidArgumentException( "{$path} must be an array of IDs." );
				}
				self::validate_count( $definition, count( $value ), $path );
				return array_values( array_filter( array_map( 'absint', $value ) ) );
			case 'file':
			case 'image':
				$attachment_id = self::object_id( $value );
				if ( ! $attachment_id ) {
					throw new InvalidArgumentException( "{$path} must be an attachment ID." );
				}
				return $attachment_id;
			case 'select':
				if ( ! empty( $definition['multiple'] ) ) {
					if ( ! is_array( $value ) ) {
						throw new InvalidArgumentException( "{$path} must be an array." );
					}
					$choices = [];
					foreach ( $value as $choice ) {
						if ( isset( $definition['choices'] ) && ! array_key_exists( (string) $choice, $definition['choices'] ) ) {
							throw new InvalidArgumentException( "{$path} contains an unknown choice." );
						}
						$choices[] = sanitize_text_field( (string) $choice );
					}
					return $choices;
				}
				// no break
			case 'radio':
				if ( $value === '' && ! empty( $definition['allow_null'] ) ) {
					return '';
				}
				if ( isset( $definition['choices'] ) && ! array_key_exists( (string) $value, $definition['choices'] ) ) {
					throw new InvalidArgumentException( "{$path} contains an unknown choice." );
				}
				return sanitize_text_field( (string) $value );
			case 'checkbox':
				if ( ! is_array( $value ) ) {
					throw new InvalidArgumentException( "{$path} must be an array." );
				}
				return array_values( array_map( 'sanitize_text_field', $value ) );
			case 'url':
				return esc_url_raw( (string) $value );
			case 'email':
				$email = sanitize_email( (string) $value );
				if ( $value !== '' && $email === '' ) {
					throw new InvalidArgumentException( "{$path} must be a valid email address." );
				}
				return $email;
			case 'textarea':
				return sanitize_textarea_field( (string) $value );
			case 'wysiwyg':
				return wp_kses_post( (string) $value );
			default:
				$sanitized = is_string( $value ) ? sanitize_text_field( $value ) : $value;
				if ( is_string( $sanitized ) && ! empty( $definition['maxlength'] ) && mb_strlen( $sanitized ) > (int) $definition['maxlength'] ) {
					throw new InvalidArgumentException( "{$path} exceeds the maximum length." );
				}
				return $sanitized;
		}
	}

	private static function validate_count( array $definition, int $count, string $path ): void {
		if ( ! empty( $definition['min'] ) && $count < (int) $definition['min'] ) {
			throw new InvalidArgumentException( "{$path} has too few items." );
		}
		if ( ! empty( $definition['max'] ) && $count > (int) $definition['max'] ) {
			throw new InvalidArgumentException( "{$path} has too many items." );
		}
	}

	private static function parse_date( string $value ): ?DateTimeImmutable {
		$format = preg_match( '/^\d{8}$/', $value ) ? '!Ymd' : '!Y-m-d';
		$date   = DateTimeImmutable::createFromFormat( $format, $value, new DateTimeZone( 'UTC' ) );
		$errors = DateTimeImmutable::getLastErrors();
		if ( ! $date || ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) ) {
			return null;
		}
		return $date;
	}

	private static function parse_datetime( string $value, ?DateTimeZone $timezone ): ?DateTimeImmutable {
		try {
			return new DateTimeImmutable( $value, $timezone );
		} catch ( \Exception $error ) {
			return null;
		}
	}

	private static function site_timezone(): DateTimeZone {
		return function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );
	}

	/** @param mixed $value @return array<string,mixed>|int|string|null */
	private static function format_attachment( array $definition, $value ) {
		$attachment_id = self::object_id( $value );
		if ( ! $attachment_id ) {
			return null;
		}

		$return_format = $definition['return_format'] ?? 'array';
		if ( $return_format === 'id' ) {
			return $attachment_id;
		}
		$url = wp_get_attachment_url( $attachment_id );
		if ( $return_format === 'url' ) {
			return $url ?: '';
		}

		$post = get_post( $attachment_id );
		if ( ! $post || $post->post_type !== 'attachment' ) {
			return null;
		}
		$metadata  = wp_get_attachment_metadata( $attachment_id );
		$mime_type = (string) get_post_mime_type( $attachment_id );
		$type_bits = array_pad( explode( '/', $mime_type, 2 ), 2, '' );

		return [
			'ID'          => $attachment_id,
			'id'          => $attachment_id,
			'title'       => $post->post_title,
			'filename'    => wp_basename( (string) get_attached_file( $attachment_id ) ),
			'filesize'    => is_file( (string) get_attached_file( $attachment_id ) ) ? filesize( (string) get_attached_file( $attachment_id ) ) : 0,
			'url'         => $url ?: '',
			'link'        => get_attachment_link( $attachment_id ),
			'alt'         => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'author'      => (string) $post->post_author,
			'description' => $post->post_content,
			'caption'     => $post->post_excerpt,
			'name'        => $post->post_name,
			'status'      => $post->post_status,
			'uploaded_to' => (int) $post->post_parent,
			'date'        => $post->post_date,
			'modified'    => $post->post_modified,
			'menu_order'  => (int) $post->menu_order,
			'mime_type'   => $mime_type,
			'type'        => $type_bits[0],
			'subtype'     => $type_bits[1],
			'icon'        => wp_mime_type_icon( $attachment_id ),
			'width'       => is_array( $metadata ) ? (int) ( $metadata['width'] ?? 0 ) : 0,
			'height'      => is_array( $metadata ) ? (int) ( $metadata['height'] ?? 0 ) : 0,
			'sizes'       => self::attachment_sizes( $attachment_id, is_array( $metadata ) ? $metadata : [] ),
		];
	}

	/** @return array<string,array<string,mixed>> */
	private static function attachment_sizes( int $attachment_id, array $metadata ): array {
		$sizes = [];
		foreach ( $metadata['sizes'] ?? [] as $name => $details ) {
			$source = wp_get_attachment_image_src( $attachment_id, $name );
			if ( ! $source ) {
				continue;
			}
			$sizes[ $name ] = [
				'url'    => $source[0],
				'width'  => (int) $source[1],
				'height' => (int) $source[2],
			];
		}
		return $sizes;
	}

	/** @param mixed $value */
	private static function object_id( $value ): int {
		if ( is_object( $value ) ) {
			return absint( $value->ID ?? $value->term_id ?? 0 );
		}
		if ( is_array( $value ) ) {
			return absint( $value['ID'] ?? $value['id'] ?? $value['term_id'] ?? 0 );
		}
		return absint( $value );
	}
}
