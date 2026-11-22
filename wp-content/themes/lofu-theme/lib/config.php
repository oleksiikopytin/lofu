<?php
// This theme uses wp_nav_menu() in three locations.
register_nav_menus( array(
	'main' => __( 'Main Menu', 'lofu' ),
	//    'footer'  => __( 'Footer Menu', 'lofu' )
) );

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