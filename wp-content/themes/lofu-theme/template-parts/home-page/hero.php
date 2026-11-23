<?php
/**
 * Home page: hero (LOFU wordmark + tagline).
 *
 * Every letter of the design's filled wordmark is rebuilt as a thick stroke
 * along its centre line (stroke 44, the letters' band width; 40 for the F
 * bar), so each one can draw itself in with stroke-dashoffset. E.g. the "O"
 * ring between r 64 and r 108 becomes a circle of r 86. The circles run 5°
 * past a full turn so the stroke's end overlaps its start, which would
 * otherwise leave an anti-aliased hairline seam at the top.
 *
 * @package WordPress
 * @subpackage lofu
 * @since lofu 1.0
 */

$lofu_hero_tagline = __( 'Where Light Is Born', 'lofu' );
$lofu_hero_words   = preg_split( '/\s+/u', trim( $lofu_hero_tagline ) );
?>
<section id="top" class="hero">
	<div class="ia-container hero__inner d-flex flex-column align-items-center justify-content-center gap-4 text-center">
		<h1 class="hero__title">
			<span class="screen-reader-text"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
			<span class="hero__logo" aria-hidden="true">
				<svg class="hero__mark" viewBox="70 60 800 280" focusable="false">
					<g fill="none" stroke="currentColor" stroke-width="44">
						<path class="hero__stroke hero__stroke--l" d="M102 115 V245 Q102 308 165 308 H185" pathLength="1"/>
						<path class="hero__stroke hero__stroke--o" d="M318 136 A86 86 0 1 1 318 308 A86 86 0 1 1 325.5 136.33" pathLength="1"/>
						<path class="hero__stroke hero__stroke--f" d="M484 330 V185 Q484 137 532 137 H575" pathLength="1"/>
						<path class="hero__stroke hero__stroke--f-bar" d="M506 225 H575" stroke-width="40" pathLength="1"/>
						<path class="hero__stroke hero__stroke--u" d="M647 115 V230 A71.5 71.5 0 0 0 790 230 V115" pathLength="1"/>
					</g>
					<g class="hero__reg">
						<path class="hero__stroke hero__stroke--reg" d="M848 69 A13 13 0 1 1 848 95 A13 13 0 1 1 849.13 69.05" fill="none" stroke="currentColor" stroke-width="2.5" pathLength="1"/>
						<text class="hero__reg-letter" x="848" y="88" text-anchor="middle" font-size="16" font-weight="600" fill="currentColor">R</text>
					</g>
				</svg>
			</span>
		</h1>

		<p class="hero__tagline">
			<?php foreach ( $lofu_hero_words as $lofu_word ) : ?>
				<span class="hero__word"><span><?php echo esc_html( $lofu_word ); ?></span></span>
			<?php endforeach; ?>
		</p>
	</div>

	<a class="hero__scroll" href="#about">
		<span class="screen-reader-text"><?php esc_html_e( 'Scroll to the next section', 'lofu' ); ?></span>
		<svg class="hero__scroll-icon" viewBox="0 0 24 14" aria-hidden="true" focusable="false">
			<path d="M1.5 1.5 L12 12 L22.5 1.5" fill="none" stroke="currentColor"/>
		</svg>
	</a>
</section>
