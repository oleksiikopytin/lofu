<?php
/**
 * Custom post types.
 *
 * @package WordPress
 * @subpackage lofu
 * @since lofu 1.0
 */

/**
 * Registers the "project" post type.
 *
 * Projects have no page of their own: they are listed in the home page
 * Projects section and open in a modal there. Single URLs stay public so old
 * or shared links keep working; lofu_project_redirect() sends them to the
 * home page with the hash that opens the project's modal.
 */
function lofu_register_post_types() {
	register_post_type(
		'project',
		array(
			'labels'              => array(
				'name'                  => __( 'Projects', 'lofu' ),
				'singular_name'         => __( 'Project', 'lofu' ),
				'menu_name'             => __( 'Projects', 'lofu' ),
				'add_new'               => __( 'Add project', 'lofu' ),
				'add_new_item'          => __( 'Add new project', 'lofu' ),
				'edit_item'             => __( 'Edit project', 'lofu' ),
				'new_item'              => __( 'New project', 'lofu' ),
				'view_item'             => __( 'View project', 'lofu' ),
				'search_items'          => __( 'Search projects', 'lofu' ),
				'not_found'             => __( 'No projects found.', 'lofu' ),
				'not_found_in_trash'    => __( 'No projects found in Trash.', 'lofu' ),
				'all_items'             => __( 'All projects', 'lofu' ),
				'featured_image'        => __( 'Card image', 'lofu' ),
				'set_featured_image'    => __( 'Set card image', 'lofu' ),
				'remove_featured_image' => __( 'Remove card image', 'lofu' ),
				'use_featured_image'    => __( 'Use as card image', 'lofu' ),
			),
			'public'              => true,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'show_in_rest'        => true,
			'show_in_nav_menus'   => false,
			'menu_position'       => 20,
			'menu_icon'           => 'dashicons-portfolio',
			'supports'            => array( 'title', 'thumbnail' ),
			'rewrite'             => array(
				'slug'       => 'project',
				'with_front' => false,
			),
		)
	);

	// Products: same pattern as projects. They live in the home page
	// Products section, inside their series' window, and single URLs
	// redirect there (lofu_product_redirect()).
	register_post_type(
		'product',
		array(
			'labels'              => array(
				'name'                  => __( 'Products', 'lofu' ),
				'singular_name'         => __( 'Product', 'lofu' ),
				'menu_name'             => __( 'Products', 'lofu' ),
				'add_new'               => __( 'Add product', 'lofu' ),
				'add_new_item'          => __( 'Add new product', 'lofu' ),
				'edit_item'             => __( 'Edit product', 'lofu' ),
				'new_item'              => __( 'New product', 'lofu' ),
				'view_item'             => __( 'View product', 'lofu' ),
				'search_items'          => __( 'Search products', 'lofu' ),
				'not_found'             => __( 'No products found.', 'lofu' ),
				'not_found_in_trash'    => __( 'No products found in Trash.', 'lofu' ),
				'all_items'             => __( 'All products', 'lofu' ),
				'featured_image'        => __( 'Card image', 'lofu' ),
				'set_featured_image'    => __( 'Set card image', 'lofu' ),
				'remove_featured_image' => __( 'Remove card image', 'lofu' ),
				'use_featured_image'    => __( 'Use as card image', 'lofu' ),
			),
			'public'              => true,
			'exclude_from_search' => true,
			'has_archive'         => false,
			'show_in_rest'        => true,
			'show_in_nav_menus'   => false,
			'menu_position'       => 21,
			'menu_icon'           => 'dashicons-lightbulb',
			'supports'            => array( 'title', 'thumbnail', 'page-attributes' ),
			'rewrite'             => array(
				'slug'       => 'product',
				'with_front' => false,
			),
		)
	);

	register_taxonomy(
		'series',
		array( 'product' ),
		array(
			'labels'             => array(
				'name'          => __( 'Series', 'lofu' ),
				'singular_name' => __( 'Series', 'lofu' ),
				'menu_name'     => __( 'Series', 'lofu' ),
				'all_items'     => __( 'All series', 'lofu' ),
				'edit_item'     => __( 'Edit series', 'lofu' ),
				'view_item'     => __( 'View series', 'lofu' ),
				'update_item'   => __( 'Update series', 'lofu' ),
				'add_new_item'  => __( 'Add new series', 'lofu' ),
				'new_item_name' => __( 'New series name', 'lofu' ),
				'search_items'  => __( 'Search series', 'lofu' ),
				'not_found'     => __( 'No series found.', 'lofu' ),
				'back_to_items' => __( '&larr; Back to series', 'lofu' ),
			),
			'public'             => true,
			'hierarchical'       => false,
			'show_in_rest'       => true,
			'show_in_nav_menus'  => false,
			'show_tagcloud'      => false,
			'show_admin_column'  => true,
			'show_in_quick_edit' => false,
			// A product's series is picked in its "Product" ACF group (one
			// series per product), so the default tag box is hidden.
			'meta_box_cb'        => false,
			'rewrite'            => array(
				'slug'       => 'series',
				'with_front' => false,
			),
		)
	);
}
add_action( 'init', 'lofu_register_post_types' );

/**
 * Re-registers the rewrite rules when the theme is switched to, so the
 * project URLs (and their redirect) work without visiting Permalinks.
 */
function lofu_flush_rewrite_rules() {
	lofu_register_post_types();
	flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'lofu_flush_rewrite_rules' );

/**
 * Hash id of a project on the home page, e.g. "project-arso".
 *
 * Used as the card's id and as the URL hash that opens the project's modal.
 *
 * @param int|WP_Post $project Project post or ID.
 * @return string
 */
function lofu_project_anchor( $project ) {
	// Non-Latin slugs are stored percent-encoded; the browser's hash (as
	// decoded by projects.js) is the plain text, so match that.
	return 'project-' . rawurldecode( get_post_field( 'post_name', $project ) );
}

/**
 * Maps project IDs to their translations in the current language.
 *
 * The home page's project list is copied between languages (ACFML "Copy"),
 * so it can hold the default language's IDs. A project without a
 * translation follows WPML's setting for the post type: dropped when
 * projects are "translatable – only show translated items", shown in the
 * original language when they are "display as translated".
 * Without WPML the IDs pass through unchanged.
 *
 * @param int[] $project_ids Project IDs, in display order.
 * @return int[] Translated IDs, in the same order.
 */
function lofu_translated_project_ids( $project_ids ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public hook.
	$fallback = (bool) apply_filters( 'wpml_is_display_as_translated_post_type', false, 'project' );
	$mapped   = array();

	foreach ( (array) $project_ids as $project_id ) {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public hook.
		$translated_id = apply_filters( 'wpml_object_id', (int) $project_id, 'project', $fallback );

		if ( $translated_id ) {
			$mapped[] = (int) $translated_id;
		}
	}

	return array_values( array_unique( $mapped ) );
}

/**
 * Redirects a single project URL to the home page (in the same language),
 * with the hash that opens that project's modal.
 *
 * A temporary redirect, so browsers don't cache it if projects ever get
 * pages of their own.
 */
function lofu_project_redirect() {
	if ( ! is_singular( 'project' ) ) {
		return;
	}

	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public hook.
	$lofu_home = trailingslashit( apply_filters( 'wpml_home_url', home_url( '/' ) ) );

	wp_safe_redirect( $lofu_home . '#' . lofu_project_anchor( get_queried_object() ), 302 );
	exit;
}
add_action( 'template_redirect', 'lofu_project_redirect' );

/**
 * Keeps the redirecting project and product URLs out of the Yoast XML sitemap.
 *
 * @param bool   $excluded  Whether the post type is excluded.
 * @param string $post_type Post type name.
 * @return bool
 */
function lofu_sitemap_exclude_projects( $excluded, $post_type ) {
	return in_array( $post_type, array( 'project', 'product' ), true ) ? true : $excluded;
}
add_filter( 'wpseo_sitemap_exclude_post_type', 'lofu_sitemap_exclude_projects', 10, 2 );

/**
 * Keeps the redirecting series URLs out of the Yoast XML sitemap.
 *
 * @param bool   $excluded Whether the taxonomy is excluded.
 * @param string $taxonomy Taxonomy name.
 * @return bool
 */
function lofu_sitemap_exclude_series( $excluded, $taxonomy ) {
	return 'series' === $taxonomy ? true : $excluded;
}
add_filter( 'wpseo_sitemap_exclude_taxonomy', 'lofu_sitemap_exclude_series', 10, 2 );

/**
 * Hash id of a series on the home page, e.g. "series-naive".
 *
 * Used as the card's id and as the URL hash that opens the series window.
 *
 * @param int|WP_Term $series Series term or term ID.
 * @return string
 */
function lofu_series_anchor( $series ) {
	$term = get_term( $series, 'series' );

	return $term instanceof WP_Term ? 'series-' . rawurldecode( $term->slug ) : '';
}

/**
 * Hash id of a product on the home page, e.g. "product-naive-pendant".
 *
 * The hash opens the product's window on top of its series' window.
 *
 * @param int|WP_Post $product Product post or ID.
 * @return string
 */
function lofu_product_anchor( $product ) {
	return 'product-' . rawurldecode( get_post_field( 'post_name', $product ) );
}

/**
 * The series a product belongs to (its first one, if it somehow has more).
 *
 * @param int $product_id Product ID.
 * @return WP_Term|null
 */
function lofu_product_series( $product_id ) {
	$terms = get_the_terms( $product_id, 'series' );

	return $terms && ! is_wp_error( $terms ) ? $terms[0] : null;
}

/**
 * Maps series term IDs to their translations in the current language.
 *
 * Like lofu_translated_project_ids(): the home page's series list is copied
 * between languages, so it can hold the default language's IDs.
 *
 * @param int[] $series_ids Series term IDs, in display order.
 * @return int[] Translated IDs, in the same order.
 */
function lofu_translated_series_ids( $series_ids ) {
	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public hook.
	$fallback = (bool) apply_filters( 'wpml_is_display_as_translated_taxonomy', false, 'series' );
	$mapped   = array();

	foreach ( (array) $series_ids as $series_id ) {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public hook.
		$translated_id = apply_filters( 'wpml_object_id', (int) $series_id, 'series', $fallback );

		if ( $translated_id ) {
			$mapped[] = (int) $translated_id;
		}
	}

	return array_values( array_unique( $mapped ) );
}

/**
 * Redirects a single product URL to the home page (in the same language),
 * with the hash that opens the product over its series' window, and a
 * series URL to the hash that opens the series' window.
 *
 * Temporary redirects, like lofu_project_redirect().
 */
function lofu_product_redirect() {
	if ( is_singular( 'product' ) ) {
		$lofu_anchor = lofu_product_anchor( get_queried_object() );
	} elseif ( is_tax( 'series' ) ) {
		$lofu_anchor = lofu_series_anchor( get_queried_object() );
	} else {
		return;
	}

	// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public hook.
	$lofu_home = trailingslashit( apply_filters( 'wpml_home_url', home_url( '/' ) ) );

	wp_safe_redirect( $lofu_home . '#' . $lofu_anchor, 302 );
	exit;
}
add_action( 'template_redirect', 'lofu_product_redirect' );
