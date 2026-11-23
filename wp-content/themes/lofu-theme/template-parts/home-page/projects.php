<?php
/**
 * Home page: projects (gallery of project cards + project window).
 *
 * The section title and the hand-picked, ordered projects come from the
 * "Home page" ACF group (`projects` group field). Each card links to the
 * project's own URL, which lofu_project_redirect() sends back here with a
 * `#project-{slug}` hash.
 *
 * Every project's details are rendered once into a <template>. projects.js
 * opens the matching one in the `project-modal` dialog when a card is
 * clicked or the page loads with that hash, and keeps the hash in sync.
 *
 * The cards are laid out by the view switcher (detail grid, masonry tiles or
 * a horizontal slider); the detail grid's positions and every card's crop
 * repeat every 10 cards (see projects.scss).
 *
 * @package WordPress
 * @subpackage lofu
 * @since lofu 1.0
 */

$lofu_projects_field = get_field( 'projects' );
// The list may hold the default language's IDs; show this language's versions.
$lofu_project_ids = array_values(
	array_filter(
		lofu_translated_project_ids( $lofu_projects_field['selected_projects'] ?? array() ),
		function ( $project_id ) {
			return 'publish' === get_post_status( $project_id );
		}
	)
);

if ( empty( $lofu_project_ids ) ) {
	return;
}

$lofu_projects_title = $lofu_projects_field['section_title'] ?? '';
$lofu_project_count  = count( $lofu_project_ids );
?>
<section id="projects" class="projects"<?php echo $lofu_projects_title ? ' aria-labelledby="projects-title"' : ''; ?>>
	<div class="ia-container">
		<div class="projects__head d-flex justify-content-between align-items-end flex-wrap">
			<?php if ( $lofu_projects_title ) : ?>
				<h2 id="projects-title" class="projects__title projects__mask"><span class="projects__mask-inner"><?php echo esc_html( $lofu_projects_title ); ?></span></h2>
			<?php endif; ?>

			<?php get_template_part( 'template-parts/components/view-switch', null, array( 'controls' => 'projects-list' ) ); ?>
		</div>

		<ul id="projects-list" class="projects__list" data-view="detail">
			<?php
			foreach ( $lofu_project_ids as $lofu_project_id ) :
				$lofu_project_name     = get_the_title( $lofu_project_id );
				$lofu_project_location = get_field( 'location', $lofu_project_id );
				$lofu_project_image    = get_post_thumbnail_id( $lofu_project_id );

				if ( ! $lofu_project_image ) {
					$lofu_project_gallery = get_field( 'gallery', $lofu_project_id );
					$lofu_project_image   = $lofu_project_gallery[0] ?? 0;
				}
				?>
				<li class="projects__item">
					<a
						id="<?php echo esc_attr( lofu_project_anchor( $lofu_project_id ) ); ?>"
						class="projects__card"
						href="<?php echo esc_url( get_permalink( $lofu_project_id ) ); ?>"
						data-project="<?php echo esc_attr( lofu_project_anchor( $lofu_project_id ) ); ?>"
						aria-haspopup="dialog"
					>
						<span class="projects__media">
							<?php
							if ( $lofu_project_image ) {
								echo wp_get_attachment_image(
									$lofu_project_image,
									'lofu-featured-image',
									false,
									array(
										'class'   => 'projects__image',
										'alt'     => '',
										'sizes'   => '(min-width: 768px) 50vw, 100vw',
										'loading' => 'lazy',
									)
								);
							}
							?>
						</span>
						<span class="projects__caption d-flex justify-content-between">
							<span class="projects__name"><?php echo esc_html( $lofu_project_name ); ?></span>
							<?php if ( $lofu_project_location ) : ?>
								<span class="projects__location"><?php echo esc_html( $lofu_project_location ); ?></span>
							<?php endif; ?>
						</span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>

<dialog id="project-modal" class="project-modal" aria-labelledby="project-modal-title"></dialog>

<?php get_template_part( 'template-parts/components/photo-viewer' ); ?>

<?php
foreach ( $lofu_project_ids as $lofu_index => $lofu_project_id ) {
	get_template_part(
		'template-parts/home-page/project-modal',
		null,
		array(
			'project_id' => $lofu_project_id,
			'index'      => $lofu_index,
			'count'      => $lofu_project_count,
			'prev_id'    => $lofu_project_ids[ ( $lofu_index - 1 + $lofu_project_count ) % $lofu_project_count ],
			'next_id'    => $lofu_project_ids[ ( $lofu_index + 1 ) % $lofu_project_count ],
		)
	);
}
