<?php
// This theme uses wp_nav_menu() in three locations.
register_nav_menus( array(
	'main' => __( 'Main Menu', 'lofu' ),
	//    'footer'  => __( 'Footer Menu', 'lofu' )
) );

/**
 * Main menu items are "#section" links to home page sections. Outside the
 * front page, point them at the home page in the current language.
 *
 * @param array    $atts Link attributes.
 * @param WP_Post  $item Menu item.
 * @param stdClass $args wp_nav_menu() arguments.
 * @return array
 */
function lofu_main_menu_anchor_links( $atts, $item, $args ) {
	if ( 'main' !== $args->theme_location || is_front_page() ) {
		return $atts;
	}

	if ( ! empty( $atts['href'] ) && 0 === strpos( $atts['href'], '#' ) ) {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public hook.
		$atts['href'] = trailingslashit( apply_filters( 'wpml_home_url', home_url( '/' ) ) ) . $atts['href'];
	}

	return $atts;
}
add_filter( 'nav_menu_link_attributes', 'lofu_main_menu_anchor_links', 10, 3 );

add_theme_support( 'post-thumbnails' );

// Theme image sizes
add_image_size( 'lofu-featured-image', 1800, 9999, false );

// function my_custom_sizes( $sizes ) {
//     return array_merge( $sizes, array(
//         '580x580' => __( 'Squared image' ),
//     ) );
// }


/*
 * Switch default core markup for search form, comment form, and comments
 * to output valid HTML5.
 */
add_theme_support( 'html5', array(
	'comment-form',
	'comment-list',
	'gallery',
	'caption',
) );