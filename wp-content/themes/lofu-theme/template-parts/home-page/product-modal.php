<?php
/**
 * Home page: one product's window content, rendered into a <template>.
 *
 * products.js clones it into the `product-modal` dialog, opened on top of
 * the series window named by the template's `data-series`. Content comes
 * from the "Product" ACF group: a slider of the gallery photos (plus an
 * optional video as the last slide), the description and specifications.
 *
 * Args:
 * - product_id (int)     Product to render.
 * - series     (WP_Term) The series it is shown in.
 *
 * @package WordPress
 * @subpackage lofu
 * @since lofu 1.0
 */

$lofu_product_id = (int) ( $args['product_id'] ?? 0 );
$lofu_term       = $args['series'] ?? null;

if ( ! $lofu_product_id || ! $lofu_term instanceof WP_Term ) {
	return;
}

$lofu_title       = get_the_title( $lofu_product_id );
$lofu_type        = get_field( 'product_type', $lofu_product_id );
$lofu_description = get_field( 'description', $lofu_product_id );
$lofu_gallery     = (array) get_field( 'gallery', $lofu_product_id );
$lofu_video_id    = (int) get_field( 'video', $lofu_product_id );
$lofu_video_url   = $lofu_video_id ? wp_get_attachment_url( $lofu_video_id ) : '';

$lofu_specs = array_filter(
	array(
		__( 'Розміри', 'lofu' )             => get_field( 'dimensions', $lofu_product_id ),
		__( 'Матеріали', 'lofu' )           => get_field( 'materials', $lofu_product_id ),
		__( 'Джерело світла', 'lofu' )      => get_field( 'light_source', $lofu_product_id ),
		__( 'Колірна температура', 'lofu' ) => get_field( 'color_temperature', $lofu_product_id ),
		__( 'Кольори', 'lofu' )             => get_field( 'colors', $lofu_product_id ),
	)
);

$lofu_eyebrow = implode(
	' · ',
	array_filter(
		array(
			/* translators: %s: series name */
			sprintf( __( 'Серія %s', 'lofu' ), $lofu_term->name ),
			$lofu_type,
		)
	)
);

$lofu_slide_count = count( $lofu_gallery ) + ( $lofu_video_url ? 1 : 0 );
?>
<template id="<?php echo esc_attr( lofu_product_anchor( $lofu_product_id ) ); ?>-modal" data-series="<?php echo esc_attr( lofu_series_anchor( $lofu_term ) ); ?>">
	<div class="product-modal__bar">
		<div class="ia-container product-modal__bar-inner d-flex align-items-center justify-content-between gap-3">
			<span class="product-modal__brand" aria-hidden="true"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
			<button type="button" class="product-modal__close d-flex align-items-center" data-modal-close>
				<span aria-hidden="true">&larr;</span>
				<?php
				/* translators: %s: series name */
				echo esc_html( sprintf( __( 'Серія %s', 'lofu' ), $lofu_term->name ) );
				?>
			</button>
		</div>
	</div>

	<div class="product-modal__body">
		<div class="ia-container">
			<div class="product-modal__layout">
				<?php if ( $lofu_slide_count ) : ?>
					<div class="product-modal__gallery d-flex flex-column" data-product-slider>
						<div class="product-modal__stage" data-photo-group>
							<ul class="product-modal__slides">
								<?php
								foreach ( $lofu_gallery as $lofu_index => $lofu_photo_id ) :
									$lofu_photo_alt = get_post_meta( $lofu_photo_id, '_wp_attachment_image_alt', true );
									$lofu_photo_alt = $lofu_photo_alt ? $lofu_photo_alt : sprintf(
										/* translators: 1: product name, 2: photo number */
										__( '%1$s, photo %2$d', 'lofu' ),
										$lofu_title,
										$lofu_index + 1
									);
									$lofu_photo_full = wp_get_attachment_image_src( $lofu_photo_id, 'full' );
									?>
									<li class="product-modal__slide"<?php echo $lofu_index ? ' hidden' : ''; ?>>
										<?php // Opens the full, uncropped photo in the photo viewer (photo-viewer.js). ?>
										<button
											type="button"
											class="product-modal__zoom"
											data-photo-src="<?php echo esc_url( $lofu_photo_full ? $lofu_photo_full[0] : '' ); ?>"
											data-photo-srcset="<?php echo esc_attr( (string) wp_get_attachment_image_srcset( $lofu_photo_id, 'full' ) ); ?>"
											data-photo-alt="<?php echo esc_attr( $lofu_photo_alt ); ?>"
											data-photo-caption="<?php echo esc_attr( (string) wp_get_attachment_caption( $lofu_photo_id ) ); ?>"
											aria-label="<?php echo esc_attr( sprintf( /* translators: %s: photo description */ __( 'Збільшити: %s', 'lofu' ), $lofu_photo_alt ) ); ?>"
										>
											<?php
											echo wp_get_attachment_image(
												$lofu_photo_id,
												'lofu-featured-image',
												false,
												array(
													'class' => 'product-modal__image',
													'alt' => $lofu_photo_alt,
													'sizes' => '(min-width: 768px) 60vw, 100vw',
													'loading' => $lofu_index ? 'lazy' : 'eager',
												)
											);
											?>
										</button>
									</li>
								<?php endforeach; ?>

								<?php if ( $lofu_video_url ) : ?>
									<li class="product-modal__slide product-modal__slide--video"<?php echo $lofu_gallery ? ' hidden' : ''; ?>>
										<video class="product-modal__video" src="<?php echo esc_url( $lofu_video_url ); ?>" controls playsinline preload="metadata" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: product name */ __( 'Відео: %s', 'lofu' ), $lofu_title ) ); ?>"></video>
									</li>
								<?php endif; ?>
							</ul>

							<?php if ( $lofu_slide_count > 1 ) : ?>
								<button type="button" class="product-modal__arrow product-modal__arrow--prev d-flex align-items-center justify-content-center" data-slide-step="-1" aria-label="<?php esc_attr_e( 'Попереднє фото', 'lofu' ); ?>">
									<svg viewBox="0 0 14 14" aria-hidden="true" focusable="false"><path d="M9 2 L4 7 L9 12" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>
								</button>
								<button type="button" class="product-modal__arrow product-modal__arrow--next d-flex align-items-center justify-content-center" data-slide-step="1" aria-label="<?php esc_attr_e( 'Наступне фото', 'lofu' ); ?>">
									<svg viewBox="0 0 14 14" aria-hidden="true" focusable="false"><path d="M5 2 L10 7 L5 12" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>
								</button>
							<?php endif; ?>
						</div>

						<?php if ( $lofu_slide_count > 1 ) : ?>
							<div class="product-modal__controls d-flex justify-content-between align-items-center gap-3">
								<div class="product-modal__thumbs d-flex flex-wrap">
									<?php foreach ( $lofu_gallery as $lofu_index => $lofu_photo_id ) : ?>
										<button
											type="button"
											class="product-modal__thumb"
											data-slide-to="<?php echo esc_attr( $lofu_index ); ?>"
											aria-label="<?php echo esc_attr( sprintf( /* translators: 1: photo number, 2: number of slides */ __( 'Показати фото %1$d з %2$d', 'lofu' ), $lofu_index + 1, $lofu_slide_count ) ); ?>"
											<?php echo $lofu_index ? '' : 'aria-current="true"'; ?>
										>
											<?php
											echo wp_get_attachment_image(
												$lofu_photo_id,
												'thumbnail',
												false,
												array(
													'class' => 'product-modal__thumb-image',
													'alt' => '',
													'loading' => 'lazy',
												)
											);
											?>
										</button>
									<?php endforeach; ?>

									<?php if ( $lofu_video_url ) : ?>
										<button
											type="button"
											class="product-modal__thumb product-modal__thumb--video d-flex align-items-center justify-content-center"
											data-slide-to="<?php echo esc_attr( count( $lofu_gallery ) ); ?>"
											aria-label="<?php esc_attr_e( 'Показати відео', 'lofu' ); ?>"
										>
											<svg viewBox="0 0 18 18" aria-hidden="true" focusable="false"><path d="M5 3 L15 9 L5 15 Z" fill="currentColor"/></svg>
										</button>
									<?php endif; ?>
								</div>

								<span class="product-modal__counter" aria-live="polite">1 / <?php echo esc_html( $lofu_slide_count ); ?></span>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<div class="product-modal__info d-flex flex-column">
					<div class="product-modal__heading d-flex flex-column">
						<span class="product-modal__eyebrow"><?php echo esc_html( $lofu_eyebrow ); ?></span>
						<h2 id="product-modal-title" class="product-modal__title"><?php echo esc_html( $lofu_title ); ?></h2>
					</div>

					<?php if ( $lofu_description ) : ?>
						<p class="product-modal__description"><?php echo esc_html( $lofu_description ); ?></p>
					<?php endif; ?>

					<?php if ( $lofu_specs ) : ?>
						<div class="product-modal__specs">
							<h3 class="product-modal__specs-title"><?php esc_html_e( 'Характеристики', 'lofu' ); ?></h3>
							<dl class="product-modal__spec-list">
								<?php foreach ( $lofu_specs as $lofu_label => $lofu_value ) : ?>
									<div class="product-modal__spec">
										<dt class="product-modal__spec-label"><?php echo esc_html( $lofu_label ); ?></dt>
										<dd class="product-modal__spec-value"><?php echo esc_html( $lofu_value ); ?></dd>
									</div>
								<?php endforeach; ?>
							</dl>
						</div>
					<?php endif; ?>

					<a class="product-modal__cta d-flex align-items-center justify-content-center" href="#contacts"><?php esc_html_e( 'Зв’язатися щодо замовлення', 'lofu' ); ?></a>
				</div>
			</div>
		</div>
	</div>
</template>
