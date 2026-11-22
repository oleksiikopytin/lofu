<?php

namespace WPML\Media;

use WPML\Element\API\PostTranslations;
use WPML\FP\Obj;

class FrontendHooks implements \IWPML_Frontend_Action {

	private static $translatingCaption = false;

	public function add_hooks() {
		add_filter( 'wp_get_attachment_caption', [ __CLASS__, 'filterCaption' ], 10, 2 );
	}

	public static function filterCaption( $caption, $postId ) {
		if ( self::$translatingCaption ) {
			return $caption;
		}
		self::$translatingCaption = true;
		try {
			return self::translateCaption( $caption, $postId );
		} finally {
			self::$translatingCaption = false;
		}
	}

	public static function translateCaption( $caption, $postId ) {
		if ( null === $postId ) {
			return $caption;
		}

		$translationId = Obj::prop( 'element_id', PostTranslations::getInCurrentLanguage( $postId ) );
		if ( null === $translationId || false === $translationId ) {
			return $caption;
		}

		$translationId = (int) $translationId;
		if ( $translationId === $postId ) {
			return $caption;
		}

		$translatedCaption = wp_get_attachment_caption( $translationId );

		return null === $translatedCaption || false === $translatedCaption ? $caption : $translatedCaption;
	}
}
