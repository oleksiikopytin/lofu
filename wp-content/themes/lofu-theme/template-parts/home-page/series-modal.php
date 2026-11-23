<?php
/**
 * Home page: one series' window content, rendered into a <template>.
 *
 * products.js clones it into the `series-modal` dialog: the series name and
 * description (the term's own Description) and a grid of its products. Each
 * product card opens that product's window on top.
 *
 * Args:
 * - series      (WP_Term) Series to render.
 * - product_ids (int[])   Its published products, in display order.
 *
 * @package WordPress
 * @subpackage lofu
 * @since lofu 1.0
 */

$lofu_term = $args['series'] ?? null;

if ( ! $lofu_term instanceof WP_Term ) {
	return;
}

$lofu_product_ids = (array) ( $args['product_ids'] ?? array() );
?>
<template id="<?php echo esc_attr( lofu_series_anchor( $lofu_term ) ); ?>-modal">
	<div class="series-modal__bar">
		<div class="ia-container series-modal__bar-inner d-flex align-items-center justify-content-between gap-3">
			<span class="series-modal__brand" aria-hidden="true"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
			<button type="button" class="series-modal__close d-flex align-items-center" data-modal-close>
				<span aria-hidden="true">&larr;</span> <?php esc_html_e( 'Всі вироби', 'lofu' ); ?>
			</button>
		</div>
	</div>

	<div class="series-modal__body">
		<div class="ia-container">
			<div class="series-modal__head">
				<div class="series-modal__heading d-flex flex-column">
					<span class="series-modal__eyebrow"><?php esc_html_e( 'Серія', 'lofu' ); ?></span>
					<h2 id="series-modal-title" class="series-modal__title"><?php echo esc_html( $lofu_term->name ); ?></h2>
				</div>

				<?php if ( $lofu_term->description ) : ?>
					<div class="series-modal__description">
						<?php echo wp_kses_post( wpautop( $lofu_term->description ) ); ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( $lofu_product_ids ) : ?>
				<ul class="series-modal__list">
					<?php
					foreach ( $lofu_product_ids as $lofu_product_id ) :
						$lofu_product_type  = get_field( 'product_type', $lofu_product_id );
						$lofu_product_image = get_post_thumbnail_id( $lofu_product_id );

						if ( ! $lofu_product_image ) {
							$lofu_product_gallery = get_field( 'gallery', $lofu_product_id );
							$lofu_product_image   = $lofu_product_gallery[0] ?? 0;
						}
						?>
						<li class="series-modal__item">
							<a
								id="<?php echo esc_attr( lofu_product_anchor( $lofu_product_id ) ); ?>"
								class="series-modal__card"
								href="<?php echo esc_url( get_permalink( $lofu_product_id ) ); ?>"
								data-product="<?php echo esc_attr( lofu_product_anchor( $lofu_product_id ) ); ?>"
								aria-haspopup="dialog"
							>
								<span class="series-modal__media">
									<?php
									if ( $lofu_product_image ) {
										echo wp_get_attachment_image(
											$lofu_product_image,
											'lofu-featured-image',
											false,
											array(
												'class'   => 'series-modal__image',
												'alt'     => '',
												'sizes'   => '(min-width: 1024px) 33vw, (min-width: 768px) 50vw, 100vw',
												'loading' => 'lazy',
											)
										);
									}
									?>
								</span>
								<span class="series-modal__caption d-flex justify-content-between gap-3">
									<span class="series-modal__name"><?php echo esc_html( get_the_title( $lofu_product_id ) ); ?></span>
									<?php if ( $lofu_product_type ) : ?>
										<span class="series-modal__type"><?php echo esc_html( $lofu_product_type ); ?></span>
									<?php endif; ?>
								</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="series-modal__empty"><?php esc_html_e( 'Світильники цієї серії незабаром з’являться.', 'lofu' ); ?></p>
			<?php endif; ?>
		</div>
	</div>
</template>
