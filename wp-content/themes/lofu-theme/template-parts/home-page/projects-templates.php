<?php
/**
 * Home page: every project window's <template>, served on its own by
 * lofu_home_templates_endpoint() and loaded into the projects section by
 * lazy-templates.js (see projects.php).
 *
 * @package WordPress
 * @subpackage lofu
 * @since lofu 1.0
 */

$lofu_project_ids   = lofu_home_project_ids();
$lofu_project_count = count( $lofu_project_ids );

lofu_prime_posts( $lofu_project_ids, array( 'gallery' ) );

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
