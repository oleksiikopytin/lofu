<?php


namespace WPML\PB\Elementor\Media\Modules;

use WPML\PB\Elementor\Helper\DynamicTag;

class AllNodes extends \WPML_Elementor_Media_Node {

	const DYNAMIC = '__dynamic__';

	private $backgroundKeys;

	private function getBackgroundKeys( $type ) {
		if ( ! isset( $this->backgroundKeys ) ) {
			$this->backgroundKeys = apply_filters(
				'wpml_elementor_media_backgrounds_to_translate',
				[
					'simple_fields'   => [
						'_background_image',
						'background_image',
						'background_overlay_image',
						'background_hover_image',
						'background_image_mobile',
						'background_image_tablet',
					],
					'repeater_fields' => [
						'background_slideshow_gallery',
					],
				]
			);
		}

		return isset( $this->backgroundKeys[ $type ] ) && is_array( $this->backgroundKeys[ $type ] ) ? $this->backgroundKeys[ $type ] : [];
	}

	public function translate( $settings, $target_lang, $source_lang ) {
		foreach ( $this->getBackgroundKeys( 'simple_fields' ) as $background ) {
			$settings = $this->translate_image_property( $settings, $background, $target_lang, $source_lang );
		}

		foreach ( $this->getBackgroundKeys( 'repeater_fields' ) as $background ) {
			$settings = $this->translate_images_property( $settings, $background, $target_lang, $source_lang );
		}

		return is_array( $settings ) ? $this->translateLightboxImages( $settings, $target_lang, $source_lang ) : $settings;
	}

	private function translateLightboxImages( array $settings, string $target_lang, string $source_lang ): array {
		$settings = $this->translateLightboxTags( $settings, $target_lang, $source_lang );

		foreach ( $settings as &$value ) {
			if ( $this->isRepeater( $value ) ) {
				foreach ( $value as &$item ) {
					$item = $this->translateLightboxTags( $item, $target_lang, $source_lang );
				}
				unset( $item );
			}
		}
		unset( $value );

		return $settings;
	}

	private function translateLightboxTags( array $settings, string $target_lang, string $source_lang ): array {
		if ( ! isset( $settings[ self::DYNAMIC ] ) || ! is_array( $settings[ self::DYNAMIC ] ) ) {
			return $settings;
		}

		$translateImage = function ( $image ) use ( $target_lang, $source_lang ) {
			return is_array( $image ) ? $this->translate_image_array( $image, $target_lang, $source_lang ) : $image;
		};

		foreach ( $settings[ self::DYNAMIC ] as &$tag ) {
			if ( is_string( $tag ) ) {
				$tag = DynamicTag::mapSetting( $tag, [ 'lightbox' ], 'image', $translateImage );
			}
		}
		unset( $tag );

		return $settings;
	}

	private function isRepeater( $value ): bool {
		if ( ! is_array( $value ) || ! $value ) {
			return false;
		}

		foreach ( $value as $key => $item ) {
			if ( ! is_int( $key ) || ! is_array( $item ) ) {
				return false;
			}
		}

		return true;
	}

}
