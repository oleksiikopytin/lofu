<?php
// Register the theme's nav menu locations.
register_nav_menus(
	array(
		'main' => __( 'Main Menu', 'lofu' ),
	)
);

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

/**
 * An image rendered with a `data-defer` attribute (wp_get_attachment_image()
 * $attr) gets its src, srcset and sizes as data-* attributes instead, so it
 * loads only when a script copies them back (e.g. concepts.js).
 *
 * @param array $attr Image attributes.
 * @return array
 */
function lofu_defer_image_attributes( $attr ) {
	if ( ! array_key_exists( 'data-defer', $attr ) ) {
		return $attr;
	}

	$defer = ! empty( $attr['data-defer'] );
	unset( $attr['data-defer'] );

	if ( ! $defer ) {
		return $attr;
	}

	foreach ( array( 'src', 'srcset', 'sizes' ) as $name ) {
		if ( isset( $attr[ $name ] ) ) {
			$attr[ 'data-' . $name ] = $attr[ $name ];
			unset( $attr[ $name ] );
		}
	}

	// Keeps the tag valid until the real source is set.
	$attr['src'] = 'data:,';

	return $attr;
}
add_filter( 'wp_get_attachment_image_attributes', 'lofu_defer_image_attributes', 99 );

add_theme_support( 'post-thumbnails' );

// Theme image sizes
add_image_size( 'lofu-featured-image', 1800, 9999, false );

/*
 * Switch default core markup for search form, comment form, and comments
 * to output valid HTML5.
 */
add_theme_support(
	'html5',
	array(
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
	)
);
