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

get_footer();
