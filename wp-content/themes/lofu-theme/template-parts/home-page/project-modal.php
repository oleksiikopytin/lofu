<?php
/**
 * Home page: one project's details, rendered into a <template>.
 *
 * projects.js clones it into the `project-modal` dialog. Inside a template
 * nothing renders and no image loads until the project is opened.
 * Content comes from the "Project" ACF group.
 *
 * Args:
 * - project_id (int) Project to render.
 * - index      (int) Its 0-based position in the home page list.
 * - count      (int) Number of projects in the list.
 * - prev_id    (int) Previous project (wraps around).
 * - next_id    (int) Next project (wraps around).
 *
 * @package WordPress
 * @subpackage lofu
 * @since lofu 1.0
 */

$lofu_project_id = (int) ( $args['project_id'] ?? 0 );

if ( ! $lofu_project_id ) {
	return;
}

$lofu_count = (int) ( $args['count'] ?? 1 );
$lofu_prev_id = (int) ( $args['prev_id'] ?? 0 );
$lofu_next_id = (int) ( $args['next_id'] ?? 0 );
$lofu_title = get_the_title( $lofu_project_id );
$lofu_gallery = get_field( 'gallery', $lofu_project_id );
$lofu_task = get_field( 'task', $lofu_project_id );
$lofu_solution = get_field( 'solution', $lofu_project_id );

$lofu_facts = array_filter(
	array(
		__( 'Місцезнаходження', 'lofu' ) => get_field( 'location', $lofu_project_id ),
		__( 'Рік', 'lofu' ) => get_field( 'year', $lofu_project_id ),
		__( 'Тип об’єкта', 'lofu' ) => get_field( 'object_type', $lofu_project_id ),
		__( 'Оснащення', 'lofu' ) => get_field( 'fixtures', $lofu_project_id ),
	)
);

$lofu_texts = array_filter(
	array(
		__( 'Завдання', 'lofu' ) => $lofu_task,
		__( 'Рішення', 'lofu' ) => $lofu_solution,
	)
);
?>
<template id="<?php echo esc_attr( lofu_project_anchor( $lofu_project_id ) ); ?>-modal">
	<div class="project-modal__bar">
		<div class="ia-container project-modal__bar-inner d-flex align-items-center justify-content-between gap-3">
			<span class="project-modal__brand" aria-hidden="true"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
			<button type="button" class="project-modal__close d-flex align-items-center" data-modal-close>
				<span aria-hidden="true">&larr;</span> <?php esc_html_e( 'Всі проекти', 'lofu' ); ?>
			</button>
		</div>
	</div>

	<div class="project-modal__body">
		<div class="ia-container">
			<div class="project-modal__head d-flex justify-content-between align-items-end flex-wrap">
				<div class="project-modal__heading d-flex flex-column">
					<span class="project-modal__counter">
						<?php
						echo esc_html(
							sprintf(
								/* translators: 1: project number, 2: number of projects, both zero-padded ("03 / 10") */
								__( 'Проект %1$s / %2$s', 'lofu' ),
								sprintf( '%02d', (int) ( $args['index'] ?? 0 ) + 1 ),
								sprintf( '%02d', $lofu_count )
							)
						);
						?>
					</span>
					<h2 id="project-modal-title" class="project-modal__title"><?php echo esc_html( $lofu_title ); ?></h2>
				</div>

				<?php
				if ( $lofu_gallery ) {
					get_template_part( 'template-parts/components/view-switch', null, array( 'controls' => 'project-modal-gallery' ) );
				}
				?>
			</div>

			<?php if ( $lofu_gallery ) : ?>
				<ul id="project-modal-gallery" class="project-modal__gallery" data-view="detail" data-photo-group>
					<?php
					$lofu_photo_count = count( $lofu_gallery );

					foreach ( $lofu_gallery as $lofu_photo_index => $lofu_photo_id ) :
						$lofu_photo_alt = get_post_meta( $lofu_photo_id, '_wp_attachment_image_alt', true );
						$lofu_photo_alt = $lofu_photo_alt ? $lofu_photo_alt : sprintf(
							/* translators: 1: project title, 2: photo number */
							__( '%1$s, photo %2$d', 'lofu' ),
							$lofu_title,
							$lofu_photo_index + 1
						);
						$lofu_photo_full  = wp_get_attachment_image_src( $lofu_photo_id, 'full' );
						$lofu_photo_label = sprintf(
							/* translators: 1: photo number, 2: number of photos */
							__( 'Відкрити фото %1$d з %2$d', 'lofu' ),
							$lofu_photo_index + 1,
							$lofu_photo_count
						);
						?>
						<li class="project-modal__photo">
							<?php // Opens the full, uncropped photo in the photo viewer (photo-viewer.js). ?>
							<button
								type="button"
								class="project-modal__zoom"
								data-photo-src="<?php echo esc_url( $lofu_photo_full ? $lofu_photo_full[0] : '' ); ?>"
								data-photo-srcset="<?php echo esc_attr( (string) wp_get_attachment_image_srcset( $lofu_photo_id, 'full' ) ); ?>"
								data-photo-alt="<?php echo esc_attr( $lofu_photo_alt ); ?>"
								data-photo-caption="<?php echo esc_attr( (string) wp_get_attachment_caption( $lofu_photo_id ) ); ?>"
								aria-label="<?php echo esc_attr( $lofu_photo_label ); ?>"
							>
								<?php
								echo wp_get_attachment_image(
									$lofu_photo_id,
									'lofu-featured-image',
									false,
									array(
										'class'   => 'project-modal__image',
										'alt'     => $lofu_photo_alt,
										'sizes'   => '(min-width: 768px) 50vw, 100vw',
										'loading' => 'lazy',
									)
								);
								?>
							</button>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( $lofu_facts || $lofu_texts ) : ?>
				<div class="project-modal__info">
					<?php if ( $lofu_facts ) : ?>
						<dl class="project-modal__facts d-flex flex-column">
							<?php foreach ( $lofu_facts as $lofu_label => $lofu_value ) : ?>
								<div class="project-modal__fact">
									<dt class="project-modal__fact-label"><?php echo esc_html( $lofu_label ); ?></dt>
									<dd class="project-modal__fact-value"><?php echo esc_html( $lofu_value ); ?></dd>
								</div>
							<?php endforeach; ?>
						</dl>
					<?php endif; ?>

					<?php if ( $lofu_texts ) : ?>
						<div class="project-modal__texts d-flex flex-column">
							<?php foreach ( $lofu_texts as $lofu_label => $lofu_text ) : ?>
								<div class="project-modal__text-block d-flex flex-column">
									<h3 class="project-modal__text-title"><?php echo esc_html( $lofu_label ); ?></h3>
									<p class="project-modal__text"><?php echo esc_html( $lofu_text ); ?></p>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $lofu_count > 1 ) : ?>
			<nav class="project-modal__nav" aria-label="<?php esc_attr_e( 'Other projects', 'lofu' ); ?>">
				<div class="ia-container d-flex justify-content-between gap-4">
					<a class="project-modal__nav-link d-flex flex-column" href="<?php echo esc_url( '#' . lofu_project_anchor( $lofu_prev_id ) ); ?>" data-project="<?php echo esc_attr( lofu_project_anchor( $lofu_prev_id ) ); ?>">
						<span class="project-modal__nav-label"><span aria-hidden="true">&larr;</span> <?php esc_html_e( 'Попередній', 'lofu' ); ?></span>
						<span class="project-modal__nav-name"><?php echo esc_html( get_the_title( $lofu_prev_id ) ); ?></span>
					</a>
					<a class="project-modal__nav-link project-modal__nav-link--next d-flex flex-column" href="<?php echo esc_url( '#' . lofu_project_anchor( $lofu_next_id ) ); ?>" data-project="<?php echo esc_attr( lofu_project_anchor( $lofu_next_id ) ); ?>">
						<span class="project-modal__nav-label"><?php esc_html_e( 'Наступний', 'lofu' ); ?> <span aria-hidden="true">&rarr;</span></span>
						<span class="project-modal__nav-name"><?php echo esc_html( get_the_title( $lofu_next_id ) ); ?></span>
					</a>
				</div>
			</nav>
		<?php endif; ?>
	</div>
</template>