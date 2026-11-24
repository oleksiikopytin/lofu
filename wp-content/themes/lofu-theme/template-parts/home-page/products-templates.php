<?php
/**
 * Home page: every series window's and product window's <template>, served
 * on its own by lofu_home_templates_endpoint() and loaded into the products
 * section by lazy-templates.js (see products.php).
 *
 * @package WordPress
 * @subpackage lofu
 * @since lofu 1.0
 */

$lofu_series_products = array();

foreach ( lofu_home_series() as $lofu_term ) {
	$lofu_series_products[] = array(
		'series'      => $lofu_term,
		'product_ids' => lofu_series_product_ids( $lofu_term ),
	);
}

lofu_prime_posts( array_merge( array(), ...array_column( $lofu_series_products, 'product_ids' ) ), array( 'gallery', 'video' ) );

foreach ( $lofu_series_products as $lofu_entry ) {
	get_template_part( 'template-parts/home-page/series-modal', null, $lofu_entry );

	foreach ( $lofu_entry['product_ids'] as $lofu_product_id ) {
		get_template_part(
			'template-parts/home-page/product-modal',
			null,
			array(
				'product_id' => $lofu_product_id,
				'series'     => $lofu_entry['series'],
			)
		);
	}
}
