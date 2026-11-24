<?php
/**
 * Home page: partners (endless marquee of partner logos).
 *
 * The title and the ordered logos come from the "Home page" ACF group
 * (`partners` group field). Each logo can link to the partner's website.
 *
 * The strip is pure CSS (partners.scss): the track holds the logo set twice
 * and slides by half its width, so the loop is seamless. The set is repeated
 * until it has at least 8 logos so a short list still
 * fills wide screens. Only the first set is exposed to assistive tech and
 * keyboard focus; the copies are decorative.
 *
 * @package WordPress
 * @subpackage lofu
 * @since lofu 1.0
 */

$lofu_partners_field = get_field( 'partners' );
$lofu_partners       = array();

lofu_prime_posts( array_column( (array) ( $lofu_partners_field['items'] ?? array() ), 'logo' ) );

foreach ( (array) ( $lofu_partners_field['items'] ?? array() ) as $lofu_row ) {
	$lofu_logo_id = (int) ( $lofu_row['logo'] ?? 0 );

	if ( ! $lofu_logo_id ) {
		continue;
	}

	$lofu_partners[] = array(
		'logo' => $lofu_logo_id,
		'name' => trim( (string) ( $lofu_row['name'] ?? '' ) ),
		'link' => trim( (string) ( $lofu_row['link'] ?? '' ) ),
	);
}

if ( empty( $lofu_partners ) ) {
	return;
}

$lofu_partners_count   = count( $lofu_partners );
$lofu_partners_repeats = (int) ceil( 8 / $lofu_partners_count );
$lofu_partners_set     = array_merge( ...array_fill( 0, $lofu_partners_repeats, $lofu_partners ) );
$lofu_partners_title   = $lofu_partners_field['section_title'] ?? '';
?>
<section id="partners" class="partners module pb-0"<?php echo $lofu_partners_title ? ' aria-labelledby="partners-title"' : ' aria-label="' . esc_attr__( 'Partners', 'lofu' ) . '"'; ?>>
	<?php if ( $lofu_partners_title ) : ?>
		<div class="ia-container">
			<h2 id="partners-title" class="partners__title section-label reveal"><span class="reveal__inner"><?php echo esc_html( $lofu_partners_title ); ?></span></h2>
		</div>
	<?php endif; ?>

	<div class="partners__marquee">
		<div class="partners__track d-flex">
			<?php for ( $lofu_copy = 0; $lofu_copy < 2; $lofu_copy++ ) : ?>
				<ul class="partners__list d-flex align-items-center"<?php echo $lofu_copy ? ' aria-hidden="true"' : ''; ?>>
					<?php
					foreach ( $lofu_partners_set as $lofu_index => $lofu_partner ) :
						// Repeats that fill the first set are decorative too.
						$lofu_is_copy    = $lofu_copy || $lofu_index >= $lofu_partners_count;
						$lofu_logo_attrs = array(
							'class'    => 'partners__logo',
							'loading'  => 'lazy',
							'decoding' => 'async',
						);

						// Without a name, the Media Library alt text is kept.
						if ( $lofu_is_copy ) {
							$lofu_logo_attrs['alt'] = '';
						} elseif ( $lofu_partner['name'] ) {
							$lofu_logo_attrs['alt'] = $lofu_partner['name'];
						}

						$lofu_logo = wp_get_attachment_image( $lofu_partner['logo'], 'medium', false, $lofu_logo_attrs );
						?>
						<li class="partners__item d-flex align-items-center"<?php echo ( $lofu_is_copy && ! $lofu_copy ) ? ' aria-hidden="true"' : ''; ?>>
							<?php if ( $lofu_partner['link'] ) : ?>
								<a class="partners__link" href="<?php echo esc_url( $lofu_partner['link'] ); ?>" target="_blank" rel="noopener"<?php echo $lofu_is_copy ? ' tabindex="-1"' : ''; ?>>
									<?php echo $lofu_logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() output is escaped. ?>
									<?php if ( ! $lofu_is_copy ) : ?>
										<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'lofu' ); ?></span>
									<?php endif; ?>
								</a>
							<?php else : ?>
								<?php echo $lofu_logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image() output is escaped. ?>
							<?php endif; ?>
							<span class="partners__dot" aria-hidden="true"></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endfor; ?>
		</div>
	</div>
</section>
