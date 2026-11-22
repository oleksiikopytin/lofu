<?php
/**
 * Template Name: Home page
 *
 *
 * @package WordPress
 * @subpackage lofu
 * @since lofu 1.0
 */
get_header();
the_post();

/*start quiz demo */
?>


<?php
/*end quiz demo */
while ( have_rows( 'hero' ) ) {
	the_row();
	get_template_part( 'template-parts/home-page/hero' );
}



get_footer();
