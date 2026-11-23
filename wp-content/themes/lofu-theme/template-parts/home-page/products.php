<?php
/**
 * Home page: products (series cards + series and product windows).
 *
 * The section title, intro and the hand-picked, ordered series come from the
 * "Home page" ACF group (`products` group field). Each series card links to
 * the series URL, which lofu_product_redirect() sends back here with a
 * `#series-{slug}` hash; a product URL comes back with `#product-{slug}`.
 *
 * Every series and every product in them is rendered once into a <template>.
 * products.js opens a series in the `series-modal` dialog (its description
 * and a grid of its products), and a product in the `product-modal` dialog
 * on top of it, and keeps the hash in sync.
 *
 * @package WordPress
 * @subpackage lofu
 * @since lofu 1.0
 */

$lofu_products_field = get_field( 'products' );
// The list may hold the default language's IDs; show this language's versions.
$lofu_series_ids = lofu_translated_series_ids( $lofu_products_field['selected_series'] ?? array() );
$lofu_series     = array();

foreach ( $lofu_series_ids as $lofu_series_id ) {
	$lofu_term = get_term( $lofu_series_id, 'series' );

	if ( $lofu_term instanceof WP_Term ) {
		$lofu_series[] = $lofu_term;
	}
}

if ( empty( $lofu_series ) ) {
	return;
}

$lofu_products_title = $lofu_products_field['section_title'] ?? '';
$lofu_products_intro = $lofu_products_field['intro'] ?? '';
?>
<section id="products" class="products module"<?php echo $lofu_products_title ? ' aria-labelledby="products-title"' : ''; ?>>
	<div class="ia-container">
		<?php if ( $lofu_products_title || $lofu_products_intro ) : ?>
			<div class="products__head d-flex justify-content-between align-items-baseline flex-wrap">
				<?php if ( $lofu_products_title ) : ?>
					<h2 id="products-title" class="products__title section-title products__mask"><span class="products__mask-inner"><?php echo esc_html( $lofu_products_title ); ?></span></h2>
				<?php endif; ?>

				<?php if ( $lofu_products_intro ) : ?>
					<p class="products__intro"><?php echo esc_html( $lofu_products_intro ); ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<ul class="products__list">
			<?php
			foreach ( $lofu_series as $lofu_term ) :
				$lofu_series_image = (int) get_field( 'image', $lofu_term );
				$lofu_series_fit   = 'contain' === get_field( 'image_fit', $lofu_term ) ? 'contain' : 'cover';
				$lofu_series_link  = get_term_link( $lofu_term );
				?>
				<li class="products__item">
					<a
						id="<?php echo esc_attr( lofu_series_anchor( $lofu_term ) ); ?>"
						class="products__card"
						href="<?php echo esc_url( is_wp_error( $lofu_series_link ) ? '#' . lofu_series_anchor( $lofu_term ) : $lofu_series_link ); ?>"
						data-series="<?php echo esc_attr( lofu_series_anchor( $lofu_term ) ); ?>"
						aria-haspopup="dialog"
					>
						<span class="products__media products__media--<?php echo esc_attr( $lofu_series_fit ); ?> d-flex align-items-center justify-content-center">
							<?php
							if ( $lofu_series_image ) {
								echo wp_get_attachment_image(
									$lofu_series_image,
									'lofu-featured-image',
									false,
									array(
										'class'   => 'products__image',
										'alt'     => '',
										'sizes'   => '(min-width: 768px) 33vw, 100vw',
										'loading' => 'lazy',
									)
								);
							}
							?>
						</span>
						<span class="products__caption d-flex justify-content-between gap-3">
							<span class="products__name"><?php echo esc_html( $lofu_term->name ); ?></span>
							<span class="products__more"><?php esc_html_e( 'Серія', 'lofu' ); ?> <span aria-hidden="true">&rarr;</span></span>
						</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>

<dialog id="series-modal" class="series-modal" aria-labelledby="series-modal-title"></dialog>
<dialog id="product-modal" class="product-modal" aria-labelledby="product-modal-title"></dialog>

<?php
foreach ( $lofu_series as $lofu_term ) {
	$lofu_product_ids = get_posts(
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
					'terms'    => $lofu_term->term_id,
				),
			),
		)
	);

	get_template_part(
		'template-parts/home-page/series-modal',
		null,
		array(
			'series'      => $lofu_term,
			'product_ids' => $lofu_product_ids,
		)
	);

	foreach ( $lofu_product_ids as $lofu_product_id ) {
		get_template_part(
			'template-parts/home-page/product-modal',
			null,
			array(
				'product_id' => $lofu_product_id,
				'series'     => $lofu_term,
			)
		);
	}
}
