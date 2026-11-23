<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WordPress
 * @subpackage lofu
 * @since 1.0
 * @version 1.0
 */

?>
</div>
<!-- End of the content -->
</main>
<!-- End of the main -->

<footer class="footer">
	<div class="ia-container">
		<div class="footer__bar d-flex justify-content-between flex-wrap">
			<span>
				<?php
				/* translators: 1: current year, 2: site name. */
				echo esc_html( sprintf( __( '© %1$s %2$s', 'lofu' ), wp_date( 'Y' ), get_bloginfo( 'name' ) ) );
				?>
			</span>
			<span><?php esc_html_e( 'Дизайнерські світильники · Зроблено в Україні', 'lofu' ); ?></span>
			<a class="footer__top" href="#wrapper"><?php esc_html_e( 'Нагору', 'lofu' ); ?> <span aria-hidden="true">↑</span></a>
		</div>
	</div>
</footer>

</div>
<!-- End of the wrapper -->
<?php wp_footer(); ?>
</body>

</html>