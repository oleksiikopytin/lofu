<?php

namespace WPML\CF7;

use WPML\FP\Obj;

class Shortcodes implements \IWPML_Frontend_Action, \IWPML_AJAX_Action {

	public function add_hooks() {
		add_filter( 'shortcode_atts_wpcf7', [ $this, 'translate_shortcode_form_id' ] );
	}

	public function translate_shortcode_form_id( $atts ) {
		$form         = null;
		$formIdOrHash = Obj::prop('id', $atts );;
		$formTitle    = Obj::prop('title', $atts );

		$formIdOrHash = is_string( $formIdOrHash ) ? trim( $formIdOrHash ) : $formIdOrHash;

		if ( $formIdOrHash && function_exists( 'wpcf7_get_contact_form_by_hash' ) ) {
			$form = wpcf7_get_contact_form_by_hash( $formIdOrHash );

			if ( ! $form ) {
				$form = $this->getFormByHashInAllLanguages( $formIdOrHash );
			}
		}

		if ( ! $form && $this->isFormId( $formIdOrHash ) ) {
			$form = wpcf7_contact_form( (int) $formIdOrHash );
		}

		if ( ! $form && $formTitle ) {
			$formTitle = trim( $formTitle );
			$form      = wpcf7_get_contact_form_by_title( $formTitle );

			if ( ! $form ) {
				$form = $this->getFormByTitleInAllLanguages( $formTitle );
			}
		}

		if ( $form ) {
			$atts['id']    = apply_filters( 'wpml_object_id', $form->id(), Constants::POST_TYPE, true );
			$atts['title'] = '';
		}

		return $atts;
	}

	private function isFormId( $formIdOrHash ) {
		return is_int( $formIdOrHash )
			|| ( is_string( $formIdOrHash ) && 1 === preg_match( '/^[0-9]+$/D', $formIdOrHash ) );
	}

	private function getFormByTitleInAllLanguages( $formTitle ) {
		return $this->getFirstFormInAllLanguages( [ 'title' => $formTitle ] );
	}

	private function getFormByHashInAllLanguages( $hash ) {
		if ( ! preg_match( '/^[0-9a-f]{7,}$/', $hash ) ) {
			return null;
		}

		return $this->getFirstFormInAllLanguages(
			[
				'meta_query' => [
					[
						'key'     => '_hash',
						'compare' => 'REGEXP',
						'value'   => '^' . $hash,
					],
				],
			]
		);
	}

	private function getFirstFormInAllLanguages( array $conditions ) {
		$formIds = get_posts(
			[ 'post_type' => Constants::POST_TYPE ]
			+ $conditions
			+ [
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'fields'           => 'ids',
				'suppress_filters' => true,
			]
		);

		return $formIds ? wpcf7_contact_form( $formIds[0] ) : null;
	}
}
