<?php
/**
 * The template for displaying all single posts
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package WordPress
 * @subpackage lofu
 * @since 1.0
 * @version 1.0
 */
get_header();
the_post(); ?>
<div class="inner">
	<?php the_content(); ?>
</div>
<?php
get_footer();