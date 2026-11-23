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
	wp_enqueue_script( 'scripts', get_theme_file_uri( '/assets/js/scripts.js' ), array( 'jquery' ), null, true );

	if ( has_nav_menu( 'main' ) ) {
		wp_enqueue_script( 'lofu-header', get_theme_file_uri( '/assets/js/header/header.js' ), array(), filemtime( get_theme_file_path( '/assets/js/header/header.js' ) ), true );
	}




}
add_action( 'wp_enqueue_scripts', 'lofu_scripts' );

/**
 * Enqueue the home page section assets, only on the "Home page" template.
 *
 * Done in the head (not from the template part) so the hero's initial
 * animation state is styled before first paint.
 */
function lofu_home_page_scripts() {
	if ( ! is_page_template( 'page-templates/front-page.php' ) ) {
		return;
	}

	$hero_css       = '/assets/css/pages/home-page/hero.css';
	$about_css      = '/assets/css/pages/home-page/about.css';
	$split_lines_js = '/assets/js/components/split-lines.js';
	$about_js       = '/assets/js/pages/home-page/about.js';
	$concepts_css   = '/assets/css/pages/home-page/concepts.css';
	$concepts_js    = '/assets/js/pages/home-page/concepts.js';

	wp_enqueue_style( 'lofu-home-hero', get_theme_file_uri( $hero_css ), array( 'main' ), filemtime( get_theme_file_path( $hero_css ) ) );
	wp_enqueue_style( 'lofu-home-about', get_theme_file_uri( $about_css ), array( 'main' ), filemtime( get_theme_file_path( $about_css ) ) );
	wp_register_script( 'lofu-split-lines', get_theme_file_uri( $split_lines_js ), array(), filemtime( get_theme_file_path( $split_lines_js ) ), true );
	wp_enqueue_script( 'lofu-home-about', get_theme_file_uri( $about_js ), array( 'lofu-split-lines' ), filemtime( get_theme_file_path( $about_js ) ), true );
	wp_enqueue_style( 'lofu-home-concepts', get_theme_file_uri( $concepts_css ), array( 'main' ), filemtime( get_theme_file_path( $concepts_css ) ) );
	wp_enqueue_script( 'lofu-home-concepts', get_theme_file_uri( $concepts_js ), array( 'jquery', 'slick' ), filemtime( get_theme_file_path( $concepts_js ) ), true );

	$view_switch_css   = '/assets/css/global/components/view-switch.css';
	$view_switch_js    = '/assets/js/components/view-switch.js';
	$projects_css      = '/assets/css/pages/home-page/projects.css';
	$project_modal_css = '/assets/css/pages/home-page/project-modal.css';
	$projects_js       = '/assets/js/pages/home-page/projects.js';
	$photo_viewer_css  = '/assets/css/global/components/photo-viewer.css';
	$photo_viewer_js   = '/assets/js/components/photo-viewer.js';

	wp_enqueue_style( 'lofu-view-switch', get_theme_file_uri( $view_switch_css ), array( 'main' ), filemtime( get_theme_file_path( $view_switch_css ) ) );
	wp_enqueue_script( 'lofu-view-switch', get_theme_file_uri( $view_switch_js ), array(), filemtime( get_theme_file_path( $view_switch_js ) ), true );
	wp_enqueue_style( 'lofu-home-projects', get_theme_file_uri( $projects_css ), array( 'main' ), filemtime( get_theme_file_path( $projects_css ) ) );
	wp_enqueue_style( 'lofu-home-project-modal', get_theme_file_uri( $project_modal_css ), array( 'main' ), filemtime( get_theme_file_path( $project_modal_css ) ) );
	wp_enqueue_script( 'lofu-home-projects', get_theme_file_uri( $projects_js ), array( 'lofu-view-switch', 'lofu-split-lines' ), filemtime( get_theme_file_path( $projects_js ) ), true );
	wp_enqueue_style( 'lofu-photo-viewer', get_theme_file_uri( $photo_viewer_css ), array( 'main' ), filemtime( get_theme_file_path( $photo_viewer_css ) ) );
	wp_enqueue_script( 'lofu-photo-viewer', get_theme_file_uri( $photo_viewer_js ), array(), filemtime( get_theme_file_path( $photo_viewer_js ) ), true );
}
add_action( 'wp_enqueue_scripts', 'lofu_home_page_scripts' );

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

