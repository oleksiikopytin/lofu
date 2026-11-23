<?php
/**
 * Template Name: Home page
 *
 * The landing page is assembled from template-parts/home-page/* sections.
 *
 * @package WordPress
 * @subpackage lofu
 * @since lofu 1.0
 */

get_header();

get_template_part( 'template-parts/home-page/hero' );
get_template_part( 'template-parts/home-page/about' );
get_template_part( 'template-parts/home-page/concepts' );
get_template_part( 'template-parts/home-page/projects' );
get_template_part( 'template-parts/home-page/products' );
get_template_part( 'template-parts/home-page/production' );

// Full-screen photo viewer, shared by the project and product windows and the production gallery.
get_template_part( 'template-parts/components/photo-viewer' );

get_footer();
