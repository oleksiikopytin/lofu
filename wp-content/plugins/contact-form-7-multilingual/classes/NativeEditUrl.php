<?php

namespace WPML\CF7;

class NativeEditUrl implements \IWPML_Backend_Action, \IWPML_REST_Action, \IWPML_DIC_Action {

	private $metabox;

	public function __construct( Language_Metabox $metabox ) {
		$this->metabox = $metabox;
	}

	public function add_hooks() {
		add_filter( 'wpml_native_edit_url', [ $this->metabox, 'native_edit_url' ], 10, 4 );
	}
}
