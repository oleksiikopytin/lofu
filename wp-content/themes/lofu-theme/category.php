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
			<?php while ( have_posts() ) :
				the_post(); ?>
				<?php echo esc_html( get_the_title() ); ?>
			<?php endwhile; ?>
		<?php endif; ?>
	</div>
</div>
<?php
// wp_enqueue_style( 'hero-category-page', get_template_directory_uri() . "/assets/css/pages/knowledge-center/hero.css" );
// wp_enqueue_style( 'dropdown-post', get_template_directory_uri() . "/assets/css/pages/knowledge-center/dropdown.css" );
// wp_enqueue_style( 'navigation-post', get_template_directory_uri() . "/assets/css/pages/knowledge-center/navigation.css" );
// wp_enqueue_script( 'ajax-posts-js', get_theme_file_uri( '/assets/js/pages/knowledge-center/ajax-posts.js' ), array(), null, true );
get_footer();