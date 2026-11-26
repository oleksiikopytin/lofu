<?php

namespace WPML\CF7;

use WPML\FP\Fns;
use WPML\FP\Obj;

class Translations implements \IWPML_Backend_Action, \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const MESSAGE_PREFIX = 'field-_messages-0-';

	const MESSAGES_DOMAIN = 'wpml-cf7-messages-';

	const BODY_FIELD = 'body';

	const ENGLISH_LOCALE = 'en_US';

	private $sitepress;

	public function __construct( \SitePress $sitepress ) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		add_filter( 'icl_job_elements', array( $this, 'remove_body_from_translation_job' ), 10, 2 );
		add_filter( 'wpml_tm_translation_job_data', array( $this, 'leave_body_out_of_translation' ), 10, 2 );
		add_filter( 'wpml_tm_populate_prev_translation', array( $this, 'default_message_translations' ), 10, 3 );
		add_filter( 'wpml_document_view_item_link', array( $this, 'document_view_item_link' ), 10, 5 );
		add_filter( 'wpml_document_edit_item_link', array( $this, 'document_edit_item_link' ), 10, 5 );
		add_action( 'save_post', array( $this, 'fix_setting_language_information' ) );
	}

	public function remove_body_from_translation_job( $elements, $post_id ) {
		if ( Constants::POST_TYPE !== get_post_type( $post_id ) ) {
			return $elements;
		}

		$field_types = wp_list_pluck( $elements, 'field_type' );
		$index       = array_search( self::BODY_FIELD, $field_types, true );
		if ( false !== $index ) {
			$elements[ $index ]->field_data            = '';
			$elements[ $index ]->field_data_translated = '';
		}

		return $elements;
	}

	public function leave_body_out_of_translation( $package, $post ) {
		if ( Constants::POST_TYPE === Obj::prop( 'post_type', $post ) && isset( $package['contents'][ self::BODY_FIELD ] ) ) {
			$package['contents'][ self::BODY_FIELD ]['translate'] = 0;
		}

		return $package;
	}

	public function default_message_translations( $prev, $package, $lang ) {
		if ( Constants::POST_TYPE !== get_post_type( $package['contents']['original_id']['data'] ) ) {
			return $prev;
		}

		$formId           = (int) $package['contents']['original_id']['data'];
		$messages         = $this->getMessages( $this->sitepress->get_locale( $lang ) );
		$originalDefaults = Fns::memorize( function () use ( $formId ) {
			return $this->getOriginalDefaults( $formId );
		} );

		foreach ( $package['contents'] as $type => $element ) {
			if ( 1 === $element['translate'] && 0 === strpos( $type, self::MESSAGE_PREFIX ) && empty( $prev[ $type ] ) ) {
				$field = $this->getPrefilledMessage( $element, str_replace( self::MESSAGE_PREFIX, '', $type ), $messages, $originalDefaults );
				if ( $field ) {
					$prev[ $type ] = $field;
				}
			}
		}

		return $prev;
	}

	private function getPrefilledMessage( array $element, string $key, array $messages, callable $originalDefaults ) {
		if ( ! isset( $messages[ $key ]['default'] ) ) {
			return null;
		}

		$original = base64_decode( $element['data'] );
		if ( ! $this->isDefaultMessage( $original, $key, $originalDefaults() ) ) {
			return null;
		}

		return new \WPML_TM_Translated_Field(
			base64_encode( $original ),
			base64_encode( $messages[ $key ]['default'] ),
			true
		);
	}

	private function getOriginalDefaults( $formId ) {
		$defaults = [ wpcf7_switch_locale( self::ENGLISH_LOCALE, 'wpcf7_messages' ) ];

		$language = $this->sitepress->get_language_for_element( $formId, TranslationEditor\JobFilter::JOB_ORIGINAL_POST_TYPE_CONTACT_FORM_7 );
		$locale   = $language ? $this->sitepress->get_locale( $language ) : null;
		if ( $locale && self::ENGLISH_LOCALE !== $locale ) {
			$defaults[] = $this->getMessages( $locale );
		}

		return $defaults;
	}

	private function isDefaultMessage( $message, $key, $defaults ) {
		foreach ( $defaults as $messages ) {
			if ( isset( $messages[ $key ]['default'] ) && trim( $message ) === trim( $messages[ $key ]['default'] ) ) {
				return true;
			}
		}

		return false;
	}

	private function getMessages( $locale ) {
		if ( in_array( $locale, get_available_languages(), true ) ) {
			return wpcf7_switch_locale( $locale, 'wpcf7_messages' );
		}

		$messages = wpcf7_switch_locale( self::ENGLISH_LOCALE, 'wpcf7_messages' );

		$domain = self::MESSAGES_DOMAIN . $locale;
		$file   = WP_LANG_DIR . '/plugins/' . WPCF7_TEXT_DOMAIN . '-' . $locale . '.mo';

		if ( ! load_textdomain( $domain, $file, $locale ) ) {
			return $messages;
		}

		$translations = get_translations_for_domain( $domain );
		foreach ( $messages as $key => $message ) {
			$messages[ $key ]['default'] = $translations->translate( $message['default'] );
		}
		unload_textdomain( $domain );

		return $messages;
	}

	public function document_view_item_link( $link, $text, $job, $prefix, $type ) {
		if ( Constants::POST_TYPE === $type ) {
			$link = '';
		}

		return $link;
	}

	public function document_edit_item_link( $link, $text, $current_document, $prefix, $type ) {
		if ( Constants::POST_TYPE === $type ) {
			$url  = sprintf( 'admin.php?page=wpcf7&post=%d&action=edit', $current_document->ID );
			$link = sprintf( '<a href="%s">%s</a>', admin_url( $url ), $text );
		}

		return $link;
	}

	public function fix_setting_language_information() {
		if ( empty( $_POST['_wpnonce'] ) || empty( $_POST['post_ID'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'wpcf7-save-contact-form_' . $_POST['post_ID'] ) ) {
			return;
		}

		if ( -1 === (int) $_POST['post_ID'] ) {
			unset( $_POST['post_ID'] );
		}
	}

}
