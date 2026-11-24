<?php
/**
 * The header for the theme.
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
<html <?php language_attributes(); ?> class="no-js no-svg">

<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
	<meta name="format-detection" content="telephone=no">
	<meta http-equiv="X-UA-Compatible" content="IE=EDGE" />
	<link rel="icon" type="image/png" href="<?php echo esc_url( get_theme_file_uri( '/assets/images/favicon-96x96.png' ) ); ?>" sizes="96x96" />
	<link rel="icon" type="image/svg+xml" href="<?php echo esc_url( get_theme_file_uri( '/assets/images/favicon.svg' ) ); ?>" />
	<link rel="shortcut icon" href="<?php echo esc_url( get_theme_file_uri( '/assets/images/favicon.ico' ) ); ?>" />
	<link rel="apple-touch-icon" sizes="180x180" href="<?php echo esc_url( get_theme_file_uri( '/assets/images/apple-touch-icon.png' ) ); ?>" />
	<meta name="apple-mobile-web-app-title" content="LOFU" />
	<link rel="manifest" href="<?php echo esc_url( get_theme_file_uri( '/assets/images/site.webmanifest' ) ); ?>" />
	<link rel="profile" href="http://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
	<?php wp_body_open(); ?>
	<a class="skip-to-main-content-link" href="#content"><?php esc_html_e( 'Skip to content', 'lofu' ); ?></a>
	<!-- main container of all the page elements -->
	<div id="wrapper">
		<header class="header">
			<div class="ia-container header__inner d-flex align-items-center justify-content-between gap-4">
				<?php if ( has_nav_menu( 'main' ) ) : ?>
					<button type="button" class="header__burger" aria-expanded="false" aria-controls="header-nav">
						<span class="header__burger-lines" aria-hidden="true"></span>
						<span class="screen-reader-text"><?php esc_html_e( 'Menu', 'lofu' ); ?></span>
					</button>

					<nav id="header-nav" class="header__nav" aria-label="<?php esc_attr_e( 'Site sections', 'lofu' ); ?>">
						<?php
						wp_nav_menu(
							array(
								'theme_location' => 'main',
								'container'      => false,
								'menu_class'     => 'header__menu',
								'depth'          => 1,
								'fallback_cb'    => false,
							)
						);
						?>
					</nav>
				<?php endif; ?>

				<div class="header__lang">
					<?php
					do_action(
						'wpml_language_switcher', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public hook.
						array(
							'template'     => 'wpml-legacy-dropdown-click',
							'flags'        => 0,
							'native'       => 1,
							'translated'   => 0,
							'link_current' => 1,
						)
					);
					?>
				</div>
			</div>
		</header>
		<!-- End the header of the page -->

		<!-- contain main informative part of the site -->
		<main>
			<div id="content">