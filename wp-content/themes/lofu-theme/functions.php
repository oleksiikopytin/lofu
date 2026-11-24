<?php
/**
 *
 * @package WordPress
 * @subpackage lofu
 * @since 1.0
 */

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook. The init hook is too late for some features, such
 * as indicating support for post thumbnails.
 */
function lofu_setup() {
	$roots_includes = array(
		'lib/post-types.php',     // set post type
		'lib/home-page.php',      // home page data + window templates endpoint
		'lib/config.php',         // set image size
		'lib/scripts.php',        // Scripts and stylesheets
		'lib/shortcode.php',      // shortcode of the site
		'lib/scss-compiler.php',  // compiler
		'lib/ajax.php',           // Ajax functions
		'lib/admin.php',          // wp-admin tweaks
	);
	foreach ( $roots_includes as $file ) {
		$filepath = locate_template( $file );
		if ( ! $filepath ) {
			continue;
		}
		require_once $filepath;
	}
	unset( $file, $filepath );
	/*
	 * Make theme available for translation.
	 * Translations can be filed at WordPress.org. See: https://translate.wordpress.org/projects/wp-themes/lofu
	 * If you're building a theme based on Twenty Seventeen, use a find and replace
	 * to change 'lofu' to the name of your theme in all the template files.
	 */
	load_theme_textdomain( 'lofu' );

	// Add default posts and comments RSS feed links to head.
	add_theme_support( 'automatic-feed-links' );

	/*
	 * Let WordPress manage the document title.
	 * By adding theme support, we declare that this theme does not use a
	 * hard-coded <title> tag in the document head, and expect WordPress to
	 * provide it for us.
	 */
	add_theme_support( 'title-tag' );

	// Add theme support for Custom Logo.
	add_theme_support(
		'custom-logo',
		array(
			'width'      => 250,
			'height'     => 250,
			'flex-width' => true,
		)
	);

	// Add theme support for selective refresh for widgets.
	add_theme_support( 'customize-selective-refresh-widgets' );
}
add_action( 'after_setup_theme', 'lofu_setup' );

/* Add a pingback url auto-discovery header for singularly identifiable articles. */
function lofu_pingback_header() {
	if ( is_singular() && pings_open() ) {
		printf( '<link rel="pingback" href="%s">' . "\n", esc_url( get_bloginfo( 'pingback_url' ) ) );
	}
}
add_action( 'wp_head', 'lofu_pingback_header' );

/* Theme options ACF */
function lofu_register_acf_options_page() {
	if ( function_exists( 'acf_add_options_page' ) ) {
		acf_add_options_page();
	}
}
add_action( 'acf/init', 'lofu_register_acf_options_page' );

/* Disable the emoji's */
function lofu_disable_emojis() {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'admin_print_styles', 'print_emoji_styles' );
	remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
	remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
	remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
	add_filter( 'tiny_mce_plugins', 'lofu_disable_emojis_tinymce' );
	add_filter( 'wp_resource_hints', 'lofu_disable_emojis_remove_dns_prefetch', 10, 2 );
}
add_action( 'init', 'lofu_disable_emojis' );

/* Filter function used to remove the tinymce emoji plugin.*/
function lofu_disable_emojis_tinymce( $plugins ) {
	if ( is_array( $plugins ) ) {
		return array_diff( $plugins, array( 'wpemoji' ) );
	} else {
		return array();
	}
}

/* Remove emoji CDN hostname from DNS prefetching hints. */
function lofu_disable_emojis_remove_dns_prefetch( $urls, $relation_type ) {
	if ( 'dns-prefetch' === $relation_type ) {
		/** This filter is documented in wp-includes/formatting.php */
		$emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core filter.

		$urls = array_diff( $urls, array( $emoji_svg_url ) );
	}
	return $urls;
}

/* register widgets area */
function lofu_widgets_init() {
	register_sidebar(
		array(
			'name'          => __( 'Sidebar Widgets', 'lofu' ),
			'id'            => 'sidebar-1',
			'description'   => __( 'Widgets in this area will be shown on all posts and pages.', 'lofu' ),
			'before_widget' => '<div id="%1$s" class="widget %2$s">',
			'after_widget'  => '</div>',
			'before_title'  => '<h2 class="widgettitle">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'lofu_widgets_init' );

/*prevent self-pings */
function lofu_no_self_ping( &$links ) {
	$home = home_url();
	foreach ( $links as $l => $link ) {
		if ( 0 === strpos( $link, $home ) ) {
			unset( $links[ $l ] );
		}
	}
}
add_action( 'pre_ping', 'lofu_no_self_ping' );

/* Replace the frameborder attribute with an empty string. */
function lofu_remove_frameborder( $html ) {
	$html = str_replace( 'frameborder="0"', '', $html );
	return $html;
}
add_filter( 'embed_oembed_html', 'lofu_remove_frameborder' );

/*smart search results*/
function lofu_highlight_words( $text, $word ) {
	$text = preg_replace( '#' . preg_quote( $word, '#' ) . '#i', '<span class="hlw">\\0</span>', $text );
	return $text;
}

/*Disable automatic plugin updates */
add_filter( 'auto_update_plugin', '__return_false' );
/**
 * Remove the WPML "registered as a development site" footer banner
 * (.otgs-development-site-front-end). OTGS Installer adds it to wp_footer as
 * an anonymous closure, so it can't be removed with remove_action().
 */
function lofu_remove_wpml_dev_site_banner() {
	global $wp_filter;

	if ( empty( $wp_filter['wp_footer']->callbacks[999] ) ) {
		return;
	}

	foreach ( $wp_filter['wp_footer']->callbacks[999] as $callback ) {
		if ( ! $callback['function'] instanceof Closure ) {
			continue;
		}

		$reflection = new ReflectionFunction( $callback['function'] );
		if ( false !== strpos( wp_normalize_path( $reflection->getFileName() ), 'otgs/installer/loader.php' ) ) {
			remove_action( 'wp_footer', $callback['function'], 999 );
		}
	}
}
add_action( 'wp_footer', 'lofu_remove_wpml_dev_site_banner', 1 );

/**
 * Hide WPML's footer language switcher. The header has its own switcher.
 *
 * @param string       $html  Switcher markup.
 * @param array        $model Template model.
 * @param WPML_LS_Slot $slot  Switcher slot.
 * @return string
 */
function lofu_hide_wpml_footer_switcher( $html, $model, $slot ) {
	return $slot->is_footer() ? '' : $html;
}
add_filter( 'wpml_ls_html', 'lofu_hide_wpml_footer_switcher', 10, 3 );

add_action(
	'send_headers',
	function () {
		header( 'X-Frame-Options: SAMEORIGIN' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Referrer-Policy: strict-origin-when-cross-origin' );
		header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()' );
	}
);
