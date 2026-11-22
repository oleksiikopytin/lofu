<?php

namespace WPML\Compatibility\FusionBuilder\Frontend;

use WPML\API\Sanitize;
use WPML\Compatibility\FusionBuilder\BaseHooks;
use WPML\FP\Obj;

class Hooks extends BaseHooks implements \IWPML_Frontend_Action, \IWPML_DIC_Action {

	const LAYOUT_POST_TYPE = 'fusion_tb_layout';

	const CONDITION_ID_PREFIXES = [ 'specific_', 'children_of_', 'archive_of_', 'taxonomy_of_' ];

	private $sitepress;

	private $translatedOffCanvasConditions = [];

	public function __construct(
		\SitePress $sitepress
	) {
		$this->sitepress = $sitepress;
	}

	public function add_hooks() {
		add_filter( 'nav_menu_link_attributes', [ $this, 'addMenuLinkCssClass' ], 10, 2 );
		add_filter( 'fusion_get_all_meta', [ $this, 'translateOffCanvasConditionId' ] );
		add_action( 'pre_get_posts', [ $this, 'enableFiltersForThemeBuilderQuery' ] );
		add_filter( 'the_posts', [ $this, 'translateThemeBuilderConditionIds' ], 10, 2 );
		add_filter( 'the_posts', [ $this, 'prefetchContentElementIds' ] );
	}

	public function addMenuLinkCssClass( $atts, $item ) {
		if ( 'wpml_ls_menu_item' === $item->type ) {
			$class         = Obj::prop( 'class', $atts );
			$atts['class'] = $class ? "$class wpml-ls-link" : 'wpml-ls-link';
		}

		return $atts;
	}

	public function translateOffCanvasConditionId( $data ) {
		if ( ! is_array( $data ) || empty( $data['layout_conditions'] ) || ! is_string( $data['layout_conditions'] ) ) {
			return $data;
		}

		$cacheKey = $this->sitepress->get_current_language() . '|' . $data['layout_conditions'];
		if ( ! array_key_exists( $cacheKey, $this->translatedOffCanvasConditions ) ) {
			$this->translatedOffCanvasConditions[ $cacheKey ] = $this->translateOffCanvasConditions( $data['layout_conditions'] );
		}

		if ( null !== $this->translatedOffCanvasConditions[ $cacheKey ] ) {
			$data['layout_conditions'] = $this->translatedOffCanvasConditions[ $cacheKey ];
		}

		return $data;
	}

	private function translateOffCanvasConditions( string $layoutConditions ) {
		$conditions = json_decode( $layoutConditions, true );
		if ( ! is_array( $conditions ) ) {
			return null;
		}

		$this->prefetchConditionPosts( array_map( 'strval', array_keys( $conditions ) ) );

		$translatedConditions = [];
		foreach ( $conditions as $key => $condition ) {
			$translatedConditions[ $this->translateConditionKey( (string) $key ) ] = $condition;
		}

		return wp_json_encode( $translatedConditions ) ?: null;
	}

	public function enableFiltersForThemeBuilderQuery( $query ) {
		if (
			$query instanceof \WP_Query
			&& [ self::LAYOUT_POST_TYPE ] === (array) $query->get( 'post_type' )
			&& $query->get( 'suppress_filters' )
		) {
			$query->set( 'suppress_filters', false );
		}
	}

	public function translateThemeBuilderConditionIds( $posts, $query ) {
		if ( ! is_array( $posts ) || ! $query instanceof \WP_Query ) {
			return $posts;
		}

		if ( ! in_array( self::LAYOUT_POST_TYPE, (array) $query->get( 'post_type' ), true ) ) {
			return $posts;
		}

		foreach ( $posts as $post ) {
			if ( is_object( $post ) && isset( $post->post_type, $post->post_content ) && self::LAYOUT_POST_TYPE === $post->post_type ) {
				$post->post_content = $this->translateConditionIds( $post->post_content );
			}
		}

		return $posts;
	}

	private function translateConditionIds( string $post_content ): string {
		if ( false === strpos( $post_content, '|' ) ) {
			return $post_content;
		}

		$conditionKey = '/(?:' . implode( '|', self::CONDITION_ID_PREFIXES ) . ')[a-z0-9_-]+\|\d+/';
		if ( preg_match_all( $conditionKey, $post_content, $keys ) ) {
			$this->prefetchConditionPosts( $keys[0] );
		}

		$translated = preg_replace_callback(
			$conditionKey,
			function ( $matches ) {
				return $this->translateConditionKey( $matches[0] );
			},
			$post_content
		);

		return null === $translated ? $post_content : $translated;
	}

	private function prefetchConditionPosts( array $keys ) {
		$ids = [];
		foreach ( $keys as $key ) {
			foreach ( self::CONDITION_ID_PREFIXES as $prefix ) {
				if ( 0 === strpos( $key, $prefix ) && false !== strpos( $key, '|' ) ) {
					list( $pattern, $id ) = explode( '|', $key, 2 );
					if ( post_type_exists( substr( $pattern, strlen( $prefix ) ) ) ) {
						$ids[] = (int) $id;
					}
					break;
				}
			}
		}
		$this->prefetchPosts( $ids );
	}

	public function prefetchContentElementIds( $posts ) {
		if ( ! is_array( $posts ) ) {
			return $posts;
		}

		$ids = [];
		foreach ( $posts as $post ) {
			if ( ! is_object( $post ) || empty( $post->post_content ) || false === strpos( $post->post_content, '[fusion_' ) ) {
				continue;
			}
			if ( preg_match_all( '/\b[a-z_]*image_id="(\d+)|\[fusion_global id="(\d+)"/', $post->post_content, $matches ) ) {
				$ids = array_merge( $ids, array_filter( $matches[1] ), array_filter( $matches[2] ) );
			}
		}
		$this->prefetchPosts( array_map( 'intval', $ids ) );

		return $posts;
	}

	private function prefetchPosts( array $ids ) {
		global $wpml_post_translations;

		$ids = array_values( array_unique( array_filter( $ids ) ) );
		if ( $ids && $wpml_post_translations instanceof \WPML_Element_Translation ) {
			$wpml_post_translations->prefetch_ids( $ids );
		}
	}

	private function translateConditionKey( string $key ): string {
		foreach ( self::CONDITION_ID_PREFIXES as $prefix ) {
			if ( 0 === strpos( $key, $prefix ) && false !== strpos( $key, '|' ) ) {
				list( $pattern, $id ) = explode( '|', $key, 2 );
				$type                 = substr( $pattern, strlen( $prefix ) );

				return $pattern . '|' . $this->sitepress->get_object_id( (int) $id, $type, true );
			}
		}

		return $key;
	}
}
