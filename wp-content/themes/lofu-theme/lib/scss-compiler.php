<?php
function iatheme_add_theme_options_page() {
	add_menu_page(
		'SCSS Options',       // Page title
		'SCSS Options',       // Menu title
		'manage_options',      // Capability
		'mytheme-options',     // Menu slug
		'iatheme_render_options_page', // Callback function
		'dashicons-admin-generic',     // Icon
		61                     // Position
	);
}
add_action( 'admin_menu', 'iatheme_add_theme_options_page' );

function iatheme_render_options_page() {
	?>
	<div class="wrap">
		<h1>SCSS Compiler Options</h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'iatheme_options_group' );
			do_settings_sections( 'mytheme-options' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}

function iatheme_register_settings() {
	register_setting( 'iatheme_options_group', 'iatheme_options' );

	add_settings_section(
		'iatheme_main_section',
		'Main Settings',
		null,
		'mytheme-options'
	);

	// Toggle Option: Enable/Disable SCSS Compilation
	add_settings_field(
		'scss_toggle',
		'Enable SCSS Compilation',
		'iatheme_scss_toggle_callback',
		'mytheme-options',
		'iatheme_main_section'
	);

	// Select Option: Compilation Type
	add_settings_field(
		'scss_compilation_type',
		'Compilation Type',
		'iatheme_scss_compilation_type_callback',
		'mytheme-options',
		'iatheme_main_section'
	);

	// Toggle Option: Recompile All Files
	add_settings_field(
		'scss_recompile_all',
		'Recompile All Files',
		'iatheme_scss_recompile_all_callback',
		'mytheme-options',
		'iatheme_main_section'
	);
}
add_action( 'admin_init', 'iatheme_register_settings' );

// Callback for the toggle option
function iatheme_scss_toggle_callback() {
	$options = get_option( 'iatheme_options' );
	?>
	<input type="checkbox" name="iatheme_options[scss_toggle]" value="1" <?php checked( $options['scss_toggle'] ?? '', '1' ); ?> />
	<label for="scss_toggle">Enable SCSS Compilation</label>
	<?php
}

// Callback for the select option
function iatheme_scss_compilation_type_callback() {
	$options = get_option( 'iatheme_options' );
	?>
	<select name="iatheme_options[scss_compilation_type]">
		<option value="EXPANDED" <?php selected( $options['scss_compilation_type'] ?? '', 'EXPANDED' ); ?>>Expanded</option>
		<option value="COMPRESSED" <?php selected( $options['scss_compilation_type'] ?? '', 'COMPRESSED' ); ?>>Compressed
		</option>
	</select>
	<p class="description">Select the SCSS compilation type.</p>
	<?php
}

// Callback for the "Recompile All Files" toggle option
function iatheme_scss_recompile_all_callback() {
	$options = get_option( 'iatheme_options' );
	?>
	<input type="checkbox" name="iatheme_options[scss_recompile_all]" value="1" <?php checked( $options['scss_recompile_all'] ?? '', '1' ); ?> />
	<label for="scss_recompile_all">Recompile all SCSS files </label>
	<?php
}

$options = get_option( 'iatheme_options' );
$is_run = ! empty( $options['scss_toggle'] ); // Check if SCSS compilation is enabled

use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;
if ( $is_run ) {
	add_action( 'wp', 'iatheme_compile_main_scss_to_css' ); // Run on every page load

	function iatheme_compile_main_scss_to_css() {
		$options = get_option( 'iatheme_options' );
		$compilation_type = $options['scss_compilation_type'] ?? 'EXPANDED'; // Default to EXPANDED
		$recompile_all = ! empty( $options['scss_recompile_all'] ); // Check if "Recompile All Files" is enabled
		$scss_path = get_template_directory() . '/assets/scss/main.scss';
		$css_path = get_template_directory() . '/assets/css/main.css';
		$map_path = $css_path . '.map';
		$scss_dir = trailingslashit( wp_normalize_path( get_template_directory() . '/assets/scss' ) );
		$css_dir = trailingslashit( wp_normalize_path( get_template_directory() . '/assets/css' ) );
		$error_log_file = get_template_directory() . '/assets/css/scss-error-log.txt';

		// Reset error state for the current request cycle.
		file_put_contents( $error_log_file, '' );

		// recompile all files
		if ( $recompile_all ) {
			// Check if SCSS directory exists
			if ( ! is_dir( $scss_dir ) ) {
				return;
			}
			require_once get_template_directory() . '/lib/vendor/autoload.php';

			$scss = new Compiler();
			$scss->setImportPaths( $scss_dir );

			// Use RecursiveDirectoryIterator to find all SCSS files in subfolders
			$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $scss_dir ) );
			foreach ( $iterator as $file ) {
				if ( $file->isFile() && pathinfo( $file, PATHINFO_EXTENSION ) === 'scss' ) {
					$scss_file = wp_normalize_path( $file->getPathname() );
					$scss_basename = basename( $scss_file );

					// Skip partials and let dedicated entry files trigger shared partial compilation.
					if ( strpos( $scss_basename, '_' ) === 0 ) {
						continue;
					}

					$relative_path = ltrim( substr( $scss_file, strlen( $scss_dir ) ), '/' );
					$css_file = $css_dir . str_replace( '.scss', '.css', $relative_path );
					$map_file = $css_file . '.map'; // Source map file path

					// Ensure the CSS directory exists
					$css_file_dir = dirname( $css_file );
					if ( ! is_dir( $css_file_dir ) ) {
						mkdir( $css_file_dir, 0755, true );
					}

					// Recompile only if SCSS is newer than CSS
					if ( file_exists( $css_file ) && filemtime( $css_file ) >= filemtime( $scss_file ) ) {
						continue;
					}

					try {
						$scss = new Compiler();
						$scss->setImportPaths( $scss_dir );
						// Dynamically set the output style
						if ( $compilation_type === "COMPRESSED" ) {
							$scss->setOutputStyle( OutputStyle::COMPRESSED );
						} else {
							$scss->setOutputStyle( OutputStyle::EXPANDED );
						}
						$scss->setSourceMap( Compiler::SOURCE_MAP_FILE ); // Enable source map generation
						$scss->setSourceMapOptions( [
							'sourceMapWriteTo' => $map_file, // Write the source map to this file
							'sourceMapURL' => basename( $map_file ), // URL to the source map
							'sourceMapFilename' => basename( $css_file ), // Name of the compiled CSS file
							'sourceMapBasepath' => get_template_directory(), // Base path for SCSS files
							'sourceRoot' => '/assets/scss', // Root path for SCSS files
						] );

						$compiled = $scss->compileFile( $scss_file );

						// Write the compiled CSS
						file_put_contents( $css_file, $compiled->getCss() );

						// Write the source map
						file_put_contents( $map_file, $compiled->getSourceMap() );

						error_log( 'Compiled SCSS file: ' . $scss_file . ' to ' . $css_file );
					} catch (Exception $e) {
						// Log the error to a file
						file_put_contents( $error_log_file, $e->getMessage() . "\n", FILE_APPEND );
						error_log( 'SCSS error in ' . $scss_file . ': ' . $e->getMessage() );
					}
				}
			}
		}
		//recompile main.scss to main.css
		$scss_path = get_template_directory() . '/assets/scss/main.scss';
		$css_path = get_template_directory() . '/assets/css/main.css';
		$map_path = $css_path . '.map';

		// Check if SCSS file exists
		if ( ! file_exists( $scss_path ) ) {
			return;
		}

		// Always recompile if any imported file is newer than the CSS file
		if ( file_exists( $css_path ) ) {
			$main_scss_mtime = filemtime( $scss_path );
			$css_mtime = filemtime( $css_path );

			// Check if any imported file is newer
			$imported_files = iatheme_get_imported_scss_files( $scss_path );
			foreach ( $imported_files as $imported_file ) {
				if ( file_exists( $imported_file ) && filemtime( $imported_file ) > $css_mtime ) {
					$main_scss_mtime = max( $main_scss_mtime, filemtime( $imported_file ) );
				}
			}

			// Skip recompilation if CSS is up-to-date
			if ( $main_scss_mtime <= $css_mtime ) {
				return;
			}
		}

		require_once get_template_directory() . '/lib/vendor/autoload.php';

		try {
			$scss = new Compiler();
			$scss->setImportPaths( dirname( $scss_path ) );
			// Dynamically set the output style
			if ( $compilation_type === "COMPRESSED" ) {
				$scss->setOutputStyle( OutputStyle::COMPRESSED );
			} else {
				$scss->setOutputStyle( OutputStyle::EXPANDED );
			}
			$scss->setSourceMap( Compiler::SOURCE_MAP_FILE ); // Enable source map generation
			$scss->setSourceMapOptions( [
				'sourceMapWriteTo' => $map_path, // Write the source map to this file
				'sourceMapURL' => basename( $map_path ), // URL to the source map
				'sourceMapFilename' => basename( $css_path ), // Name of the compiled CSS file
				'sourceMapBasepath' => get_template_directory(), // Base path for SCSS files
				'sourceRoot' => '/assets/scss', // Root path for SCSS files
			] );

			$compiled = $scss->compileFile( $scss_path );
			file_put_contents( $css_path, $compiled->getCss() );
			file_put_contents( $map_path, $compiled->getSourceMap() );
			error_log( 'Source map generated: ' . $map_path );
		} catch (Exception $e) {
			file_put_contents( $error_log_file, $e->getMessage() . "\n", FILE_APPEND );
			error_log( 'SCSS error in ' . $scss_path . ': ' . $e->getMessage() );
		}
	}

	function iatheme_get_imported_scss_files( $scss_path ) {
		$imported_files = [];

		if ( ! file_exists( $scss_path ) ) {
			return $imported_files;
		}

		$scss_content = file_get_contents( $scss_path );
		$scss_dir = dirname( $scss_path );

		// Match @import, @use and @forward statements.
		preg_match_all( '/@(import|use|forward)\s+["\']([^"\']+)["\']/', $scss_content, $matches );

		if ( ! empty( $matches[2] ) ) {
			foreach ( $matches[2] as $import ) {
				// Skip CSS/native imports.
				if ( preg_match( '/^(https?:|\/\/)/', $import ) || preg_match( '/\.css$/', $import ) ) {
					continue;
				}

				$candidates = [
					$scss_dir . '/' . $import . '.scss',
					$scss_dir . '/' . $import,
					$scss_dir . '/' . dirname( $import ) . '/_' . basename( $import ) . '.scss',
					$scss_dir . '/' . $import . '/_index.scss',
					$scss_dir . '/' . $import . '/index.scss',
				];

				foreach ( $candidates as $candidate ) {
					$normalized_candidate = str_replace( [ '\\', '//' ], [ '/', '/' ], $candidate );
					if ( file_exists( $normalized_candidate ) ) {
						$imported_files[] = $normalized_candidate;
						$imported_files = array_merge( $imported_files, iatheme_get_imported_scss_files( $normalized_candidate ) );
						break;
					}
				}
			}
		}

		return array_values( array_unique( $imported_files ) );
	}

}

add_action( 'wp_head', 'iatheme_show_scss_errors_in_header' );

function iatheme_show_scss_errors_in_header() {
	// Only expose compilation errors to admins in debug mode.
	if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Check if there are any SCSS errors logged
	$error_log_file = get_template_directory() . '/assets/css/scss-error-log.txt';

	if ( file_exists( $error_log_file ) ) {
		$errors = file_get_contents( $error_log_file );

		if ( ! empty( $errors ) ) {
			echo '<div style="background: #cc8686; color: white; padding: 10px; font-size: 14px; z-index: 9999; position: fixed; bottom: 0; left: 0; width: 100%;">';
			echo '<strong>SCSS Compilation Error:</strong> ' . esc_html( $errors );
			echo '</div>';
		}
	}
}