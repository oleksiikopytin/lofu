<?php
/**
 * Full-screen photo viewer (one per page).
 *
 * photo-viewer.js opens it when a `[data-photo-src]` button is clicked and
 * steps through the other buttons in the same `[data-photo-group]`. It is a
 * native modal <dialog>, so it stacks above another open dialog (the project
 * window) and Esc closes only the viewer.
 *
 * Render it once, after the content that uses it, and enqueue
 * `lofu-photo-viewer` (style + script) on the same page.
 *
 * @package WordPress
 * @subpackage lofu
 * @since lofu 1.0
 */

?>
<dialog id="photo-viewer" class="photo-viewer" aria-label="<?php esc_attr_e( 'Перегляд фото', 'lofu' ); ?>">
	<div class="photo-viewer__stage">
		<figure class="photo-viewer__figure">
			<img class="photo-viewer__image" src="data:," alt="" sizes="100vw" decoding="async">
			<figcaption class="photo-viewer__caption d-flex justify-content-between gap-3" aria-live="polite">
				<span class="photo-viewer__caption-text"></span>
				<span class="photo-viewer__counter"></span>
			</figcaption>
		</figure>
	</div>

	<button type="button" class="photo-viewer__button photo-viewer__close d-flex align-items-center justify-content-center" aria-label="<?php esc_attr_e( 'Закрити', 'lofu' ); ?>">
		<svg viewBox="0 0 14 14" aria-hidden="true" focusable="false"><path d="M2 2 L12 12 M12 2 L2 12" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>
	</button>
	<button type="button" class="photo-viewer__button photo-viewer__arrow photo-viewer__arrow--prev d-flex align-items-center justify-content-center" aria-label="<?php esc_attr_e( 'Попереднє фото', 'lofu' ); ?>">
		<svg viewBox="0 0 14 14" aria-hidden="true" focusable="false"><path d="M9 2 L4 7 L9 12" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>
	</button>
	<button type="button" class="photo-viewer__button photo-viewer__arrow photo-viewer__arrow--next d-flex align-items-center justify-content-center" aria-label="<?php esc_attr_e( 'Наступне фото', 'lofu' ); ?>">
		<svg viewBox="0 0 14 14" aria-hidden="true" focusable="false"><path d="M5 2 L10 7 L5 12" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>
	</button>
</dialog>
