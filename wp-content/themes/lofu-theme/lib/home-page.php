<?php
/**
 * Home page data and the endpoint that serves its windows' templates.
 *
 * The project, series and product windows are not part of the home page
 * HTML. Each section names a URL in its `data-templates-src` (the page's own
 * URL with `?lofu_templates={section}`), and lazy-templates.js fetches their
 * <template>s from it as the section comes near the viewport. Rendering them
 * all up front cost most of the page's server time.
 *
 * @package WordPress
 * @subpackage lofu
 * @since lofu 1.0
 */

/**
 * Sections whose window templates load separately, and their template parts.
 *
 * @return string[] Section => template part.
 */
function lofu_home_template_sections() {
	return array(
		'projects' => 'template-parts/home-page/projects-templates',
		'products' => 'template-parts/home-page/products-templates',
	);
}

/**
 * URL of a section's window templates, on the current page (so WPML keeps
 * the language).
 *
 * @param string $section Key from lofu_home_template_sections().
 * @return string
 */
function lofu_home_templates_url( $section ) {
	return add_query_arg( 'lofu_templates', $section, get_permalink() );
}

/**
 * Prints a section's window templates instead of the page, for
 * `?lofu_templates={section}` on the "Home page" template.
 */
function lofu_home_templates_endpoint() {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public content.
	if ( ! isset( $_GET['lofu_templates'] ) || ! is_string( $_GET['lofu_templates'] ) || ! is_page_template( 'page-templates/front-page.php' ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only public content.
	$section  = sanitize_key( wp_unslash( $_GET['lofu_templates'] ) );
	$sections = lofu_home_template_sections();

	if ( ! isset( $sections[ $section ] ) ) {
		return;
	}

	header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
	header( 'X-Robots-Tag: noindex' );

	get_template_part( $sections[ $section ] );
	exit;
}
add_action( 'template_redirect', 'lofu_home_templates_endpoint' );

/**
 * The home page's hand-picked projects, in this language, published only.
 *
 * @return int[]
 */
function lofu_home_project_ids() {
	$field = get_field( 'projects' );
	// The list may hold the default language's IDs; show this language's versions.
	$ids = lofu_translated_project_ids( $field['selected_projects'] ?? array() );

	lofu_prime_posts( $ids );

	return array_values(
		array_filter(
			$ids,
			function ( $project_id ) {
				return 'publish' === get_post_status( $project_id );
			}
		)
	);
}

/**
 * The home page's hand-picked series, in this language.
 *
 * @return WP_Term[]
 */
function lofu_home_series() {
	$field  = get_field( 'products' );
	$series = array();

	// The list may hold the default language's IDs; show this language's versions.
	foreach ( lofu_translated_series_ids( $field['selected_series'] ?? array() ) as $series_id ) {
		$term = get_term( $series_id, 'series' );

		if ( $term instanceof WP_Term ) {
			$series[] = $term;
		}
	}

	return $series;
}

/**
 * A series' published products, in display order.
 *
 * @param WP_Term $term Series.
 * @return int[]
 */
function lofu_series_product_ids( $term ) {
	return get_posts(
		array(
			'post_type'        => 'product',
			'post_status'      => 'publish',
			'posts_per_page'   => -1,
			'fields'           => 'ids',
			'orderby'          => array(
				'menu_order' => 'ASC',
				'title'      => 'ASC',
			),
			'no_found_rows'    => true,
			'suppress_filters' => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- one small query per series.
			'tax_query'        => array(
				array(
					'taxonomy' => 'series',
					'field'    => 'term_id',
					'terms'    => $term->term_id,
				),
			),
		)
	);
}
