<?php

add_action( 'wp_ajax_popup_dimension', 'lofu_ajax_popup_dimension' );
add_action( 'wp_ajax_nopriv_popup_dimension', 'lofu_ajax_popup_dimension' );
function lofu_ajax_popup_dimension() {

	wp_die();
}



