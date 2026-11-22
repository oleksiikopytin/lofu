<?php
/**
 * The template for displaying all pages
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site may use a
 * different template.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPress
 * @subpackage lofu
 * @since 1.0
 * @version 1.0
 */

get_header();
the_post(); ?>

<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<div class="hero-headline first-screen">
		<div class="inner">
			<div class="hero-headline-in">
				<h1 class="fadeInUpS wow" data-wow-duration="0.8s" data-wow-delay="0.3s"><?php the_title(); ?></h1>
			</div>
		</div>
	</div>

	<div class="content-block wow fadeInUpS" data-wow-duration="0.8s" data-wow-delay="0.2s">
		<div class="inner">
			<div class="content-block-inner">
				<div class="content-entry">
					<?php the_content(); ?>
					<?php
					wp_link_pages(
						array(
							'before' => '<div class="page-links">' . __( 'Pages:', 'lofu' ),
							'after' => '</div>',
						)
					);
					?>
				</div>
			</div>
		</div>
	</div>
</div>

<?php get_footer();
