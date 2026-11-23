<?php
/**
 * Home page: about (facts, intro paragraph and numbered pillars).
 *
 * Content comes from the "Home page: About" ACF group (`about` group field).
 * On scroll, about.js reveals the `about__rows` title/fact rows and the
 * `about__lines` text line by line, and fades `about__reveal` elements in.
 *
 * @package WordPress
 * @subpackage lofu
 * @since lofu 1.0
 */

$lofu_about = get_field( 'about' );

if ( empty( $lofu_about ) ) {
	return;
}

$lofu_about_title       = $lofu_about['section_title'] ?? '';
$lofu_about_description = $lofu_about['description'] ?? '';
?>
<section id="about" class="about"<?php echo $lofu_about_title ? ' aria-labelledby="about-title"' : ''; ?>>
	<div class="ia-container">
		<div class="about__intro">
			<div class="about__aside about__rows d-flex flex-column gap-3">
				<?php if ( $lofu_about_title ) : ?>
					<h2 id="about-title" class="about__title"><span class="about__title-text"><?php echo esc_html( $lofu_about_title ); ?></span></h2>
				<?php endif; ?>

				<?php if ( have_rows( 'about' ) ) : ?>
					<?php
					while ( have_rows( 'about' ) ) :
						the_row();
						if ( have_rows( 'facts' ) ) :
							?>
							<dl class="about__facts d-flex flex-column gap-3">
								<?php
								while ( have_rows( 'facts' ) ) :
									the_row();
									?>
									<div class="about__fact">
										<dt class="about__fact-label"><?php echo esc_html( get_sub_field( 'label' ) ); ?></dt>
										<dd class="about__fact-value"><?php echo esc_html( get_sub_field( 'value' ) ); ?></dd>
									</div>
								<?php endwhile; ?>
							</dl>
							<?php
						endif;
					endwhile;
					?>
				<?php endif; ?>
			</div>

			<?php if ( $lofu_about_description ) : ?>
				<p class="about__description about__lines"><?php echo esc_html( $lofu_about_description ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( have_rows( 'about' ) ) : ?>
			<?php
			while ( have_rows( 'about' ) ) :
				the_row();
				if ( have_rows( 'pillars' ) ) :
					?>
					<ol class="about__pillars">
						<?php
						while ( have_rows( 'pillars' ) ) :
							the_row();
							$lofu_pillar_text = get_sub_field( 'text' );
							?>
							<li class="about__pillar about__reveal d-flex flex-column gap-3">
								<span class="about__pillar-number" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', get_row_index() ) ); ?></span>
								<h3 class="about__pillar-title"><?php echo esc_html( get_sub_field( 'title' ) ); ?></h3>
								<?php if ( $lofu_pillar_text ) : ?>
									<p class="about__pillar-text"><?php echo esc_html( $lofu_pillar_text ); ?></p>
								<?php endif; ?>
							</li>
						<?php endwhile; ?>
					</ol>
					<?php
				endif;
			endwhile;
			?>
		<?php endif; ?>
	</div>
</section>
