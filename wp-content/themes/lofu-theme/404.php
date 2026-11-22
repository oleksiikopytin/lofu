<?php
/**
 * The template for displaying 404 pages (not found)
 *
 * @link https://codex.wordpress.org/Creating_an_Error_404_Page
 *
 * @package WordPress
 * @subpackage lofu
 * @since 1.0
 * @version 1.0
 */

get_header(); ?>

<div class="extra-space-header"></div>

<div class="content-section">
	<div class="inner">
		<div class="content-thin">
			<div class="content-entry">
				<h1 class="title-section"><?php echo __("Oops! That page can’t be found.", "lofu"); ?></h1>
				<p><?php echo __("It looks like nothing was found at this location. ", "lofu"); ?><a href="<?php echo get_home_url(); ?>"><?php echo __("Go back Home", "lofu"); ?></a></p>
			</div>
		</div>
	</div>
</div>

<?php get_footer();