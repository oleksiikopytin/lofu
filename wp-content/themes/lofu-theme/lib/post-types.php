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
 * Keeps the redirecting project URLs out of the Yoast XML sitemap.
 *
 * @param bool   $excluded  Whether the post type is excluded.
 * @param string $post_type Post type name.
 * @return bool
 */
function lofu_sitemap_exclude_projects( $excluded, $post_type ) {
	return 'project' === $post_type ? true : $excluded;
}
add_filter( 'wpseo_sitemap_exclude_post_type', 'lofu_sitemap_exclude_projects', 10, 2 );
