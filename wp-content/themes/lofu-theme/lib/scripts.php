<?php
/**
 * Enqueue scripts and styles.
 */
function lofu_scripts() {
	// Theme stylesheet.
	wp_enqueue_style( 'animate', get_template_directory_uri() . "/assets/css/animate.css" );
	wp_enqueue_style( 'fancybox3-style', get_template_directory_uri() . "/assets/css/jquery.fancybox.min.css" );
	wp_enqueue_style( 'main', get_template_directory_uri() . "/assets/css/main.css" );


	// Load the html5 shiv.
	wp_enqueue_script( 'slick', get_template_directory_uri() . '/assets/js/slick.min.js', array( 'jquery' ), '20170801', true );

	wp_enqueue_script( 'fancybox3', get_template_directory_uri() . '/assets/js/jquery.fancybox.min.js', array( 'jquery' ), '20220117', true );
	wp_enqueue_script( 'scripts', get_theme_file_uri( '/assets/js/scripts.js' ), array(), null, true );




}
add_action( 'wp_enqueue_scripts', 'lofu_scripts' );
/**
 * Handles JavaScript detection.
 *
 */
function lofu_javascript_detection() {
	echo "<script>(function(html){html.className = html.className.replace(/\bno-js\b/,'js')})(document.documentElement);</script>\n";
}
add_action( 'wp_head', 'lofu_javascript_detection', 0 );

/* Dequeue the jQuery UI script. */
function lofu_dequeue_jquery_ui() {
	wp_dequeue_script( 'jquery-ui-core' );
}
add_action( 'wp_print_scripts', 'lofu_dequeue_jquery_ui', 100 );

/*remove Gutenberg block editor styles */
function lofu_remove_block_library_css() {
	wp_dequeue_style( 'wp-block-library' );
}
add_action( 'wp_enqueue_scripts', 'lofu_remove_block_library_css' );

