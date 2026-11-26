<?php

namespace WPML\CF7\TranslationEditor;

use WPML\CF7\Constants;
use WPML\FP\Fns;
use WPML\FP\Lens;
use WPML\FP\Obj;
use WPML\FP\Str;
use WPML\LIB\WP\Hooks;
use function WPML\FP\compose;
use function WPML\FP\spreadArgs;

class TagTexts implements \IWPML_Backend_Action, \IWPML_Frontend_Action {

	const TAG_PREFIX = 'wpmlcf7-';

	const FORM_FIELD = 'field-_form-0';

	const QUOTED_TAG = '#\[(?<tagName>[a-z-]+)(?<beforeTexts>\*?[^\]]*?)(?<texts>["\'][^\]]*)\]#';

	const PIPE = '|';

	const JOB_BLOCK = '#\[' . self::TAG_PREFIX . '(?<index>\d+)-(?<opening>[^\]]*)\](?<texts>[^\[]*)\[/' . self::TAG_PREFIX . '\k<index>-(?<closing>[^\]]*)\]#';

	const JOB_LOG_CLASS = '\WPML\TM\Jobs\JobLog';

	private $sentTags = [];

	public function add_hooks() {
		Hooks::onFilter( 'wpml_tm_translation_job_data', PHP_INT_MAX, 2 )
			->then( spreadArgs( [ self::class, 'preProcess' ] ) );
		Hooks::onFilter( 'wpml_encode_custom_field', 10, 3 )
			->then( spreadArgs( function ( $value, $name, $originalPostId = null ) {
				$sentTags = $originalPostId ? Obj::prop( (int) $originalPostId, $this->sentTags ) : null;

				return self::rebuildForm( $value, $name, $originalPostId, $sentTags );
			} ) );
		Hooks::onAction( 'icl_pro_translation_saved', 10, 3 )
			->then( spreadArgs( function ( $translatedPostId, $fields, $job ) {
				$this->rememberSentTags( $job );
			} ) );
	}

	public static function preProcess( $package, $post ) {
		if ( Constants::POST_TYPE !== Obj::prop( 'post_type', $post ) ) {
			return $package;
		}

		$formContentLens = compose(
			Obj::lensPath( [ 'contents', self::FORM_FIELD, 'data' ] ),
			Lens::isoBase64Decoded()
		);

		$process = function( string $content ):string {
			$index = 0;

			return preg_replace_callback(
				self::QUOTED_TAG,
				function( array $matches ) use ( &$index ):string {
					$rawTagName = $matches['tagName'];

					if ( self::isLeftAsIs( $rawTagName ) ) {
						return $matches[0];
					}

					$index++;
					$tagName = self::TAG_PREFIX . "$index-" . $rawTagName;

					$texts = self::jobTexts( $rawTagName, self::quotedValues( $matches['texts'] ) );
					$texts = $texts ? PHP_EOL . implode( PHP_EOL, $texts ) . PHP_EOL : '';

					return '[' . $tagName . ']' . $texts . '[/' . $tagName . ']';
				},
				$content
			);
		};

		return (array) Obj::over( $formContentLens, $process, $package );
	}

	private function rememberSentTags( $job ) {
		$originalPostId = (int) Obj::prop( 'original_doc_id', $job );
		if ( ! $originalPostId || JobFilter::JOB_ORIGINAL_POST_TYPE_CONTACT_FORM_7 !== Obj::prop( 'original_post_type', $job ) ) {
			return;
		}

		unset( $this->sentTags[ $originalPostId ] );
		foreach ( (array) Obj::prop( 'elements', $job ) as $element ) {
			if ( self::FORM_FIELD === Obj::prop( 'field_type', $element ) ) {
				$this->sentTags[ $originalPostId ] = self::jobTags( (string) base64_decode( (string) Obj::prop( 'field_data', $element ) ) );
			}
		}
	}

	public static function postProcess( $value, $name, $originalPostId = null ) {
		return self::rebuildForm( $value, $name, $originalPostId, null );
	}

	private static function rebuildForm( $value, $name, $originalPostId, $sentTags ) {
		if ( '_form' !== $name || ! is_string( $value ) ) {
			return $value;
		}

		$originalTags = $originalPostId ? self::originalTags( (int) $originalPostId ) : null;
		$usedTags     = [];

		return preg_replace_callback(
			self::JOB_BLOCK,
			function( $matches ) use ( $originalTags, $sentTags, &$usedTags, $originalPostId ) {
				$index = (int) $matches['index'];
				$lines = wpml_collect( self::blockLines( $matches['texts'] ) )
					->map( self::encodeDoubleQuotes() )
					->toArray();
				$texts = self::quoteValues( $lines );

				if ( null === $originalTags ) {
					list( $tagName, $beforeTexts ) = self::splitOpeningTag( $matches['opening'] );

					return '[' . $tagName . $beforeTexts . ' ' . $texts . ']';
				}

				$sentTag       = Obj::prop( $index, (array) $sentTags );
				$originalIndex = self::findOriginalTag( $index, $originalTags, $sentTag, $usedTags );
				if ( null !== $originalIndex ) {
					$usedTags[ $originalIndex ] = true;
					$tag                        = $originalTags[ $originalIndex ];

					if ( $originalIndex !== $index ) {
						self::logMovedOriginalTag( (int) $originalPostId, $index, $originalIndex, $tag['tagName'] );
					}

					$texts = self::quoteValues( self::translatedValues( $tag, $lines, $sentTag ? $sentTag['texts'] : null ) );

					return '[' . $tag['tagName'] . $tag['beforeTexts'] . ' ' . $texts . ']';
				}

				$tagName = self::leadingTagName( $matches['closing'] ) ?: self::leadingTagName( $matches['opening'] );
				self::logMissingOriginalTag( (int) $originalPostId, $index, $tagName );

				return '[' . $tagName . ' ' . $texts . ']';
			},
			$value
		);
	}

	private static function findOriginalTag( int $index, array $originalTags, $sentTag, array $usedTags ) {
		if ( null === $sentTag ) {
			return isset( $originalTags[ $index ] ) ? $index : null;
		}

		$isSameTag = function ( $tag ) use ( $sentTag ) {
			return $tag['tagName'] === $sentTag['tagName'] && $tag['texts'] === $sentTag['texts'];
		};

		$candidates = [ $index => Obj::prop( $index, $originalTags ) ] + $originalTags;
		foreach ( $candidates as $candidateIndex => $tag ) {
			if ( $tag && ! isset( $usedTags[ $candidateIndex ] ) && $isSameTag( $tag ) ) {
				return $candidateIndex;
			}
		}

		if ( isset( $originalTags[ $index ] ) && ! isset( $usedTags[ $index ] ) && $originalTags[ $index ]['tagName'] === $sentTag['tagName'] ) {
			return $index;
		}

		return null;
	}

	private static function jobTags( string $content ): array {
		$tags = [];

		if ( preg_match_all( self::JOB_BLOCK, $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$tags[ (int) $match['index'] ] = [
					'tagName' => self::leadingTagName( $match['opening'] ),
					'texts'   => self::blockLines( $match['texts'] ),
				];
			}
		}

		return $tags;
	}

	private static function quotedValues( string $quotedTexts ): array {
		if ( "'" === substr( $quotedTexts, 0, 1 ) ) {
			preg_match_all( '#"[^"]*"|\'[^\']*\'#', $quotedTexts, $matches );
			$values = wpml_collect( $matches[0] )->map( Fns::unary( Str::trim( '"\'' ) ) );
		} else {
			$values = wpml_collect( preg_split( '#\s+(?=(?:[^"]*"[^"]*")*[^"]*$)#', $quotedTexts ) )
				->map( Fns::unary( Str::trim( '"' ) ) );
		}

		return $values
			->map( self::encodeDoubleQuotes() )
			->values()
			->toArray();
	}

	private static function jobTexts( string $tagName, array $values ): array {
		if ( self::isHidden( $tagName ) ) {
			return [];
		}

		return array_map( [ self::class, 'label' ], $values );
	}

	private static function translatedValues( array $tag, array $lines, $sentTexts ): array {
		if ( self::isHidden( $tag['tagName'] ) ) {
			return $tag['values'];
		}

		$valuesByLabel = [];
		foreach ( $tag['values'] as $value ) {
			$label = self::label( $value );
			if ( ! isset( $valuesByLabel[ $label ] ) ) {
				$valuesByLabel[ $label ] = $value;
			}
		}

		$sameCount = count( $lines ) === count( $tag['values'] );
		$result    = [];
		foreach ( array_values( $lines ) as $i => $line ) {
			$value = null;
			if ( null !== $sentTexts && isset( $sentTexts[ $i ] ) ) {
				$value = Obj::prop( self::label( $sentTexts[ $i ] ), $valuesByLabel );
			}
			if ( null === $value && $sameCount ) {
				$value = $tag['values'][ $i ];
			}

			$result[] = null === $value ? $line : self::withLabel( $value, self::label( $line ) );
		}

		return $result;
	}

	private static function label( string $value ): string {
		$pipe = strpos( $value, self::PIPE );

		return false === $pipe ? $value : substr( $value, 0, $pipe );
	}

	private static function withLabel( string $value, string $label ): string {
		$pipe = strpos( $value, self::PIPE );

		return false === $pipe ? $label : $label . substr( $value, $pipe );
	}

	private static function quoteValues( array $values ): string {
		return wpml_collect( $values )
			->map( Str::wrap( '"', '"' ) )
			->implode( ' ' );
	}

	private static function blockLines( string $texts ): array {
		$texts = trim( $texts );

		return '' === $texts ? [] : explode( PHP_EOL, $texts );
	}

	private static function originalTags( $originalPostId ) {
		$form = get_post_meta( $originalPostId, '_form', true );
		$tags = [];

		if ( ! is_string( $form ) || ! preg_match_all( self::QUOTED_TAG, $form, $matches, PREG_SET_ORDER ) ) {
			return $tags;
		}

		$index = 0;
		foreach ( $matches as $match ) {
			if ( self::isLeftAsIs( $match['tagName'] ) ) {
				continue;
			}
			$index++;
			$values         = self::quotedValues( $match['texts'] );
			$tags[ $index ] = [
				'tagName'     => $match['tagName'],
				'beforeTexts' => rtrim( $match['beforeTexts'] ),
				'values'      => $values,
				'texts'       => self::jobTexts( $match['tagName'], $values ),
			];
		}

		return $tags;
	}

	private static function isLeftAsIs( $rawTagName ) {
		return 'acceptance' === $rawTagName;
	}

	private static function isHidden( string $rawTagName ): bool {
		return 'hidden' === $rawTagName;
	}

	private static function splitOpeningTag( $opening ) {
		$tagName = self::leadingTagName( $opening );

		return [ $tagName, (string) substr( $opening, strlen( $tagName ) ) ];
	}

	private static function leadingTagName( $text ) {
		return preg_match( '#^[a-z-]+#', $text, $match ) ? $match[0] : '';
	}

	private static function logMissingOriginalTag( int $originalPostId, int $index, string $tagName ) {
		self::log(
			'addError',
			'cf7_form_tag_missing_in_original',
			[
				'form'     => $originalPostId,
				'tag'      => $index,
				'tag_name' => $tagName,
				'message'  => 'The original Contact Form 7 form has no tag ' . $index . ' any more: the translated texts were saved without the tag options.',
			]
		);
	}

	private static function logMovedOriginalTag( int $originalPostId, int $index, int $originalIndex, string $tagName ) {
		self::log(
			'add',
			'cf7_form_tag_moved_in_original',
			[
				'form'         => $originalPostId,
				'tag'          => $index,
				'original_tag' => $originalIndex,
				'tag_name'     => $tagName,
				'message'      => 'The original Contact Form 7 form changed while its job was in progress: the texts of tag ' . $index . ' were saved on tag ' . $originalIndex . '.',
			]
		);
	}

	private static function log( string $method, string $id, array $data ) {
		if ( class_exists( self::JOB_LOG_CLASS ) ) {
			call_user_func( [ self::JOB_LOG_CLASS, $method ], $id, $data );
		}
	}

	private static function encodeDoubleQuotes() {
		return Str::pregReplace( '/"/', '&quot;' );
	}
}
