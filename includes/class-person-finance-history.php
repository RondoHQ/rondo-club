<?php
/** Stored financial history. Reading old seasons never recalculates fees. */
namespace Rondo\Finance;

use Rondo\Fields\Fields;
use Rondo\Fees\SeasonKey;

class PersonFinanceHistory {

	public static function valid_season( string $season ): bool {
		return preg_match( '/^(\d{4})-(\d{4})$/', $season, $parts ) && (int) $parts[2] === (int) $parts[1] + 1;
	}

	public static function invoice_season( int $id ): ?string {
		$season = (string) get_post_meta( $id, '_invoice_season', true );
		if ( self::valid_season( $season ) ) {
			return $season;
		}
		// Linked credits retain the source's explicitly unknown season as well.
		if ( (int) get_post_meta( $id, '_credit_source_invoice_id', true ) > 0 ) {
			return null;
		}
		// A membership invoice's creation date does not identify its billing season.
		if ( Fields::get_for_post( $id, 'invoice_type' ) === 'membership' ) {
			return null;
		}
		return SeasonKey::current( get_post_field( 'post_date', $id ) );
	}

	public static function invoices( int $person_id ): array {
		return get_posts(
			[
				'post_type'      => 'rondo_invoice',
				'post_status'    => [ 'rondo_draft', 'rondo_sent', 'rondo_paid', 'rondo_overdue', 'rondo_cancelled' ],
				'posts_per_page' => -1,
				'meta_query'     => [
					[
						'key'   => 'person',
						'value' => $person_id,
					],
				],
			]
			);
	}

	public static function summary( \WP_Post $post ): array {
		$items = Fields::get_for_post( $post->ID, 'line_items' ) ?: [];
		return [
			'id'                => $post->ID,
			'invoice_number'    => Fields::get_for_post( $post->ID, 'invoice_number' ),
			'invoice_type'      => Fields::get_for_post( $post->ID, 'invoice_type' ),
			'invoice_kind'      => get_post_meta( $post->ID, '_invoice_kind', true ) ?: 'normal',
			'season'            => self::invoice_season( $post->ID ),
			'status'            => str_replace( 'rondo_', '', $post->post_status ),
			'total_amount'      => (float) Fields::get_for_post( $post->ID, 'total_amount' ),
			'description'       => $items[0]['description'] ?? '',
			'source_invoice_id' => (int) get_post_meta( $post->ID, '_credit_source_invoice_id', true ),
			'credit_reason'     => (string) get_post_meta( $post->ID, '_credit_reason', true ),
			'reminder_count'    => (int) (bool) get_post_meta( $post->ID, '_invoice_reminder_1_sent_at', true ) + (int) (bool) get_post_meta( $post->ID, '_invoice_reminder_2_sent_at', true ),
		];
	}

	public static function get( int $person_id, string $season ) {
		if ( get_post_type( $person_id ) !== 'person' || ! \Rondo\Core\AccessControl::can_view_person( $person_id, get_current_user_id() ) ) {
			return new \WP_Error( 'finance_person_not_found', 'Lid niet gevonden.', [ 'status' => 404 ] );
		}
		if ( ! self::valid_season( $season ) ) {
			return new \WP_Error( 'finance_invalid_season', 'Kies een geldig seizoen.', [ 'status' => 400 ] );
		}
		$seasons = [ SeasonKey::current() ];
		foreach ( array_keys( get_post_meta( $person_id ) ) as $key ) {
			if ( preg_match( '/^_nikki_(\d{4})_(total|saldo)$/', $key, $matches ) ) {
				$seasons[] = $matches[1] . '-' . ( (int) $matches[1] + 1 );
			} elseif ( preg_match( '/^fee_snapshot_(\d{4}-\d{4})$/', $key, $matches ) && self::valid_season( $matches[1] ) ) {
				$seasons[] = $matches[1];
			}
		}
		$invoices         = [];
		$unassigned       = [];
		$total            = 0;
		$paid             = 0;
		$membership_count = 0;
		foreach ( self::invoices( $person_id ) as $post ) {
			$row = self::summary( $post );
			if ( $row['season'] === null ) {
				$unassigned[] = $row;
				continue;
			}
			$seasons[] = $row['season'];
			if ( $row['season'] !== $season ) {
				continue;
			}
			$invoices[] = $row;
			if ( $row['invoice_type'] !== 'membership' || $row['invoice_kind'] === 'credit' || $row['status'] === 'cancelled' ) {
				continue;
			}
			if ( $row['status'] === 'draft' ) {
				continue;
			}
			++$membership_count;
			$amount = (int) round( $row['total_amount'] * 100 );
			$total += $amount;
			if ( $row['status'] === 'paid' ) {
				$paid += $amount;
			} else {
				$installment_paid  = 0;
				$installment_count = (int) get_post_meta( $post->ID, '_installment_count', true );
				for ( $n = 1; $n <= $installment_count; ++$n ) {
					if ( get_post_meta( $post->ID, '_installment_' . $n . '_status', true ) === 'betaald' ) {
						$installment_paid += (int) round( (float) get_post_meta( $post->ID, '_installment_' . $n . '_amount', true ) * 100 );
					}
				}
				$paid += min( $amount, max( 0, $installment_paid ) );
			}
		}
		$year          = substr( $season, 0, 4 );
		$nikki_total   = get_post_meta( $person_id, '_nikki_' . $year . '_total', true );
		$nikki_balance = get_post_meta( $person_id, '_nikki_' . $year . '_saldo', true );
		$nikki         = is_numeric( $nikki_total ) ? [
			'total'   => (float) $nikki_total,
			'balance' => is_numeric( $nikki_balance ) ? (float) $nikki_balance : null,
		] : null;
		$source        = $membership_count > 0 ? 'rondo' : ( $nikki ? 'nikki' : null );
		$seasons       = array_values( array_unique( $seasons ) );
		rsort( $seasons );
		return [
			'person_id'           => $person_id,
			'person_name'         => get_the_title( $person_id ),
			'season'              => $season,
			'current_season'      => SeasonKey::current(),
			'seasons'             => $seasons,
			'invoices'            => $invoices,
			'unassigned_invoices' => $unassigned,
			'nikki'               => $nikki,
			'source'              => $source,
			'contribution_total'  => $source === 'rondo' ? $total / 100 : ( $nikki['total'] ?? null ),
			'contribution_paid'   => $source === 'rondo' ? $paid / 100 : ( $nikki && $nikki['balance'] !== null ? min( $nikki['total'], max( 0, $nikki['total'] - $nikki['balance'] ) ) : null ),
			'snapshot'            => \Rondo\Fees\FeeServices::fee_cache()->get_fee_snapshot( $person_id, $season ),
		];
	}
}
