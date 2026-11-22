<?php

namespace WPML\Compatibility\WoodMart\Hooks;

class LabelConditionIds implements \IWPML_Frontend_Action, \IWPML_AJAX_Action {

	const ID_TYPES = [
		'products'             => 'product',
		'product_cats'         => 'product_cat',
		'product_cat_children' => 'product_cat',
	];

	public function add_hooks() {
		add_filter( 'wpml_object_id', [ $this, 'convertConditionIds' ], 11, 4 );
	}

	public function convertConditionIds( $elementId, $elementType = null, $returnOriginal = false, $languageCode = null ) {
		if ( ! is_string( $elementType ) || ! array_key_exists( $elementType, self::ID_TYPES ) ) {
			return $elementId;
		}

		$idType = self::ID_TYPES[ $elementType ];

		if ( is_int( $elementId ) || ( is_string( $elementId ) && preg_match( '/^\d+$/', $elementId ) ) ) {
			return (int) $elementId > 0 ? $this->convertId( (int) $elementId, $idType, $languageCode ) : $elementId;
		}

		if ( ! is_string( $elementId ) || ! preg_match( '/^\d+(,\d+)+$/', $elementId ) ) {
			return $elementId;
		}

		$ids = array_map( 'intval', explode( ',', $elementId ) );

		if ( min( $ids ) <= 0 ) {
			return $elementId;
		}

		$convertedIds = [];
		foreach ( $ids as $id ) {
			$convertedIds[] = $this->convertId( $id, $idType, $languageCode );
		}

		return implode( ',', $convertedIds );
	}

	private function convertId( $id, $idType, $languageCode ) {
		$convertedId = apply_filters( 'wpml_object_id', $id, $idType, true, $languageCode );

		return is_numeric( $convertedId ) && (int) $convertedId > 0 ? (int) $convertedId : $id;
	}
}
