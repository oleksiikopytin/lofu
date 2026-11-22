<?php

namespace WPML\Compatibility\Divi\ThemeBuilderRepair;

class Command {

	public function __invoke( $args, $assocArgs ) {
		$dryRun = isset( $assocArgs['dry-run'] );
		$answer = $this->runner()->run( $dryRun );

		if ( ! $answer['todo'] ) {
			\WP_CLI::success( 'The site has one Theme Builder for all languages. Nothing to repair.' );
			return;
		}

		if ( $answer['keep'] ) {
			\WP_CLI::log( 'Theme Builder that stays: ' . self::post( $answer['keep'] ) );
		}
		foreach ( $answer['unpublish'] as $post ) {
			\WP_CLI::log( ( $dryRun ? 'To unpublish: ' : 'Unpublished: ' ) . self::post( $post ) . ', templates: ' . $post['templates'] );
		}
		foreach ( $answer['keptTemplates'] as $template ) {
			\WP_CLI::log( 'Template that stays: ' . self::template( $template ) );
		}
		foreach ( $answer['otherTemplates'] as $template ) {
			\WP_CLI::log( 'Template only an unpublished Theme Builder lists: ' . self::template( $template ) );
		}
		if ( $answer['languageRows'] > 0 ) {
			\WP_CLI::log( ( $dryRun ? 'Language records to delete: ' : 'Language records deleted: ' ) . ( $dryRun ? $answer['languageRows'] : $answer['rowsDeleted'] ) );
		}
		if ( $answer['settings'] ) {
			\WP_CLI::log( $dryRun ? 'Divi Theme Builder and Divi Templates will be set back to Not translatable.' : 'Divi Theme Builder and Divi Templates are set back to Not translatable.' );
		}

		if ( $dryRun ) {
			\WP_CLI::success( 'Dry run: nothing was written.' );
			return;
		}

		\WP_CLI::success( sprintf( 'Repaired. %d Theme Builder(s) moved to draft.', $answer['unpublished'] ) );
	}

	protected function runner() {
		global $wpdb, $sitepress;

		return Runner::forSite( $wpdb, $sitepress );
	}

	private static function post( array $post ) {
		return sprintf( 'post %d "%s" (language %s, %s)', $post['id'], $post['title'], null === $post['language'] ? 'none' : $post['language'], $post['date'] );
	}

	private static function template( array $template ) {
		return sprintf( 'post %d "%s"', $template['id'], $template['title'] );
	}
}
