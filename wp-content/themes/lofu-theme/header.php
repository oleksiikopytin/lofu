<?php
/**
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WordPress
 * @subpackage lofu
 * @since 1.0
 * @version 1.0
 */
?>

<!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js no-svg <?php echo ! wp_is_mobile() ? 'desktop-version' : 'device-version'; ?>">

<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
	<meta name="format-detection" content="telephone=no">
	<meta http-equiv="X-UA-Compatible" content="IE=EDGE" />
	<link rel="profile" href="http://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
	<?php wp_body_open(); ?>
	<!-- main container of all the page elements -->
	<div id="wrapper">
		<header class="header">
			<div class="ia-container py-5">
				<div class="logo">
					<a href="<?php echo esc_url( get_home_url() ); ?>">
						<img src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/images/logo.svg" class="nolazy" width="232" height="22" alt="Logo of LOFU">
					</a>
				</div>

			</div>
		</header>
		<!-- End the header of the page -->

		<!-- contain main informative part of the site -->
		<main>
			<div id="content">