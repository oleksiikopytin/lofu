<?php
/**
 * Gallery view switcher: "Detailed / Tiles / Slider" toggle buttons.
 *
 * view-switch.js sets the chosen view as `data-view` on the element named by
 * `aria-controls`; that element's own SCSS styles each view. Without JS the
 * switcher is hidden and the list stays in its default `detail` view.
 *
 * Args:
 * - controls (string) ID of the list the switcher drives.
 *
 * @package WordPress
 * @subpackage lofu
 * @since lofu 1.0
 */

$lofu_controls = $args['controls'] ?? '';

if ( ! $lofu_controls ) {
	return;
}

$lofu_views = array(
	'detail' => __( 'Детально', 'lofu' ),
	'masonry' => __( 'Плитки', 'lofu' ),
	'slider' => __( 'Слайдер', 'lofu' ),
);
?>
<div class="view-switch js-only d-flex" role="group" aria-label="<?php esc_attr_e( 'Gallery view', 'lofu' ); ?>">
	<?php foreach ( $lofu_views as $lofu_view => $lofu_label ) : ?>
		<button type="button" class="view-switch__button" data-view="<?php echo esc_attr( $lofu_view ); ?>" aria-controls="<?php echo esc_attr( $lofu_controls ); ?>" aria-pressed="<?php echo 'detail' === $lofu_view ? 'true' : 'false'; ?>"><?php echo esc_html( $lofu_label ); ?></button>
	<?php endforeach; ?>
</div>