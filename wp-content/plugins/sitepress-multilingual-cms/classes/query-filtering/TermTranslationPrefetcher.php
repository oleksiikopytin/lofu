<?php

namespace WPML\QueryFiltering;

class TermTranslationPrefetcher implements \IWPML_Action, \IWPML_Frontend_Action_Loader, \IWPML_Backend_Action_Loader {

	private $term_translations;

	private $sitepress;

	private $prefetched_objects = [];

	public function __construct( ?\WPML_Term_Translation $termTranslations = null, ?\SitePress $sitepress = null ) {
		$this->term_translations = $termTranslations;
		$this->sitepress         = $sitepress;
	}

	public function create() {
		return $this;
	}

	public function add_hooks(): void {
		add_filter( 'terms_pre_query', [ $this, 'prefetchTermQuery' ], 10, 2 );
		add_filter( 'pre_wp_nav_menu', [ $this, 'prefetchMenuTerms' ], PHP_INT_MAX, 2 );
		foreach ( [ 'set_object_terms', 'added_term_relationship', 'deleted_term_relationships', 'clean_term_cache', 'wpml_translation_update' ] as $action ) {
			add_action( $action, [ $this, 'forgetPrefetchedObjects' ] );
		}
	}

	public function forgetPrefetchedObjects() {
		$this->prefetched_objects = [];
	}

	public function prefetchTermQuery( $terms, $query ) {
		if ( null !== $terms ) {
			return $terms;
		}
		$qv = $query->query_vars;
		if ( 'count' === ( $qv['fields'] ?? '' ) ) {
			return $terms;
		}
		if ( ! $this->warmsHere() ) {
			return $terms;
		}
		$term_translations = $this->termTranslations();
		if ( ! $term_translations ) {
			return $terms;
		}

		$ttids = $this->termTaxonomyIdsFor( $qv );
		if ( $ttids ) {
			$term_translations->prefetch_ids( $ttids );
		}

		return $terms;
	}

	public function prefetchMenuTerms( $output, $args ) {
		if ( null !== $output || ! $this->warmsHere() ) {
			return $output;
		}
		$term_translations = $this->termTranslations();
		$menu              = $term_translations ? $this->menuOf( $args ) : null;
		if ( ! $menu || empty( $menu->count ) ) {
			return $output;
		}

		global $wpdb;
		$ttids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT tt.term_taxonomy_id
				FROM {$wpdb->term_relationships} tr
				JOIN {$wpdb->postmeta} item_type ON item_type.post_id = tr.object_id AND item_type.meta_key = '_menu_item_type' AND item_type.meta_value = 'taxonomy'
				JOIN {$wpdb->postmeta} item_object ON item_object.post_id = tr.object_id AND item_object.meta_key = '_menu_item_object'
				JOIN {$wpdb->postmeta} item_object_id ON item_object_id.post_id = tr.object_id AND item_object_id.meta_key = '_menu_item_object_id'
				JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = CAST( item_object_id.meta_value AS UNSIGNED ) AND tt.taxonomy = item_object.meta_value
				WHERE tr.term_taxonomy_id = %d",
				$menu->term_taxonomy_id
			)
		);
		if ( $ttids ) {
			$term_translations->prefetch_ids( array_map( 'intval', $ttids ) );
		}

		return $output;
	}

	private function menuOf( $args ) {
		if ( ! is_object( $args ) ) {
			return null;
		}
		$menu = ! empty( $args->menu ) ? wp_get_nav_menu_object( $args->menu ) : false;
		if ( ! $menu && ! empty( $args->theme_location ) ) {
			$locations = get_nav_menu_locations();
			if ( isset( $locations[ $args->theme_location ] ) ) {
				$menu = wp_get_nav_menu_object( $locations[ $args->theme_location ] );
			}
		}

		return $menu instanceof \WP_Term ? $menu : null;
	}

	private function warmsHere() {
		$sitepress = $this->sitepress();
		if ( ! $sitepress || ! $sitepress->get_setting( 'auto_adjust_ids' ) ) {
			return false;
		}

		return ! ( ( is_admin() && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) || wpml_is_rest_request() );
	}

	private function termTaxonomyIdsFor( $qv ) {
		global $wpdb;

		if ( ! empty( $qv['object_ids'] ) ) {
			$ids = array_values(
				array_filter(
					array_map( 'intval', (array) $qv['object_ids'] ),
					function ( $id ) {
						return $id && ! isset( $this->prefetched_objects[ $id ] );
					}
				)
			);
			if ( ! $ids ) {
				return [];
			}
			foreach ( $ids as $id ) {
				$this->prefetched_objects[ $id ] = true;
			}
			return array_map(
				'intval',
				$wpdb->get_col(
					"SELECT DISTINCT term_taxonomy_id FROM {$wpdb->term_relationships}
					 WHERE object_id IN (" . implode( ',', $ids ) . ')'
				)
			);
		}

		if ( ! empty( $qv['term_taxonomy_id'] ) ) {
			return array_values( array_filter( wp_parse_id_list( $qv['term_taxonomy_id'] ) ) );
		}

		if ( ! empty( $qv['include'] ) ) {
			return $this->termTaxonomyIdsOfTermIds( array_values( array_filter( wp_parse_id_list( $qv['include'] ) ) ), $qv['taxonomy'] ?? [] );
		}

		return [];
	}

	private function termTaxonomyIdsOfTermIds( array $term_ids, $taxonomies ) {
		global $wpdb;

		$taxonomies = array_values( array_filter( (array) $taxonomies, 'is_string' ) );
		$ttids      = [];
		$unknown    = [];
		foreach ( $term_ids as $term_id ) {
			$term = wp_cache_get( $term_id, 'terms' );
			if ( is_object( $term ) && isset( $term->term_taxonomy_id, $term->taxonomy )
				&& ( ! $taxonomies || in_array( $term->taxonomy, $taxonomies, true ) )
			) {
				$ttids[] = (int) $term->term_taxonomy_id;
			} else {
				$unknown[] = (int) $term_id;
			}
		}

		if ( $unknown && $taxonomies ) {
			$found = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id IN (" . implode( ',', array_fill( 0, count( $unknown ), '%d' ) ) . ') AND taxonomy IN (' . implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) ) . ')',
					array_merge( $unknown, $taxonomies )
				)
			);
			$ttids = array_merge( $ttids, array_map( 'intval', $found ) );
		} elseif ( $unknown ) {
			$found = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE term_id IN (" . implode( ',', array_fill( 0, count( $unknown ), '%d' ) ) . ')',
					$unknown
				)
			);
			$ttids = array_merge( $ttids, array_map( 'intval', $found ) );
		}

		return $ttids;
	}

	private function termTranslations() {
		if ( $this->term_translations ) {
			return $this->term_translations;
		}
		global $wpml_term_translations;

		return $wpml_term_translations;
	}

	private function sitepress() {
		if ( $this->sitepress ) {
			return $this->sitepress;
		}
		global $sitepress;

		return $sitepress;
	}
}
