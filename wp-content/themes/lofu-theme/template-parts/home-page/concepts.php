<?php
/**
 * Home page: concepts (one-image-at-a-time slider).
 *
 * Content comes from the "Home page" ACF group (`concepts` group field).
 * concepts.js turns `concepts__track` into a Slick slider and keeps the
 * counter, title and dots in sync. Without JS only the first slide shows
 * and the controls stay hidden.
 *
 * On scroll, concepts.js reveals the head and title text from behind their
 * `concepts__mask`, wipes the stage in and draws the dots; on each slide
 * change the title and counter digits roll to the new values.
 *
 * @package WordPress
 * @subpackage lofu
 * @since lofu 1.0
 */

$lofu_concepts = get_field( 'concepts' );
$lofu_slides = $lofu_concepts['slides'] ?? array();

if ( empty( $lofu_slides ) ) {
	return;
}

lofu_prime_posts( array_column( (array) $lofu_slides, 'image' ) );

$lofu_concepts_title = $lofu_concepts['section_title'] ?? '';
$lofu_slide_count = count( $lofu_slides );
$lofu_first_title = $lofu_slides[0]['title'] ?? '';
?>
<section id="concepts" class="concepts module  border-0" aria-roledescription="<?php esc_attr_e( 'carousel', 'lofu' ); ?>" <?php echo $lofu_concepts_title ? ' aria-labelledby="concepts-title"' : ''; ?>>
	<div class="ia-container">
		<div class="concepts__head section-label d-flex justify-content-between align-items-baseline gap-2">
			<?php if ( $lofu_concepts_title ) : ?>
				<h2 id="concepts-title" class="concepts__title concepts__mask"><span class="concepts__title-text concepts__mask-inner"><?php echo esc_html( $lofu_concepts_title ); ?></span></h2>
			<?php endif; ?>
			<?php if ( $lofu_slide_count > 1 ) : ?>
				<span class="concepts__counter concepts__mask js-only" aria-hidden="true">
					<span class="concepts__counter-text concepts__mask-inner"><span class="concepts__counter-current">01</span> / <?php echo esc_html( sprintf( '%02d', $lofu_slide_count ) ); ?></span>
				</span>
			<?php endif; ?>
		</div>

		<div class="concepts__stage">
			<div class="concepts__track">
				<?php
				foreach ( $lofu_slides as $lofu_index => $lofu_slide ) :
					$lofu_slide_title = $lofu_slide['title'] ?? '';
					$lofu_image_attrs = array(
						'class' => 'concepts__image',
						'alt' => $lofu_slide_title,
						'sizes' => '(min-width: 1440px) 1360px, 100vw',
						'loading' => 'lazy',
					);
					?>
					<div class="concepts__slide" role="group" aria-roledescription="<?php esc_attr_e( 'slide', 'lofu' ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: slide number, 2: slide count */ __( '%1$d of %2$d', 'lofu' ), $lofu_index + 1, $lofu_slide_count ) ); ?>"
						data-title="<?php echo esc_attr( $lofu_slide_title ); ?>">
						<?php if ( ! empty( $lofu_slide['image'] ) ) : ?>
							<?php
							// Every slide's image loads only as the section nears the
							// viewport (concepts.js); without JS the first one shows
							// from <noscript> instead.
							echo wp_get_attachment_image( $lofu_slide['image'], 'lofu-featured-image', false, $lofu_image_attrs + array( 'data-defer' => true ) );

							if ( 0 === $lofu_index ) {
								echo '<noscript>' . wp_get_attachment_image( $lofu_slide['image'], 'lofu-featured-image', false, $lofu_image_attrs ) . '</noscript>';
							}
							?>
						<?php else : ?>
							<div class="concepts__placeholder d-flex align-items-center justify-content-center">
								<?php
								/* translators: %s: concept title */
								echo esc_html( sprintf( __( '%s — concept visualisation', 'lofu' ), $lofu_slide_title ) );
								?>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if ( $lofu_slide_count > 1 ) : ?>
				<button type="button" class="concepts__arrow concepts__arrow--prev js-only d-flex align-items-center justify-content-center" aria-label="<?php esc_attr_e( 'Previous concept', 'lofu' ); ?>">
					<svg viewBox="0 0 14 14" aria-hidden="true" focusable="false">
						<path d="M9 2 L4 7 L9 12" fill="none" stroke="currentColor" stroke-width="1.5" />
					</svg>
				</button>
				<button type="button" class="concepts__arrow concepts__arrow--next js-only d-flex align-items-center justify-content-center" aria-label="<?php esc_attr_e( 'Next concept', 'lofu' ); ?>">
					<svg viewBox="0 0 14 14" aria-hidden="true" focusable="false">
						<path d="M5 2 L10 7 L5 12" fill="none" stroke="currentColor" stroke-width="1.5" />
					</svg>
				</button>
			<?php endif; ?>
		</div>

		<div class="concepts__foot d-flex justify-content-between align-items-center flex-wrap">
			<p class="concepts__name concepts__mask" aria-live="polite"><span class="concepts__name-text concepts__mask-inner"><?php echo esc_html( $lofu_first_title ); ?></span></p>

			<?php if ( $lofu_slide_count > 1 ) : ?>
				<div class="concepts__dots js-only d-flex">
					<?php foreach ( $lofu_slides as $lofu_index => $lofu_slide ) : ?>
						<button type="button" class="concepts__dot d-flex align-items-center" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: concept title */ __( 'Show: %s', 'lofu' ), $lofu_slide['title'] ?? '' ) ); ?>"
							aria-current="<?php echo 0 === $lofu_index ? 'true' : 'false'; ?>"></button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>