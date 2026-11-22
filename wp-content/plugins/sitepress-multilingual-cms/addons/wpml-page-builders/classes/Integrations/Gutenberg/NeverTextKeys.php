<?php

namespace WPML\PB\Gutenberg;

final class NeverTextKeys {

	const EXACT = [ 'classname', 'class', 'cssclass', 'anchor', 'htmlanchor', 'id', 'uniqueid', 'clientid' ];

	const SUFFIX_PATTERN = '/[a-z0-9](?:ClassName|Classes|Id|Ids)$/';

	public static function matches( string $key ): bool {
		return in_array( strtolower( $key ), self::EXACT, true )
			|| (bool) preg_match( self::SUFFIX_PATTERN, $key );
	}
}
