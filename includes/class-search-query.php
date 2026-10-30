<?php
/** Search text variants for names entered with different apostrophe keys. */
namespace Rondo\Core;

final class SearchQuery {
	/** Keep exact searches and accept common keyboard/typographic apostrophes. */
	public static function apostrophe_variants( string $query ): array {
		$marks    = [ "'", '’', '‘', '´', '`' ];
		$variants = [ $query ];
		foreach ( $marks as $mark ) {
			$variants[] = str_replace( $marks, $mark, $query );
		}
		return array_values( array_unique( $variants ) );
	}
}
