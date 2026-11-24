<?php
/**
 *
 * @package WordPress
 * @subpackage lofu
 * @since 1.0
 * @version 1.0
 */

get_header(); ?>
<div class="three-columns wow fadeInUpS">
	<div class="inner">
		<?php if ( have_posts() ) : ?>
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<?php echo esc_html( get_the_title() ); ?>
			<?php endwhile; ?>
		<?php endif; ?>
	</div>
</div>
<?php
get_footer();