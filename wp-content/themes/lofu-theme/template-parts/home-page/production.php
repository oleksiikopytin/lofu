<?php
/**
 * Home page: production (gallery of production photos and videos).
 *
 * The title, description and the ordered photos/videos come from the
 * "Home page" ACF group (`production` group field).
 *
 * The tiles are laid out by the view switcher (detail grid, masonry tiles or
 * a horizontal slider); the detail grid's positions and every tile's crop
 * repeat every 4 tiles (see production.scss). Each tile links to its full
 * photo or video file, so it still works without JS; with JS a photo opens
 * in the shared photo viewer and a video in the `production-video` dialog
 * (production.js).
 *
 * @package WordPress
 * @subpackage lofu
 * @since lofu 1.0
 */

$lofu_production_field = get_field( 'production' );
$lofu_production_items = array();

foreach ( (array) ( $lofu_production_field['items'] ?? array() ) as $lofu_row ) {
	$lofu_image_id = (int) ( $lofu_row['image'] ?? 0 );
	$lofu_video_id = 'video' === ( $lofu_row['type'] ?? '' ) ? (int) ( $lofu_row['video'] ?? 0 ) : 0;
	$lofu_video    = $lofu_video_id ? wp_get_attachment_url( $lofu_video_id ) : '';

	if ( ! $lofu_video && ! $lofu_image_id ) {
		continue;
	}

	$lofu_production_items[] = array(
		'image'   => $lofu_image_id,
		'video'   => $lofu_video,
		'caption' => trim( (string) ( $lofu_row['caption'] ?? '' ) ),
	);
}

if ( empty( $lofu_production_items ) ) {
	return;
}

$lofu_production_title       = $lofu_production_field['section_title'] ?? '';
$lofu_production_description = $lofu_production_field['section_description'] ?? '';
$lofu_has_video              = (bool) array_filter( wp_list_pluck( $lofu_production_items, 'video' ) );
?>
<section id="production" class="production"<?php echo $lofu_production_title ? ' aria-labelledby="production-title"' : ''; ?>>
	<div class="ia-container">
		<?php if ( $lofu_production_title || $lofu_production_description ) : ?>
			<div class="production__head d-flex justify-content-between align-items-end flex-wrap">
				<?php if ( $lofu_production_title ) : ?>
					<h2 id="production-title" class="production__title production__mask"><span class="production__mask-inner"><?php echo esc_html( $lofu_production_title ); ?></span></h2>
				<?php endif; ?>

				<?php if ( $lofu_production_description ) : ?>
					<p class="production__description"><?php echo esc_html( $lofu_production_description ); ?></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="production__switch d-flex justify-content-end">
			<?php get_template_part( 'template-parts/components/view-switch', null, array( 'controls' => 'production-list' ) ); ?>
		</div>

		<ul id="production-list" class="production__list" data-view="detail" data-photo-group>
			<?php
			foreach ( $lofu_production_items as $lofu_item ) :
				$lofu_image_id = $lofu_item['image'];
				$lofu_caption  = $lofu_item['caption'];
				$lofu_is_video = (bool) $lofu_item['video'];
				$lofu_alt      = $lofu_image_id ? (string) get_post_meta( $lofu_image_id, '_wp_attachment_image_alt', true ) : '';
				$lofu_alt      = $lofu_alt ? $lofu_alt : $lofu_caption;

				if ( $lofu_is_video ) {
					$lofu_href   = $lofu_item['video'];
					$lofu_poster = $lofu_image_id ? wp_get_attachment_image_src( $lofu_image_id, 'lofu-featured-image' ) : false;
					$lofu_label  = $lofu_caption
						/* translators: %s: video caption */
						? sprintf( __( 'Відтворити відео: %s', 'lofu' ), $lofu_caption )
						: __( 'Відтворити відео з виробництва', 'lofu' );
				} else {
					$lofu_full  = wp_get_attachment_image_src( $lofu_image_id, 'full' );
					$lofu_href  = $lofu_full ? $lofu_full[0] : '';
					$lofu_label = $lofu_caption
						/* translators: %s: photo caption */
						? sprintf( __( 'Відкрити фото: %s', 'lofu' ), $lofu_caption )
						: __( 'Відкрити фото з виробництва', 'lofu' );
				}
				?>
				<li class="production__item">
					<?php if ( $lofu_is_video ) : ?>
						<a
							class="production__card production__card--video"
							href="<?php echo esc_url( $lofu_href ); ?>"
							data-video-src="<?php echo esc_url( $lofu_href ); ?>"
							data-video-poster="<?php echo esc_url( $lofu_poster ? $lofu_poster[0] : '' ); ?>"
							data-video-caption="<?php echo esc_attr( $lofu_caption ); ?>"
							aria-label="<?php echo esc_attr( $lofu_label ); ?>"
							aria-haspopup="dialog"
						>
					<?php else : ?>
						<a
							class="production__card"
							href="<?php echo esc_url( $lofu_href ); ?>"
							data-photo-src="<?php echo esc_url( $lofu_href ); ?>"
							data-photo-srcset="<?php echo esc_attr( (string) wp_get_attachment_image_srcset( $lofu_image_id, 'full' ) ); ?>"
							data-photo-alt="<?php echo esc_attr( $lofu_alt ); ?>"
							data-photo-caption="<?php echo esc_attr( $lofu_caption ); ?>"
							aria-label="<?php echo esc_attr( $lofu_label ); ?>"
							aria-haspopup="dialog"
						>
					<?php endif; ?>
						<span class="production__media d-flex align-items-center justify-content-center">
							<?php
							if ( $lofu_image_id ) {
								echo wp_get_attachment_image(
									$lofu_image_id,
									'lofu-featured-image',
									false,
									array(
										'class'   => 'production__image',
										'alt'     => '',
										'sizes'   => '(min-width: 768px) 50vw, 100vw',
										'loading' => 'lazy',
									)
								);
							}
							?>

							<?php if ( $lofu_is_video ) : ?>
								<span class="production__play d-flex align-items-center justify-content-center" aria-hidden="true">
									<svg viewBox="0 0 18 18" focusable="false"><path d="M5 3 L15 9 L5 15 Z" fill="currentColor"/></svg>
								</span>
							<?php endif; ?>
						</span>
						<span class="production__caption d-flex justify-content-between">
							<span class="production__name"><?php echo esc_html( $lofu_caption ); ?></span>
							<span class="production__kind"><?php echo $lofu_is_video ? esc_html__( 'Відео', 'lofu' ) : esc_html__( 'Фото', 'lofu' ); ?></span>
						</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>

<?php if ( $lofu_has_video ) : ?>
	<dialog id="production-video" class="production-video" aria-label="<?php esc_attr_e( 'Відео з виробництва', 'lofu' ); ?>">
		<div class="production-video__stage">
			<figure class="production-video__figure">
				<video class="production-video__player" controls playsinline preload="none"></video>
				<figcaption class="production-video__caption" aria-live="polite"></figcaption>
			</figure>
		</div>

		<button type="button" class="production-video__close d-flex align-items-center justify-content-center" aria-label="<?php esc_attr_e( 'Закрити', 'lofu' ); ?>">
			<svg viewBox="0 0 14 14" aria-hidden="true" focusable="false"><path d="M2 2 L12 12 M12 2 L2 12" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>
		</button>
	</dialog>
<?php endif; ?>
