<?php
/**
 * Home page: contacts (large title + columns of contact lines).
 *
 * The title and the ordered columns come from the "Home page" ACF group
 * (`contacts` group field). Each column has a caption and lines; a line with
 * a link (https://, mailto: or tel:) is rendered as a link, and website links
 * open in a new tab.
 *
 * The copyright bar under the section is site-wide and lives in footer.php.
 *
 * @package WordPress
 * @subpackage lofu
 * @since lofu 1.0
 */

$lofu_contacts_field   = get_field( 'contacts' );
$lofu_contacts_columns = array();

foreach ( (array) ( $lofu_contacts_field['columns'] ?? array() ) as $lofu_row ) {
	$lofu_lines = array();

	foreach ( (array) ( $lofu_row['lines'] ?? array() ) as $lofu_line ) {
		$lofu_text = trim( (string) ( $lofu_line['text'] ?? '' ) );
		$lofu_link = trim( (string) ( $lofu_line['link'] ?? '' ) );

		if ( '' === $lofu_text ) {
			continue;
		}

		// A bare email or phone number would otherwise become "http://…".
		if ( is_email( $lofu_link ) ) {
			$lofu_link = 'mailto:' . $lofu_link;
		} elseif ( preg_match( '/^\+?[\d\s().-]{5,}$/', $lofu_link ) ) {
			$lofu_link = 'tel:' . preg_replace( '/[^\d+]/', '', $lofu_link );
		}

		$lofu_link = esc_url( $lofu_link, array( 'http', 'https', 'mailto', 'tel' ) );

		// Email addresses are written as random HTML entities (antispambot()) so
		// they don't appear as plain text in the page source for scrapers.
		// Entities are kept by esc_attr()/esc_html(), which don't double-encode.
		if ( 0 === stripos( $lofu_link, 'mailto:' ) ) {
			$lofu_mailto = explode( '?', substr( $lofu_link, 7 ), 2 );
			$lofu_link   = 'mailto:' . antispambot( sanitize_email( rawurldecode( $lofu_mailto[0] ) ) ) . ( isset( $lofu_mailto[1] ) ? '?' . $lofu_mailto[1] : '' );
		}

		if ( is_email( $lofu_text ) ) {
			$lofu_text = antispambot( $lofu_text );
		}

		$lofu_lines[] = array(
			'text'     => $lofu_text,
			'link'     => $lofu_link,
			'external' => (bool) preg_match( '#^https?://#i', $lofu_link ),
		);
	}

	if ( empty( $lofu_lines ) ) {
		continue;
	}

	$lofu_contacts_columns[] = array(
		'label' => trim( (string) ( $lofu_row['label'] ?? '' ) ),
		'lines' => $lofu_lines,
	);
}

$lofu_contacts_title = $lofu_contacts_field['section_title'] ?? '';

if ( empty( $lofu_contacts_columns ) && ! $lofu_contacts_title ) {
	return;
}
?>
<section id="contacts" class="contacts module"<?php echo $lofu_contacts_title ? ' aria-labelledby="contacts-title"' : ' aria-label="' . esc_attr__( 'Contacts', 'lofu' ) . '"'; ?>>
	<div class="ia-container">
		<div class="contacts__grid">
			<?php if ( $lofu_contacts_title ) : ?>
				<h2 id="contacts-title" class="contacts__title section-title reveal"><span class="reveal__inner"><?php echo esc_html( $lofu_contacts_title ); ?></span></h2>
			<?php endif; ?>

			<?php if ( $lofu_contacts_columns ) : ?>
				<div class="contacts__columns<?php echo 0 === count( $lofu_contacts_columns ) % 2 ? ' contacts__columns--even' : ''; ?>">
					<?php foreach ( $lofu_contacts_columns as $lofu_column ) : ?>
						<div class="contacts__column">
							<?php if ( $lofu_column['label'] ) : ?>
								<h3 class="contacts__label"><?php echo esc_html( $lofu_column['label'] ); ?></h3>
							<?php endif; ?>

							<ul class="contacts__lines">
								<?php foreach ( $lofu_column['lines'] as $lofu_line ) : ?>
									<li class="contacts__line">
										<?php if ( $lofu_line['link'] ) : ?>
											<a class="contacts__link" href="<?php echo esc_attr( $lofu_line['link'] ); // Already passed through esc_url() above. ?>"<?php echo $lofu_line['external'] ? ' target="_blank" rel="noopener"' : ''; ?>>
												<?php echo esc_html( $lofu_line['text'] ); ?>
												<?php if ( $lofu_line['external'] ) : ?>
													<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'lofu' ); ?></span>
												<?php endif; ?>
											</a>
										<?php else : ?>
											<?php echo esc_html( $lofu_line['text'] ); ?>
										<?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
