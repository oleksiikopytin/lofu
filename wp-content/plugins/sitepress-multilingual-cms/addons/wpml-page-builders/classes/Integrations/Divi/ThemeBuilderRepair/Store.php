<?php

namespace WPML\Compatibility\Divi\ThemeBuilderRepair;

use WPML\Compatibility\Divi\Hooks\ThemeBuilderOneList;

class Store {

	const ELEMENT_TYPE = 'post_et_theme_builder';

	const LIBRARY_META = '_et_library_theme_builder';

	const TEMPLATES_META = '_et_template';

	const RECORD_META = '_wpml_divi_theme_builder_repair';

	const LAST_OPTION = 'wpml_divi_theme_builder_repair_last';

	private $wpdb;

	private $sitepress;

	public function __construct( $wpdb, $sitepress ) {
		$this->wpdb      = $wpdb;
		$this->sitepress = $sitepress;
	}

	public function defaultLanguage() {
		return (string) $this->sitepress->get_default_language();
	}

	public function publishedThemeBuilderPosts() {
		$wpdb = $this->wpdb;

		$rows = (array) $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID AS id, p.post_title AS title, p.post_date AS date, t.language_code AS language
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->prefix}icl_translations t ON t.element_id = p.ID AND t.element_type = %s
				LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
				WHERE p.post_type = %s AND p.post_status = %s AND m.meta_id IS NULL
				ORDER BY p.post_date DESC, p.ID DESC",
				self::ELEMENT_TYPE,
				self::LIBRARY_META,
				'et_theme_builder',
				'publish'
			)
		);

		$posts = [];
		foreach ( $rows as $row ) {
			$posts[] = [
				'id'       => (int) $row->id,
				'title'    => (string) $row->title,
				'date'     => (string) $row->date,
				'language' => null === $row->language || '' === $row->language ? null : (string) $row->language,
			];
		}

		return $posts;
	}

	public function templates( $themeBuilderId ) {
		$templates = [];
		foreach ( (array) get_post_meta( $themeBuilderId, self::TEMPLATES_META, false ) as $templateId ) {
			$templateId = (int) $templateId;
			if ( $templateId > 0 ) {
				$templates[] = [
					'id'    => $templateId,
					'title' => (string) get_the_title( $templateId ),
				];
			}
		}

		return $templates;
	}

	public function languageRowCount() {
		$wpdb = $this->wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}icl_translations WHERE element_type = %s", self::ELEMENT_TYPE )
		);
	}

	public function storedModes() {
		$sync     = (array) $this->sitepress->get_setting( ThemeBuilderOneList::SYNC_OPTION, [] );
		$unlocked = (array) $this->sitepress->get_setting( ThemeBuilderOneList::UNLOCK_OPTION, [] );

		$modes = [];
		foreach ( ThemeBuilderOneList::POST_TYPES as $postType ) {
			$modes[ $postType ] = [
				'mode'     => isset( $sync[ $postType ] ) ? (int) $sync[ $postType ] : ThemeBuilderOneList::MODE_NOT_TRANSLATABLE,
				'unlocked' => ! empty( $unlocked[ $postType ] ),
			];
		}

		return $modes;
	}

	public function unpublish( $themeBuilderId, array $record ) {
		update_post_meta( $themeBuilderId, self::RECORD_META, $record );

		$result = wp_update_post(
			[
				'ID'          => $themeBuilderId,
				'post_status' => 'draft',
			],
			true
		);

		return ! is_wp_error( $result ) && 0 !== $result;
	}

	public function deleteLanguageRows() {
		$deleted = $this->wpdb->delete(
			$this->wpdb->prefix . 'icl_translations',
			[ 'element_type' => self::ELEMENT_TYPE ],
			[ '%s' ]
		);

		do_action( 'wpml_cache_clear' );

		return (int) $deleted;
	}

	public function resetModes() {
		$sync     = (array) $this->sitepress->get_setting( ThemeBuilderOneList::SYNC_OPTION, [] );
		$unlocked = (array) $this->sitepress->get_setting( ThemeBuilderOneList::UNLOCK_OPTION, [] );

		foreach ( ThemeBuilderOneList::POST_TYPES as $postType ) {
			$sync[ $postType ]     = ThemeBuilderOneList::MODE_NOT_TRANSLATABLE;
			$unlocked[ $postType ] = 0;
		}

		$this->sitepress->set_setting( ThemeBuilderOneList::SYNC_OPTION, $sync );
		$this->sitepress->set_setting( ThemeBuilderOneList::UNLOCK_OPTION, $unlocked, true );
	}

	public function writeLast( array $last ) {
		update_option( self::LAST_OPTION, $last, false );
	}

	public function log( $line ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( 'WPML Divi Theme Builder repair: ' . $line );
		}
	}
}
