<?php
/**
 * wp-admin tweaks.
 *
 * @package WordPress
 * @subpackage lofu
 * @since lofu 1.0
 */

/**
 * Hides WPML's "The WPML site key on this site was not accepted, so no
 * updates are offered for it" notice; every other WPML Installer notice
 * still shows.
 *
 * Installer (bundled with WPML) has no filter for its notices and keeps them
 * in a private list, so its printer is swapped for a wrapper that drops that
 * one message from the output.
 */
function lofu_replace_installer_notices() {
	if ( ! function_exists( 'WP_Installer' ) ) {
		return;
	}

	$installer = WP_Installer();

	if ( false === has_action( 'admin_notices', array( $installer, 'show_admin_messages' ) ) ) {
		return;
	}

	remove_action( 'admin_notices', array( $installer, 'show_admin_messages' ) );
	add_action( 'admin_notices', 'lofu_installer_notices' );
}
add_action( 'admin_init', 'lofu_replace_installer_notices' );

/**
 * Prints Installer's notices without the "site key was not accepted" one.
 */
function lofu_installer_notices() {
	ob_start();
	WP_Installer()->show_admin_messages();
	$notices = (string) ob_get_clean();

	// Installer's own sentence, in the admin's language. The repository name
	// (%s) varies, so match the text on either side of it.
	// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch, WordPress.WP.I18n.MissingTranslatorsComment -- Installer's string, matched in its own translation.
	$sentence = __( 'The %s site key on this site was not accepted, so no updates are offered for it. Register the site again to get updates.', 'installer' );
	$parts    = array_map(
		function ( $part ) {
			return preg_quote( trim( $part ), '/' );
		},
		explode( '%s', $sentence, 2 )
	);
	$pattern  = '/<div class="[^"]*">\s*<p>\s*' . implode( '.*?', $parts ) . '.*?<\/p>\s*<\/div>/s';

	echo preg_replace( $pattern, '', $notices ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Installer's own markup, printed as Installer would.
}
