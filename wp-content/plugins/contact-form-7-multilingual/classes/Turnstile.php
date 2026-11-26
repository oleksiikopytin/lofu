<?php

namespace WPML\CF7;

use WPML\FP\Obj;

class Turnstile implements \IWPML_Frontend_Action, \IWPML_AJAX_Action {

	const TAG_TYPE = 'turnstile';

	const LANGUAGE_OPTION_PREFIX = 'language:';

	const LANGUAGE_PATTERN = '/^[a-z-]{2,5}$/';

	const TURNSTILE_LANGUAGES = [
		'zh-hans' => 'zh-cn',
		'zh-hant' => 'zh-tw',
	];

	public function add_hooks() {
		add_filter( 'wpcf7_form_tag', [ $this, 'useFormLanguage' ] );
	}

	public function useFormLanguage( $tag ) {
		if ( self::TAG_TYPE !== Obj::prop( 'basetype', $tag ) || ! is_array( Obj::prop( 'options', $tag ) ) ) {
			return $tag;
		}

		$languageCode = $this->getTurnstileLanguage( $this->getTranslatedFormLanguage() );

		if ( ! $languageCode ) {
			return $tag;
		}

		foreach ( $tag['options'] as $index => $option ) {
			if ( 0 === strpos( $option, self::LANGUAGE_OPTION_PREFIX ) ) {
				$tag['options'][ $index ] = self::LANGUAGE_OPTION_PREFIX . $languageCode;
			}
		}

		return $tag;
	}

	private function getTurnstileLanguage( $languageCode ) {
		if ( isset( self::TURNSTILE_LANGUAGES[ $languageCode ] ) ) {
			$languageCode = self::TURNSTILE_LANGUAGES[ $languageCode ];
		}

		return $languageCode && preg_match( self::LANGUAGE_PATTERN, $languageCode ) ? $languageCode : null;
	}

	private function getTranslatedFormLanguage() {
		$form = \WPCF7_ContactForm::get_current();

		if ( ! $form ) {
			return null;
		}

		$details = apply_filters(
			'wpml_element_language_details',
			null,
			[
				'element_id'   => $form->id(),
				'element_type' => Constants::POST_TYPE,
			]
		);

		return Obj::prop( 'source_language_code', $details ) ? Obj::prop( 'language_code', $details ) : null;
	}
}
