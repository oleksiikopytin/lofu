<?php
/**
 * Home page: the order form window (one per page).
 *
 * order-modal.js opens it from a product window's "contact us about an
 * order" button (`[data-order-product]`), shows the product's name in the
 * heading and fills it into the form's `product` field. It is a native modal
 * <dialog>, so it stacks above the product window and Esc closes only it.
 *
 * Args:
 * - form_id (int) Contact Form 7 form to show (the "Order form" field of the
 *                 "Home page" ACF group); its translation is used if any.
 *
 * @package WordPress
 * @subpackage lofu
 * @since lofu 1.0
 */

// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public hook.
$lofu_form_id = (int) apply_filters( 'wpml_object_id', (int) ( $args['form_id'] ?? 0 ), 'wpcf7_contact_form', true );

if ( ! $lofu_form_id || 'wpcf7_contact_form' !== get_post_type( $lofu_form_id ) || ! shortcode_exists( 'contact-form-7' ) ) {
	return;
}
?>
<dialog id="order-modal" class="order-modal" aria-labelledby="order-modal-title">
	<div class="order-modal__panel">
		<div class="order-modal__head d-flex justify-content-between align-items-start gap-3">
			<div class="order-modal__heading d-flex flex-column">
				<span class="order-modal__eyebrow"><?php esc_html_e( 'Запит щодо замовлення', 'lofu' ); ?></span>
				<h2 id="order-modal-title" class="order-modal__title" data-order-product-name></h2>
			</div>
			<button type="button" class="order-modal__close d-flex align-items-center justify-content-center" data-order-close aria-label="<?php esc_attr_e( 'Закрити', 'lofu' ); ?>">
				<svg viewBox="0 0 14 14" aria-hidden="true" focusable="false"><path d="M2 2 L12 12 M12 2 L2 12" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>
			</button>
		</div>

		<?php
		// Contact Form 7 builds and escapes the form markup itself.
		echo do_shortcode( sprintf( '[contact-form-7 id="%d" html_class="order-modal__form"]', $lofu_form_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		?>
	</div>
</dialog>
